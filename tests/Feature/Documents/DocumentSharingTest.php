<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use App\Modules\Documents\Actions\UploadDocumentVersion;
use App\Modules\Documents\Jobs\ScanDocumentVersion;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Actions\AcceptResourceInvitation;
use App\Modules\IdentityAccess\Actions\ShareDocumentVersion;
use App\Modules\IdentityAccess\Jobs\SendResourceInvitation;
use App\Modules\IdentityAccess\Mail\ResourceInvitationMail;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\ResourceInvitationRecipient;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SyntheticDocumentEnvironment;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
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

    private function version(User $owner, Tenant $tenant, ?DocumentVersion $previous = null): DocumentVersion
    {
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $owner->id)->sole());
        $version = app(UploadDocumentVersion::class)->execute($owner, $tenant, $previous?->document_id, [
            'title' => 'Título privado no compartido', 'description' => 'Notas internas no compartidas', 'upload_key' => (string) Str::uuid(), 'expected_version' => $previous?->number,
        ], UploadedFile::fake()->createWithContent('synthetic.txt', 'Synthetic version '.($previous ? 'two' : 'one')));
        app()->call([Queue::pushed(ScanDocumentVersion::class)->first(fn ($job) => $job->versionId === $version->id), 'handle']);

        return $version->fresh();
    }

    private function invitation(User $owner, Tenant $tenant, DocumentVersion $version, User $recipient, string $mode = 'file'): ResourceInvitation
    {
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $owner->id)->sole());

        $proof = $this->proofFor($owner);

        return app(ShareDocumentVersion::class)->invite($owner, $tenant, $version->document_id, $version->id, $recipient->email, $mode, 7, $proof);
    }

    private function proof(ResourceInvitation $invitation): array
    {
        return ['id' => $invitation->id, 'hash' => $invitation->token_hash, 'until' => now()->addHour()->timestamp];
    }

    public function test_external_recipient_gets_only_the_exact_version_without_membership_or_private_parent_data(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $v1 = $this->version($owner, $tenant);
        $v2 = $this->version($owner, $tenant, $v1);
        $recipient = User::factory()->create();
        $other = $this->workspaceFor($recipient, 'Otro espacio propio');
        $invite = $this->invitation($owner, $tenant, $v1, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($invite));
        $this->assertDatabaseCount('resource_grants', 1);
        $this->assertSame(1, $tenant->memberships()->count());
        $this->assertEquals($invite->expires_at, $grant->expires_at);
        $this->actingAs($recipient)->withSession(['current_workspace_id' => $other->id])->withHeaders(['X-Tenant-ID' => $other->id])
            ->get(route('shared-documents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('documents.total', 1)->where('documents.data.0.number', 1));
        $this->get(route('shared-documents.show', $grant))->assertOk()->assertInertia(fn (Assert $p) => $p->component('documents/SharedShow')
            ->where('document.number', 1)->where('document.can_download', true)->missing('document.storage_path')->missing('document.description')
            ->missing('document.title')->missing('document.user_id')->missing('document.source_membership_id'));
        $response = $this->get(route('shared-documents.download', $grant))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('Synthetic version one', $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('documents.show', [$tenant, $v1->document_id]))->assertNotFound();
        $this->get(route('documents.download', [$tenant, $v1->document_id, $v2->id]))->assertNotFound();
        $this->get(route('parties.index', $tenant))->assertNotFound();
        $this->get(route('documents.sharing.index', [$tenant, $v1->document_id]))->assertNotFound();
        $this->actingAs($owner)->get(route('shared-documents.show', $grant))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('shared-documents.download', $grant))->assertNotFound();
    }

    public function test_metadata_only_does_not_grant_file_and_bad_integrity_blocks_authorized_file(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $metadata = $this->invitation($owner, $tenant, $version, $recipient, 'metadata');
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($metadata));
        $this->actingAs($recipient)->get(route('shared-documents.show', $grant))->assertOk()->assertInertia(fn (Assert $p) => $p->where('document.can_download', false));
        $this->get(route('shared-documents.download', $grant))->assertForbidden();
        $file = $this->invitation($owner, $tenant, $version, $recipient);
        $fileGrant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($file));
        Storage::disk('documents')->put($version->storage_path, 'Corrupt synthetic bytes');
        $this->get(route('shared-documents.download', $fileGrant))->assertStatus(409);
        $this->assertSame('blocked', $version->fresh()->scan_status);
    }

    public function test_revocation_and_expiration_remove_access_from_details_downloads_and_counts(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($invite));
        $this->actingAs($owner)->delete(route('documents.sharing.revoke', [$tenant, $version->document_id, $invite]))->assertRedirect();
        $this->actingAs($recipient)->get(route('shared-documents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
        $this->get(route('shared-documents.show', $grant))->assertNotFound();
        $this->get(route('shared-documents.download', $grant))->assertNotFound();
        $next = $this->invitation($owner, $tenant, $version, $recipient);
        $nextGrant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($next));
        $this->travel(8)->days();
        $this->get(route('shared-documents.show', $nextGrant))->assertNotFound();
        $this->get(route('shared-documents.download', $nextGrant))->assertNotFound();
        $this->get(route('shared-documents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
    }

    public function test_source_authority_changes_reject_acceptance_and_existing_grants_without_stale_permissions(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $accepted = $this->invitation($owner, $tenant, $version, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($accepted));
        $pending = $this->invitation($owner, $tenant, $version, $recipient);
        $tenant->memberships()->where('user_id', $owner->id)->increment('access_version');
        $this->actingAs($recipient)->get(route('shared-documents.download', $grant))->assertNotFound();
        $this->get(route('shared-documents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
        $this->expectException(ValidationException::class);
        app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($pending));
    }

    public function test_recipient_membership_revocation_does_not_destroy_an_independent_grant(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $membership = $this->memberFor($tenant, $recipient);
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($invite));
        $membership->update(['status' => 'revoked', 'revoked_at' => now(), 'access_version' => $membership->access_version + 1]);
        $response = $this->actingAs($recipient)->get(route('shared-documents.download', $grant))->assertOk();
        $this->assertSame('Synthetic version one', $response->streamedContent());
        $this->get(route('workspaces.show', $tenant))->assertNotFound();
        $recipient->forceFill(['status' => 'suspended'])->save();
        $this->get(route('shared-documents.download', $grant))->assertForbidden();
    }

    public function test_token_proof_wrong_recipient_replay_rotation_and_anonymous_get(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $this->get(route('resource-invitations.landing', $invite))->assertOk()->assertInertia(fn (Assert $p) => $p->component('invitations/ResourceClaim')->missing('invitation'));
        $this->assertSame('pending', $invite->fresh()->status);
        $wrong = User::factory()->create();
        $this->actingAs($wrong)->withSession(['resource_invitation_proof' => $this->proof($invite)])->post(route('resource-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $proof = $this->proof($invite);
        $this->actingAs($owner)->post(route('documents.sharing.resend', [$tenant, $version->document_id, $invite]))->assertRedirect();
        $this->assertNotSame($proof['hash'], $invite->fresh()->token_hash);
        $this->actingAs($recipient)->withSession(['resource_invitation_proof' => $proof])->post(route('resource-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $newProof = $this->proof($invite->fresh());
        $this->withSession(['resource_invitation_proof' => $newProof])->get(route('resource-invitations.review'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('invitations/ResourceReview')->where('invitation.number', 1)->missing('invitation.token_hash'));
        $this->post(route('resource-invitations.accept'), ['confirmation' => true])->assertRedirect();
        $this->assertDatabaseCount('resource_grants', 1);
        $this->withSession(['resource_invitation_proof' => $newProof])->post(route('resource-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertDatabaseCount('resource_grants', 1);
        $audit = DB::table('audit_events')->get()->toJson();
        $this->assertStringNotContainsString($proof['hash'], $audit);
        $this->assertStringNotContainsString($recipient->email, $audit);
    }

    public function test_sharing_requires_explicit_capability_delegation_mfa_and_same_tenant_target(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $va = $this->version($owner, $a);
        $vb = $this->version($owner, $b);
        $operator = User::factory()->create();
        $this->memberFor($a, $operator, ['tenant.view', 'documents.view', 'documents.download', 'documents.share']);
        $this->actingAs($operator)->get(route('documents.sharing.index', [$a, $va->document_id]))->assertForbidden();
        $data = ['recipient_email' => 'synthetic@example.invalid', 'projection' => 'file', 'days' => 7, 'password' => 'password', 'code' => '123456'];
        $this->post(route('documents.sharing.store', [$a, $va->document_id, $va->id]), $data)->assertForbidden();
        $this->actingAs($owner)->post(route('documents.sharing.store', [$a, $va->document_id, $vb->id]), $data)->assertNotFound();
        $this->post(route('documents.sharing.store', [$a, $va->document_id, $va->id]), $data)->assertSessionHasErrors();
        $this->post(route('documents.sharing.store', [$a, $va->document_id, $va->id]), $data + ['tenant_id' => $b->id])->assertSessionHasErrors('tenant_id');
        $this->get(route('documents.sharing.index', [$a, $va->document_id]))->assertOk()->assertInertia(fn (Assert $p) => $p->component('documents/Sharing')->where('canShareFile', true)->where('invitations.total', 0));
        $this->assertDatabaseCount('resource_invitations', 0);
    }

    public function test_typed_foreign_keys_projection_and_history_are_enforced_by_postgresql(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $version = $this->version($owner, $a);
        $recipient = User::factory()->create();
        $invite = $this->invitation($owner, $a, $version, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($invite));
        $base = $grant->getRawOriginal();
        unset($base['id']);
        $attempts = [
            fn () => ResourceGrant::query()->create(array_merge($base, ['tenant_id' => $b->id])),
            fn () => ResourceGrant::query()->create(array_merge($base, ['document_version_id' => null])),
            fn () => ResourceGrant::query()->whereKey($grant->id)->update(['projection' => 'metadata']),
            fn () => ResourceGrant::query()->whereKey($grant->id)->update(['user_id' => $owner->id]),
            fn () => ResourceGrant::query()->whereKey($grant->id)->delete(),
            fn () => ResourceInvitation::query()->whereKey($invite->id)->update(['status' => 'pending']),
        ];
        foreach ($attempts as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('Invalid resource access mutation was accepted.');
            } catch (QueryException $exception) {
                $this->assertStringStartsWith('23', $exception->errorInfo[0]);
            }
        }
        $this->assertDatabaseCount('resource_grants', 1);
        $proposal = $invite->getRawOriginal();
        unset($proposal['id']);
        $proposal['status'] = 'pending';
        $proposal['accepted_at'] = null;
        $proposal['accepted_by_id'] = null;
        foreach ([['tenant_id' => $b->id], ['projection' => 'metadata'], ['document_version_id' => null]] as $invalid) {
            try {
                DB::transaction(fn () => ResourceInvitation::query()->create(array_merge($proposal, $invalid)));
                $this->fail('Typed target or projection constraints were not enforced.');
            } catch (QueryException $exception) {
                $this->assertSame(isset($invalid['tenant_id']) ? '23503' : (array_key_exists('document_version_id', $invalid) ? '23502' : '23514'), $exception->errorInfo[0]);
            }
        }
    }

    public function test_new_account_claim_survives_login_and_requires_verified_exact_recipient(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->unverified()->create();
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $job = Queue::pushed(SendResourceInvitation::class)->sole();
        $token = (new \ReflectionProperty($job, 'token'))->getValue($job);
        $this->assertSame(hash('sha256', $token), $invite->token_hash);
        $this->post(route('resource-invitations.claim', $invite), ['token' => $token])->assertRedirect(route('resource-invitations.review'));
        $this->assertSame($invite->token_hash, session('resource_invitation_proof.hash'));
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->get(route('resource-invitations.review'))->assertRedirect(route('login'));
        $this->actingAs($recipient)->get(route('resource-invitations.review'))->assertRedirect(route('verification.notice'));
        $this->post(route('resource-invitations.accept'), ['confirmation' => true])->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('resource_grants', 0);
        $recipient->forceFill(['email_verified_at' => now()])->save();
        $this->get(route('resource-invitations.review'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('invitation.filename', 'synthetic.txt'));
        $this->post(route('resource-invitations.accept'), ['confirmation' => true])->assertRedirect();
        $this->assertDatabaseCount('resource_grants', 1);
        $this->assertDatabaseCount('tenant_memberships', 1);
    }

    public function test_delivery_waits_for_commit_is_idempotent_and_revalidates_source_with_context_cleanup(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        DB::beginTransaction();
        $this->invitation($owner, $tenant, $version, $recipient);
        Queue::assertNotPushed(SendResourceInvitation::class);
        DB::rollBack();
        $this->assertDatabaseCount('resource_invitations', 0);
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        Queue::assertPushed(SendResourceInvitation::class, 1);
        Mail::fake();
        $job = Queue::pushed(SendResourceInvitation::class)->sole();
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Mail::assertSent(ResourceInvitationMail::class, 1);
        $this->assertSame('simulated', $invite->fresh()->delivery_status);
        $next = $this->invitation($owner, $tenant, $version, $recipient);
        $nextJob = Queue::pushed(SendResourceInvitation::class)->last();
        $tenant->memberships()->where('user_id', $owner->id)->increment('access_version');
        app()->call([$nextJob, 'handle']);
        $this->assertSame('authority_changed', $next->fresh()->failure_code);
        Mail::assertSent(ResourceInvitationMail::class, 1);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
    }

    public function test_delivery_error_is_redacted_retryable_and_old_generation_cannot_send(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $old = Queue::pushed(SendResourceInvitation::class)->sole();
        $mailManager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('synthetic-provider-secret'));
        try {
            app()->call([$old, 'handle']);
            $this->fail('Delivery failure must be visible.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
        }
        $this->assertSame('failed', $invite->fresh()->delivery_status);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
        Mail::swap($mailManager);
        Mail::fake();
        $this->actingAs($owner)->post(route('documents.sharing.resend', [$tenant, $version->document_id, $invite]))->assertRedirect();
        app()->call([$old, 'handle']);
        $old->failed(new \RuntimeException('synthetic-provider-secret'));
        $this->assertSame('pending', $invite->fresh()->delivery_status);
        Mail::assertNothingSent();
        app()->call([Queue::pushed(SendResourceInvitation::class)->last(), 'handle']);
        $this->assertSame('simulated', $invite->fresh()->delivery_status);
        Mail::assertSent(ResourceInvitationMail::class, 1);
    }

    public function test_withdrawn_delegation_without_epoch_change_also_removes_grant_access(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $version = $this->version($owner, $tenant);
        $recipient = User::factory()->create();
        $invite = $this->invitation($owner, $tenant, $version, $recipient);
        $grant = app(AcceptResourceInvitation::class)->execute($recipient, $this->proof($invite));
        $pending = $this->invitation($owner, $tenant, $version, $recipient);
        $permission = DB::table('permissions')->where('code', 'documents.download')->value('id');
        DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('permission_id', $permission)->update(['is_delegable' => false]);
        $this->actingAs($recipient)->get(route('shared-documents.download', $grant))->assertNotFound();
        $this->get(route('shared-documents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('documents.total', 0));
        $this->expectException(ValidationException::class);
        app(ResourceInvitationRecipient::class)->find($this->proof($pending), $recipient);
    }
}
