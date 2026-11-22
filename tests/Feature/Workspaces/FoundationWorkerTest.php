<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\Documents\Actions\UploadDocumentVersion;
use App\Modules\Documents\Jobs\ScanDocumentVersion;
use App\Modules\IdentityAccess\Actions\ChangeMembershipStatus;
use App\Modules\IdentityAccess\Actions\CreateTeamInvitation;
use App\Modules\IdentityAccess\Jobs\SendAccountEmail;
use App\Modules\IdentityAccess\Jobs\SendTeamInvitation;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FoundationContextProbe;
use Tests\Support\SyntheticDocumentEnvironment;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class FoundationWorkerTest extends TestCase
{
    use WorkspaceFixtures;

    private string $queue;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        $this->queue = 'foundation-test-'.Str::uuid();
        $this->key = 'foundation-test:'.Str::uuid();
    }

    protected function tearDown(): void
    {
        try {
            Queue::connection('redis')->clear($this->queue);
            Redis::del($this->key);
            $this->artisan('migrate:fresh')->assertSuccessful();
        } finally {
            RefreshDatabaseState::$migrated = false;
            parent::tearDown();
        }
    }

    private function captureInvitation(User $actor, Tenant $tenant): array
    {
        $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Consulta']);
        DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => Permission::where('code', 'tenant.view')->sole()->id]);
        $manager = Queue::getFacadeRoot();
        Queue::fake();
        try {
            app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $actor->id)->sole());
            $proof = $this->proofFor($actor);
            $invitation = app(CreateTeamInvitation::class)->execute($actor, $tenant, Str::uuid().'@example.invalid', [['role_id' => $role->id, 'scope' => 'tenant']], 7, $proof);
            $job = Queue::pushed(SendTeamInvitation::class)->sole();
        } finally {
            Queue::swap($manager);
            app(TenantContext::class)->clear();
        }

        return [$invitation, $job];
    }

    private function runWorker(string $mode = 'normal'): array
    {
        $connection = config('database.connections.pgsql');
        $process = new Process([PHP_BINARY, base_path('tests/Support/foundation-worker.php'), $this->queue, $this->key, $mode], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'ascanio_test', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ], timeout: 30);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $events = array_map(static fn ($event) => json_decode($event, true, flags: JSON_THROW_ON_ERROR), Redis::lrange($this->key, 0, -1));
        $this->assertCount(1, array_unique(array_column($events, 'pid')), 'All jobs must run in the same daemon process.');
        foreach ($events as $event) {
            if ($event['phase'] !== 'summary') {
                $this->assertTrue($event['empty'], 'Context leaked at '.$event['phase']);
            }
        }
        $this->assertSame(0, Queue::connection('redis')->size($this->queue), $process->getOutput());

        return $events;
    }

    public function test_one_worker_revalidates_revocation_and_clears_context_across_tenants_global_mail_and_failure(): void
    {
        $ownerA = User::factory()->create();
        $tenantA = $this->workspaceFor($ownerA, 'A');
        [$inviteA, $jobA] = $this->captureInvitation($ownerA, $tenantA);
        $ownerB = User::factory()->create();
        $tenantB = $this->workspaceFor($ownerB, 'B');
        $delegate = User::factory()->create();
        $membership = $this->memberFor($tenantB, $delegate, ['tenant.view', 'tenant.members.invite', 'tenant.members.assign_roles'], delegable: true);
        [$revokedInvite, $revokedJob] = $this->captureInvitation($delegate, $tenantB);
        [$inviteB, $jobB] = $this->captureInvitation($ownerB, $tenantB);
        app(TenantContext::class)->establish($tenantB, $tenantB->memberships()->where('user_id', $ownerB->id)->sole());
        $proof = $this->proofFor($ownerB);
        app(ChangeMembershipStatus::class)->execute($ownerB, $tenantB, $membership->id, 'revoked', $proof);
        app(TenantContext::class)->clear();

        $account = User::factory()->unverified()->create();
        $accountJob = new SendAccountEmail($account->id, 'verify', hash('sha256', $account->email));
        foreach ([new FoundationContextProbe($tenantA->id, $this->key, true), $jobA,
            new FoundationContextProbe($tenantA->id, $this->key), $revokedJob,
            new FoundationContextProbe($tenantB->id, $this->key), $accountJob, $jobB] as $job) {
            Queue::connection('redis')->push($job, '', $this->queue);
        }
        $events = $this->runWorker();
        $summary = end($events);
        $this->assertSame(2, $summary['invitations']);
        $this->assertSame(1, $summary['accounts']);
        $this->assertSame(7, count(array_filter($events, static fn ($event) => $event['phase'] === 'before')));
        $this->assertContains('exception', array_column($events, 'phase'));
        $this->assertContains('failed', array_column($events, 'phase'));
        $this->assertSame('simulated', $inviteA->fresh()->delivery_status);
        $this->assertSame('simulated', $inviteB->fresh()->delivery_status);
        $this->assertSame('blocked', $revokedInvite->fresh()->delivery_status);
        $this->assertSame('authority_changed', $revokedInvite->fresh()->failure_code);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(1, $tenantA->memberships()->count());
        $this->assertSame(2, $tenantB->memberships()->count());
    }

    public function test_real_shared_worker_scans_versions_for_two_tenants_and_blocks_revoked_upload_authority(): void
    {
        SyntheticDocumentEnvironment::configure();
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner, 'Document A');
        $b = $this->workspaceFor($owner, 'Document B');
        $delegate = User::factory()->create();
        $membership = $this->memberFor($b, $delegate, ['tenant.view', 'documents.view', 'documents.upload']);
        $manager = Queue::getFacadeRoot();
        Queue::fake();
        $versions = [];
        try {
            foreach ([[$owner, $a], [$delegate, $b], [$owner, $b]] as [$actor, $tenant]) {
                app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $actor->id)->sole());
                $versions[] = app(UploadDocumentVersion::class)->execute($actor, $tenant, null, [
                    'title' => 'Synthetic queued document', 'upload_key' => (string) Str::uuid(),
                ], UploadedFile::fake()->createWithContent('worker.txt', 'Synthetic worker bytes'));
            }
            $jobs = Queue::pushed(ScanDocumentVersion::class)->all();
        } finally {
            Queue::swap($manager);
            app(TenantContext::class)->clear();
        }
        try {
            $proof = $this->proofFor($owner);
            app(TenantContext::class)->establish($b, $b->memberships()->where('user_id', $owner->id)->sole());
            app(ChangeMembershipStatus::class)->execute($owner, $b, $membership->id, 'revoked', $proof);
            app(TenantContext::class)->clear();
            foreach ([new FoundationContextProbe($a->id, $this->key, true), $jobs[0], new FoundationContextProbe($b->id, $this->key), $jobs[1], $jobs[2]] as $job) {
                Queue::connection('redis')->push($job, '', $this->queue);
            }
            $this->runWorker('document-scans');
            $this->assertSame('clean', $versions[0]->fresh()->scan_status);
            $this->assertSame('blocked', $versions[1]->fresh()->scan_status);
            $this->assertSame('authority_revoked', $versions[1]->fresh()->failure_code);
            $this->assertSame('clean', $versions[2]->fresh()->scan_status);
        } finally {
            Storage::disk('documents')->deleteDirectory('tenants');
        }
    }

    public function test_provider_failure_is_redacted_and_the_next_tenant_job_runs_without_context(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $job] = $this->captureInvitation($owner, $tenant);
        $job->tries = 1; // Exercise exhaustion without waiting production backoff.
        Queue::connection('redis')->push($job, '', $this->queue);
        Queue::connection('redis')->push(new FoundationContextProbe($tenant->id, $this->key), '', $this->queue);
        $this->runWorker('mail-failure');
        $this->assertSame('failed', $invitation->fresh()->delivery_status);
        $this->assertSame('attempts_exhausted', $invitation->fresh()->failure_code);
        $failure = DB::table('failed_jobs')->sole();
        $this->assertStringNotContainsString('synthetic-provider-secret', $failure->exception);
        $payload = json_decode($failure->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($invitation->recipient_email, $payload['data']['command']);
        $this->assertStringNotContainsString('SendTeamInvitation', $payload['data']['command']);
    }
}
