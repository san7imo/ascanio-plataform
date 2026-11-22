<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use App\Modules\Documents\Actions\UploadDocumentVersion;
use App\Modules\Documents\Data\CapacitySnapshot;
use App\Modules\Documents\Jobs\ScanDocumentVersion;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Storage\CapacityProbe;
use App\Modules\Documents\Storage\MalwareScanner;
use App\Modules\Documents\Storage\UnavailableCapacityProbe;
use App\Modules\Documents\Storage\UnavailableMalwareScanner;
use App\Modules\IdentityAccess\Actions\ChangeMembershipStatus;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SyntheticDocumentEnvironment;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use WorkspaceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        SyntheticDocumentEnvironment::configure();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        try {
            Storage::disk('documents')->deleteDirectory('tenants');
            $this->artisan('migrate:fresh')->assertSuccessful();
        } finally {
            RefreshDatabaseState::$migrated = false;
            parent::tearDown();
        }
    }

    private function upload(User $actor, Tenant $tenant, string $content = 'Synthetic private document', ?Document $document = null, ?string $key = null): DocumentVersion
    {
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $actor->id)->sole());
        try {
            return app(UploadDocumentVersion::class)->execute($actor, $tenant, $document?->id,
                ['title' => 'Documento sintético', 'description' => 'Descripción privada', 'upload_key' => $key ?? (string) Str::uuid(), 'expected_version' => $document?->version_count],
                UploadedFile::fake()->createWithContent('fixture.txt', $content));
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    private function scan(DocumentVersion $version): void
    {
        $job = Queue::pushed(ScanDocumentVersion::class)->first(fn ($job) => $job->versionId === $version->id);
        $this->assertNotNull($job);
        app()->call([$job, 'handle']);
    }

    public function test_original_is_private_quarantined_and_an_idempotent_replay_cannot_overwrite_it(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $key = (string) Str::uuid();
        $version = $this->upload($owner, $tenant, key: $key);
        $this->assertSame('quarantined', $version->fresh()->scan_status);
        $this->assertSame(hash('sha256', 'Synthetic private document'), $version->sha256);
        $this->assertStringStartsWith('tenants/'.$tenant->id.'/documents/', $version->storage_path);
        Storage::disk('documents')->assertExists($version->storage_path);
        $again = $this->upload($owner, $tenant, key: $key);
        $this->assertSame($version->id, $again->id);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('document_versions', 1);
        Queue::assertPushed(ScanDocumentVersion::class, 1);
        $this->actingAs($owner)->get(route('documents.download', [$tenant, $version->document_id, $version->id]))->assertStatus(409);
        $this->get('/storage/'.$version->storage_path)->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_clean_exact_version_downloads_as_attachment_and_leaves_an_audited_access(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->upload($owner, $tenant);
        $this->scan($version);
        $this->assertSame('clean', $version->fresh()->scan_status);
        $response = $this->actingAs($owner)->get(route('documents.download', [$tenant, $version->document_id, $version->id]));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('Synthetic private document', $response->streamedContent());
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->id, 'actor_id' => $owner->id, 'action' => 'document.download_authorized', 'resource_id' => $version->id]);
        $this->assertContextEmpty();
    }

    public function test_metadata_permission_and_a_signed_link_do_not_grant_file_access(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->upload($owner, $tenant);
        $this->scan($version);
        $viewer = User::factory()->create();
        $this->memberFor($tenant, $viewer, ['tenant.view', 'documents.view']);
        $url = URL::signedRoute('documents.download', ['workspace' => $tenant->id, 'document' => $version->document_id, 'version' => $version->id]);
        $this->actingAs($viewer)->get(route('documents.show', [$tenant, $version->document_id]))->assertInertia(fn (Assert $page) => $page->component('documents/Show')->where('versions.data.0.can_download', false)->missing('versions.data.0.storage_path')->missing('versions.data.0.uploaded_by'));
        $this->get($url)->assertForbidden();
        $this->post(route('documents.retry', [$tenant, $version->document_id, $version->id]))->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_lists_counts_and_nested_versions_downloads_cannot_cross_tenants(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $va = $this->upload($owner, $a);
        $vb = $this->upload($owner, $b);
        $this->scan($vb);
        DB::table('documents')->where('tenant_id', $b->id)->update(['title' => 'Secret B']);
        $this->actingAs($owner)->withSession(['current_workspace_id' => $b->id])->withHeaders(['X-Tenant-ID' => $b->id])->get(route('documents.index', $a))->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.id', $va->document_id));
        $this->get(route('documents.index', [$a, 'search' => 'Secret B']))->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
        $this->get(route('documents.index', [$a, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->has('documents.data', 0));
        $this->get(route('documents.show', [$a, $vb->document_id]))->assertNotFound();
        $this->get(route('documents.download', [$a, $vb->document_id, $vb->id]))->assertNotFound();
        $this->get(route('documents.download', [$a, $va->document_id, $vb->id]))->assertNotFound();
        $this->post(route('documents.retry', [$a, $va->document_id, $vb->id]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('documents.index', $a))->assertNotFound();
    }

    public function test_multiple_versions_preserve_originals_and_reject_stale_append_or_changed_upload_key_payload(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $key = (string) Str::uuid();
        $v1 = $this->upload($owner, $tenant, key: $key);
        $document = Document::where('tenant_id', $tenant->id)->sole();
        $v2 = $this->upload($owner, $tenant, 'Second synthetic version', $document);
        $this->assertSame(2, $v2->number);
        $this->assertNotSame($v1->storage_path, $v2->storage_path);
        $this->assertSame('Synthetic private document', Storage::disk('documents')->get($v1->storage_path));
        $this->assertSame('Second synthetic version', Storage::disk('documents')->get($v2->storage_path));
        $this->actingAs($owner)->post(route('documents.versions.store', [$tenant, $document->id]), ['file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Stale append'), 'upload_key' => (string) Str::uuid(), 'expected_version' => 1])->assertSessionHasErrors('expected_version');
        $this->post(route('documents.store', $tenant), ['title' => 'Different', 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Different'), 'upload_key' => $key])->assertSessionHasErrors('upload_key');
        $this->assertDatabaseCount('document_versions', 2);
    }

    public function test_infected_failed_unknown_or_missing_scanner_results_never_release_a_file(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $infected = $this->upload($owner, $tenant, 'synthetic-infected');
        $this->scan($infected);
        $this->assertSame('infected', $infected->fresh()->scan_status);
        $this->actingAs($owner)->get(route('documents.download', [$tenant, $infected->document_id, $infected->id]))->assertStatus(409);
        $version = $this->upload($owner, $tenant);
        app()->instance(MalwareScanner::class, new UnavailableMalwareScanner);
        try {
            $this->scan($version);
            $this->fail('Unavailable scanner must remain retryable and fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('análisis', $exception->getMessage());
        }
        $this->assertSame('scan_failed', $version->fresh()->scan_status);
        $this->assertSame('scanner_unavailable', $version->fresh()->failure_code);
        $this->get(route('documents.download', [$tenant, $version->document_id, $version->id]))->assertStatus(409);
        $this->assertContextEmpty();
    }

    public function test_retry_has_fresh_authority_and_old_jobs_or_duplicate_callbacks_cannot_change_the_new_generation(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->upload($owner, $tenant);
        $oldJob = Queue::pushed(ScanDocumentVersion::class)->sole();
        $version->update(['scan_status' => 'scan_failed', 'failure_code' => 'scan_error']);
        $this->actingAs($owner)->post(route('documents.retry', [$tenant, $version->document_id, $version->id]))->assertRedirect();
        $this->assertSame(2, $version->fresh()->scan_generation);
        app()->call([$oldJob, 'handle']);
        $oldJob->failed(new \RuntimeException('Synthetic old callback'));
        $this->assertSame('quarantined', $version->fresh()->scan_status);
        $newJob = Queue::pushed(ScanDocumentVersion::class)->last();
        app()->call([$newJob, 'handle']);
        $events = DB::table('audit_events')->where('tenant_id', $tenant->id)->where('action', 'document.scan_clean')->count();
        app()->call([$newJob, 'handle']);
        $this->assertSame($events, DB::table('audit_events')->where('tenant_id', $tenant->id)->where('action', 'document.scan_clean')->count());
        $this->assertSame('clean', $version->fresh()->scan_status);
    }

    public function test_membership_revocation_invalidates_pending_scans_and_downloads_but_keeps_an_independent_workspace(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $actor = User::factory()->create();
        $member = $this->memberFor($a, $actor, ['tenant.view', 'documents.view', 'documents.upload', 'documents.download']);
        $this->memberFor($b, $actor, ['tenant.view', 'documents.view']);
        $clean = $this->upload($actor, $a, 'Previously clean');
        $this->scan($clean);
        $response = $this->actingAs($actor)->get(route('documents.download', [$a, $clean->document_id, $clean->id]));
        $response->assertOk();
        $this->assertSame('Previously clean', $response->streamedContent());
        $version = $this->upload($actor, $a);
        $proof = $this->proofFor($owner);
        app(TenantContext::class)->establish($a, $a->memberships()->where('user_id', $owner->id)->sole());
        app(ChangeMembershipStatus::class)->execute($owner, $a, $member->id, 'revoked', $proof);
        app(TenantContext::class)->clear();
        $this->scan($version);
        $this->assertSame('blocked', $version->fresh()->scan_status);
        $this->assertSame('authority_revoked', $version->fresh()->failure_code);
        $this->actingAs($actor)->get(route('documents.download', [$a, $version->document_id, $version->id]))->assertNotFound();
        $this->get(route('documents.download', [$a, $clean->document_id, $clean->id]))->assertNotFound();
        $this->get(route('documents.index', $b))->assertOk();
    }

    public function test_corrupt_or_missing_originals_are_blocked_before_any_download_bytes_are_sent(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        foreach (['corrupt', 'missing'] as $scenario) {
            $version = $this->upload($owner, $tenant);
            $this->scan($version);
            if ($scenario === 'corrupt') {
                Storage::disk('documents')->put($version->storage_path, 'Changed infrastructure bytes');
            } else {
                Storage::disk('documents')->delete($version->storage_path);
            }
            $this->actingAs($owner)->get(route('documents.download', [$tenant, $version->document_id, $version->id]))->assertStatus(409);
            $this->assertSame('blocked', $version->fresh()->scan_status);
            $this->assertSame($scenario === 'corrupt' ? 'integrity_mismatch' : 'file_missing', $version->fresh()->failure_code);
        }
    }

    public function test_default_adapters_disabled_flag_unknown_capacity_and_public_storage_roots_fail_closed(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $this->actingAs($owner);
        config(['documents.uploads_enabled' => false]);
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic')])->assertStatus(423);
        $this->get(route('documents.index', $tenant))->assertInertia(fn (Assert $page) => $page->where('uploadsEnabled', false));
        config(['documents.uploads_enabled' => true]);
        app()->instance(MalwareScanner::class, new UnavailableMalwareScanner);
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic')])->assertSessionHasErrors('file');
        SyntheticDocumentEnvironment::configure(false);
        app()->instance(CapacityProbe::class, new UnavailableCapacityProbe);
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic')])->assertSessionHasErrors('file');
        SyntheticDocumentEnvironment::configure(false);
        config(['filesystems.disks.documents.root' => public_path()]);
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic')])->assertServerError();
        $this->assertDatabaseCount('document_versions', 0);
    }

    public function test_capacity_reserves_freshness_and_tenant_and_shared_managed_budgets_are_enforced_without_exposing_other_tenants(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $version = $this->upload($owner, $a, str_repeat('x', 100));
        config(['documents.app_limit_bytes' => 150]);
        $this->actingAs($owner)->post(route('documents.store', $b), ['title' => 'B', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', str_repeat('y', 100))])->assertSessionHasErrors('file');
        config(['documents.app_limit_bytes' => 16777216, 'documents.tenant_limit_bytes' => 150]);
        $this->post(route('documents.store', $a), ['title' => 'A', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', str_repeat('y', 100))])->assertSessionHasErrors('file');
        foreach ([[104857600, 10000, -300], [104857600, 10000, 300], [1048576, 10000, 0], [104857600, 10, 0]] as [$bytes, $inodes, $age]) {
            app()->instance(CapacityProbe::class, new class($bytes, $inodes, $age) implements CapacityProbe
            {
                public function __construct(private int $bytes, private int $inodes, private int $age) {}

                public function snapshot(): CapacitySnapshot
                {
                    return new CapacitySnapshot($this->bytes, $this->inodes, CarbonImmutable::now()->addSeconds($this->age));
                }
            });
            $this->post(route('documents.store', $b), ['title' => 'B', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic')])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('document_versions', 1);
        Storage::disk('documents')->assertExists($version->storage_path);
    }

    public function test_client_fields_mime_extension_and_file_limits_cannot_bypass_the_server(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $this->actingAs($owner);
        foreach (['tenant_id' => (string) Str::uuid(), 'scan_status' => 'clean', 'storage_path' => '../../public/file', 'sha256' => str_repeat('0', 64)] as $field => $value) {
            $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic'), $field => $value])->assertSessionHasErrors($field);
        }
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('spoof.pdf', 'Plain text, not PDF')])->assertSessionHasErrors('file');
        config(['documents.max_file_bytes' => 10]);
        $this->post(route('documents.store', $tenant), ['title' => 'Test', 'upload_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fixture.txt', 'Too large synthetic text')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertCount(0, Storage::disk('documents')->allFiles());
    }

    public function test_failed_audit_rolls_back_metadata_and_removes_only_the_uncommitted_original_without_dispatching(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $this->mock(RecordAudit::class)->shouldReceive('execute')->andThrow(new \RuntimeException('synthetic-provider-secret'));
        try {
            $this->upload($owner, $tenant);
            $this->fail('Audit failure must abort the upload.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_versions', 0);
        $this->assertCount(0, Storage::disk('documents')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_upload_requires_its_own_commit_boundary_and_absent_context_is_denied(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        app(TenantContext::class)->clear();
        try {
            app(UploadDocumentVersion::class)->execute($owner, $tenant, null, [], UploadedFile::fake()->createWithContent('fixture.txt', 'Synthetic'));
            $this->fail('Context must be explicit.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('documents', 0);
        }
        DB::beginTransaction();
        try {
            $this->upload($owner, $tenant);
            $this->fail('An enclosing transaction would make filesystem cleanup ambiguous.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('commit boundary', $exception->getMessage());
        } finally {
            DB::rollBack();
        }
        Queue::assertNothingPushed();
        $this->assertCount(0, Storage::disk('documents')->allFiles());
    }

    public function test_postgresql_checks_tenant_foreign_keys_frozen_payloads_and_no_history_delete_or_truncate(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $version = $this->upload($owner, $a);
        $row = (array) DB::table('document_versions')->where('tenant_id', $a->id)->sole();
        $row['id'] = (string) Str::uuid();
        $row['tenant_id'] = $b->id;
        $row['upload_key_hash'] = hash('sha256', random_bytes(32));
        $this->reject(fn () => DB::table('document_versions')->insert($row), '23503');
        $this->reject(fn () => DB::table('document_versions')->where('tenant_id', $a->id)->update(['sha256' => str_repeat('0', 64)]), '23514');
        $this->reject(fn () => DB::table('document_versions')->where('tenant_id', $a->id)->delete(), '23514');
        $this->reject(fn () => DB::statement('TRUNCATE document_versions'), '23514');
        $this->scan($version);
        $this->reject(fn () => DB::table('document_versions')->where('tenant_id', $a->id)->update(['scan_status' => 'quarantined']), '23514');
    }

    public function test_scan_provider_errors_and_unknown_results_are_redacted_and_never_release_bytes(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        foreach (['throw', 'unknown'] as $mode) {
            SyntheticDocumentEnvironment::configure(false);
            $version = $this->upload($owner, $tenant);
            app()->instance(MalwareScanner::class, new class($mode) implements MalwareScanner
            {
                public function __construct(private string $mode) {}

                public function available(): bool
                {
                    return true;
                }

                public function scan($stream): string
                {
                    if ($this->mode === 'throw') {
                        throw new \RuntimeException('synthetic-provider-secret');
                    }

                    return 'unknown';
                }
            });
            try {
                $this->scan($version);
                $this->fail('Non-clean results must fail closed.');
            } catch (\RuntimeException $exception) {
                $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
            $this->assertSame('scan_failed', $version->fresh()->scan_status);
            $this->assertContextEmpty();
        }
    }

    public function test_scan_dispatch_runs_only_after_an_independently_visible_real_commit(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $manager = Queue::getFacadeRoot();
        $test = $this;
        $connection = new class($test) extends SyncQueue
        {
            public function __construct(private DocumentTest $test)
            {
                parent::__construct();
            }

            public function push($job, $data = '', $queue = null): void
            {
                $this->test->assertInstanceOf(ScanDocumentVersion::class, $job);
                $this->test->assertSame(0, DB::transactionLevel());
                config(['database.connections.document_commit_probe' => config('database.connections.pgsql')]);
                $this->test->assertTrue(DB::connection('document_commit_probe')->table('document_versions')->where('tenant_id', $job->tenantId)->where('id', $job->versionId)->exists());
            }
        };
        Queue::shouldReceive('connection')->once()->andReturn($connection);
        try {
            $version = $this->upload($owner, $tenant);
            $this->assertSame('quarantined', $version->fresh()->scan_status);
        } finally {
            Queue::swap($manager);
            DB::purge('document_commit_probe');
        }
    }

    private function reject(callable $operation, string $state): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected PostgreSQL to reject this mutation.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        }
    }

    private function assertContextEmpty(): void
    {
        try {
            app(TenantContext::class)->tenant();
            $this->fail('Tenant context leaked.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
