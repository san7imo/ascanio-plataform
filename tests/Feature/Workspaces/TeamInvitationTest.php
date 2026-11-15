<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateTeamInvitation;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Jobs\SendTeamInvitation;
use App\Modules\IdentityAccess\Mail\TeamInvitationMail;
use App\Modules\IdentityAccess\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase, WorkspaceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function role(Tenant $tenant): Role
    {
        $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Consulta del equipo']);
        DB::table('role_permissions')->insert([
            'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'permission_id' => Permission::query()->where('code', 'tenant.view')->sole()->id, 'is_delegable' => false,
        ]);

        return $role;
    }

    private function invitation(User $owner, Tenant $tenant, string $email, string $scope = 'tenant'): array
    {
        $role = $this->role($tenant);
        $proof = $this->proofFor($owner);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $owner->id)->sole());
        $invitation = app(CreateTeamInvitation::class)->execute($owner, $tenant, $email, [['role_id' => $role->id, 'scope' => $scope]], 7, $proof);
        $mail = Mail::sent(TeamInvitationMail::class)->last();
        $this->assertNotNull($mail, 'Expected invitation mail after the mutation commit.');
        preg_match('/#token=([a-f0-9]{64})/', html_entity_decode($mail->render()), $matches);
        $this->assertArrayHasKey(1, $matches);

        return [$invitation, $matches[1], $role];
    }

    private function proof(TeamInvitation $invitation, string $token): array
    {
        return ['id' => $invitation->id, 'hash' => hash('sha256', $token), 'until' => now()->addHour()->timestamp];
    }

    public function test_invitation_stores_only_a_hash_and_mail_uses_fragment_with_no_shared_token(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $token] = $this->invitation($owner, $tenant, 'future@example.invalid');
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame('simulated', $invitation->delivery_status);
        $this->assertDatabaseMissing('users', ['email' => 'future@example.invalid']);
        $this->assertStringNotContainsString($token, $invitation->toJson());
        $this->assertStringNotContainsString('token_hash', $invitation->toJson());
        $this->assertStringNotContainsString('future@example.invalid', AuditEvent::query()->get()->toJson());
        $this->assertStringNotContainsString($token, AuditEvent::query()->get()->toJson());
        $this->get(route('team-invitations.landing', $invitation))->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('invitations/Claim')->where('invitationId', $invitation->id)->missing('token')->missing('workspace')->missing('recipient_email'));
        $this->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertRedirect(route('team-invitations.review'));
        $this->assertSame($this->proof($invitation, $token)['hash'], session('team_invitation_proof.hash'));
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->get(route('team-invitations.review'))->assertRedirect(route('login'));
    }

    public function test_existing_verified_account_in_another_workspace_accepts_exactly_the_invited_roles(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        $independent = $this->workspaceFor($recipient);
        [$invitation, $token, $role] = $this->invitation($owner, $tenant, $recipient->email);
        $this->actingAs($recipient)->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertRedirect();
        $this->get(route('team-invitations.review'))->assertInertia(fn (Assert $page) => $page->component('invitations/Review')
            ->where('invitation.available', true)->where('invitation.workspace', $tenant->name)->has('invitation.roles', 1)
            ->missing('invitation.token')->missing('invitation.inviter_id')->missing('invitation.recipient_email'));
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertRedirect(route('workspaces.show', $tenant));
        $member = $tenant->memberships()->where('user_id', $recipient->id)->sole();
        $this->assertFalse($member->is_owner);
        $this->assertDatabaseHas('membership_roles', ['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => 'tenant']);
        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertSame($recipient->id, $invitation->fresh()->accepted_by_id);
        $this->assertNull(session('team_invitation_proof'));
        $this->get(route('workspaces.show', $independent))->assertOk();
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertSame(1, AuditEvent::query()->where('tenant_id', $tenant->id)->where('action', 'invitation.accepted')->count());
    }

    public function test_new_account_claim_survives_registration_and_email_verification_without_claiming_identity(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $token] = $this->invitation($owner, $tenant, 'new-account@example.invalid');
        $this->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertRedirect();
        $this->get(route('team-invitations.review'))->assertRedirect(route('login'));
        $this->post(route('register.store'), ['name' => 'Nueva cuenta sintética', 'email' => 'new-account@example.invalid', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasNoErrors();
        $recipient = User::query()->where('email', 'new-account@example.invalid')->sole();
        $this->get(route('team-invitations.review'))->assertRedirect(route('verification.notice'));
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertRedirect(route('verification.notice'));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $recipient->id, 'hash' => sha1($recipient->email)]);
        $this->get($url)->assertRedirect();
        $this->get(route('team-invitations.review'))->assertInertia(fn (Assert $page) => $page->where('invitation.available', true));
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasNoErrors();
        $this->assertNull($recipient->fresh()->person_id);
        $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $recipient->id, 'is_owner' => false]);
    }

    public function test_wrong_recipient_and_missing_or_forged_proof_do_not_disclose_or_link_accounts(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner, 'Private workspace');
        [$invitation, $token] = $this->invitation($owner, $tenant, 'intended@example.invalid');
        $wrong = User::factory()->create();
        $this->actingAs($wrong)->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertSessionHasErrors('invitation');
        $this->assertNull(session('team_invitation_proof'));
        $this->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->get(route('team-invitations.review'))->assertInertia(fn (Assert $page) => $page->where('invitation', ['available' => false]));
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->withSession(['team_invitation_proof' => []])->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertSame(1, $tenant->memberships()->count());
        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_expired_revoked_rotated_and_replayed_tokens_fail_without_creating_memberships(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token] = $this->invitation($owner, $tenant, $recipient->email);
        $this->actingAs($owner)->post(route('workspaces.invitations.resend', [$tenant, $invitation]), $this->sensitiveData($owner))->assertSessionHasNoErrors();
        $this->actingAs($recipient)->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertSessionHasErrors('invitation');
        $this->assertStringNotContainsString($token, json_encode(session()->getOldInput()));
        preg_match('/#token=([a-f0-9]{64})/', html_entity_decode(Mail::sent(TeamInvitationMail::class)->last()->render()), $matches);
        $newToken = $matches[1];
        $this->assertNotSame($token, $newToken);
        $this->post(route('team-invitations.claim', $invitation), ['token' => $newToken])->assertSessionHasNoErrors();
        $invitation->fresh()->update(['expires_at' => now()->subSecond()]);
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $invitation->fresh()->update(['expires_at' => now()->addDay()]);
        $this->actingAs($owner)->delete(route('workspaces.invitations.revoke', [$tenant, $invitation]), $this->sensitiveData($owner))->assertSessionHasNoErrors();
        $this->actingAs($recipient)->post(route('team-invitations.claim', $invitation), ['token' => $newToken])->assertSessionHasErrors('invitation');
        $this->assertSame(1, $tenant->memberships()->count());
    }

    public function test_roles_changing_or_disappearing_and_inviter_authority_revocation_block_acceptance(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token, $role] = $this->invitation($owner, $tenant, $recipient->email);
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)]);
        DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->update(['is_delegable' => true]);
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->update(['is_delegable' => false]);
        DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('permission_id', Permission::query()->where('code', 'tenant.members.invite')->sole()->id)->delete();
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertForbidden();
        $this->assertSame(1, $tenant->memberships()->count());
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
    }

    public function test_invitation_cannot_reactivate_revoked_members_or_mutate_protected_owner(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        $member = $this->memberFor($tenant, $recipient);
        $member->update(['status' => 'revoked', 'revoked_at' => now()]);
        [$invitation, $token] = $this->invitation($owner, $tenant, $recipient->email);
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertSame('revoked', $member->fresh()->status);
        [$ownerInvite, $ownerToken] = $this->invitation($owner, $tenant, $owner->email);
        $this->actingAs($owner)->withSession(['team_invitation_proof' => $this->proof($ownerInvite, $ownerToken)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertTrue($tenant->memberships()->where('user_id', $owner->id)->sole()->is_owner);
    }

    public function test_assigned_resources_scope_does_not_turn_into_tenant_access_and_existing_roles_are_preserved(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token, $role] = $this->invitation($owner, $tenant, $recipient->email, 'assigned_resources');
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertRedirect(route('workspaces.index'));
        $this->get(route('workspaces.show', $tenant))->assertNotFound();
        [$second, $secondToken] = $this->invitation($owner, $tenant, $recipient->email);
        $this->withSession(['team_invitation_proof' => $this->proof($second, $secondToken)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertRedirect(route('workspaces.show', $tenant));
        $member = $tenant->memberships()->where('user_id', $recipient->id)->sole();
        $this->assertDatabaseHas('membership_roles', ['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => 'assigned_resources']);
        $this->assertSame(2, DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->count());
    }

    public function test_list_search_counts_metadata_and_mutations_are_tenant_bounded(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner, 'A');
        $b = $this->workspaceFor($owner, 'B');
        [$local, $token] = $this->invitation($owner, $a, 'local@example.invalid');
        [$outside] = $this->invitation($owner, $b, 'private-b@example.invalid');
        $this->actingAs($owner)->get(route('workspaces.invitations', $a))->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/Invitations')->where('invitations.total', 1)->has('invitations.data', 1)
            ->missing('invitations.data.0.token_hash')->missing('invitations.data.0.token')->missing('invitations.data.0.inviter_id'));
        $this->get(route('workspaces.invitations', [$a, 'search' => 'private-b', 'page' => 2]))->assertInertia(fn (Assert $page) => $page->where('invitations.total', 0)->has('invitations.data', 0));
        $this->delete(route('workspaces.invitations.revoke', [$a, $outside]), $this->sensitiveData($owner))->assertNotFound();
        $this->post(route('workspaces.invitations.resend', [$a, $outside]), $this->sensitiveData($owner))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('workspaces.invitations', $a))->assertNotFound();
        $this->assertStringNotContainsString($token, AuditEvent::query()->get()->toJson());
    }

    public function test_request_requires_mfa_and_delegation_and_rejects_privileged_or_cross_tenant_assignments(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $role = $this->role($a);
        $outsideRole = $this->role($b);
        $base = ['recipient_email' => 'team@example.invalid', 'days' => 7, 'assignments' => [['role_id' => $role->id, 'scope' => 'tenant']]];
        $this->actingAs($owner)->post(route('workspaces.invitations.store', $a), [...$base, 'password' => 'password'])->assertSessionHasErrors('code');
        $this->post(route('workspaces.invitations.store', $a), [...$base, ...$this->sensitiveData($owner), 'tenant_id' => $b->id, 'is_owner' => true])->assertSessionHasErrors(['tenant_id', 'is_owner']);
        $this->post(route('workspaces.invitations.store', $a), [...$base, ...$this->sensitiveData($owner), 'assignments' => [['role_id' => $outsideRole->id, 'scope' => 'tenant']]])->assertNotFound();
        $protected = Role::query()->where('tenant_id', $a->id)->where('system_kind', 'owner')->sole();
        $this->post(route('workspaces.invitations.store', $a), [...$base, ...$this->sensitiveData($owner), 'assignments' => [['role_id' => $protected->id, 'scope' => 'tenant']]])->assertForbidden();
        $limited = User::factory()->create();
        $this->memberFor($a, $limited, ['tenant.view', 'tenant.members.invite', 'tenant.members.assign_roles'], delegable: false);
        $this->actingAs($limited)->post(route('workspaces.invitations.store', $a), [...$base, ...$this->sensitiveData($limited)])->assertSessionHasErrors('permissions');
        $this->assertDatabaseCount('team_invitations', 0);
    }

    public function test_audit_failure_rolls_back_invitation_mfa_consumption_and_prevents_mail(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $role = $this->role($tenant);
        $data = [...$this->sensitiveData($owner), 'recipient_email' => 'team@example.invalid', 'days' => 7, 'assignments' => [['role_id' => $role->id, 'scope' => 'tenant']]];
        $this->mock(RecordAudit::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->actingAs($owner)->withoutExceptionHandling();
        try {
            $this->post(route('workspaces.invitations.store', $tenant), $data);
            $this->fail('Invitation must roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('team_invitations', 0);
        $this->assertDatabaseCount('team_invitation_roles', 0);
        $this->assertNull($owner->fresh()->last_sensitive_totp_step);
        Mail::assertNothingSent();
    }

    public function test_acceptance_failure_rolls_back_consumption_membership_and_role_links(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token] = $this->invitation($owner, $tenant, $recipient->email);
        $this->mock(RecordAudit::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Synthetic accept failure'));
        $this->actingAs($recipient)->withoutExceptionHandling();
        try {
            $this->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])->post(route('team-invitations.accept'), ['confirmation' => true]);
            $this->fail('Acceptance must roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic accept failure', $exception->getMessage());
        }
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertSame(1, $tenant->memberships()->count());
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
    }

    public function test_deleted_role_preserves_snapshot_but_prevents_acceptance(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token, $role] = $this->invitation($owner, $tenant, $recipient->email);
        $snapshot = DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('invitation_id', $invitation->id)->sole()->snapshot;
        $this->actingAs($owner)->delete(route('workspaces.roles.destroy', [$tenant, $role]), $this->sensitiveData($owner))->assertSessionHasNoErrors();
        $row = DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('invitation_id', $invitation->id)->sole();
        $this->assertNull($row->role_id);
        $this->assertSame($snapshot, $row->snapshot);
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
    }

    public function test_job_rechecks_authority_clears_context_and_ignores_duplicate_or_obsolete_delivery(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $token] = $this->invitation($owner, $tenant, 'target@example.invalid');
        $job = new SendTeamInvitation($tenant->id, $invitation->id, $owner->id, 1, $token);
        app()->call([$job, 'handle']);
        Mail::assertSent(TeamInvitationMail::class, 1);
        $invitation->fresh()->update(['delivery_status' => 'pending', 'delivered_at' => null]);
        $tenant->memberships()->where('user_id', $owner->id)->update(['status' => 'active']);
        DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('permission_id', Permission::query()->where('code', 'tenant.members.invite')->sole()->id)->delete();
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());
        app()->call([$job, 'handle']);
        $this->assertSame('authority_changed', $invitation->fresh()->failure_code);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
        Mail::assertSent(TeamInvitationMail::class, 1);
    }

    public function test_log_transport_is_blocked_and_delivery_failures_are_visible_without_secrets(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $token] = $this->invitation($owner, $tenant, 'target@example.invalid');
        $invitation->update(['delivery_status' => 'pending', 'delivered_at' => null]);
        config(['mail.default' => 'log']);
        $job = new SendTeamInvitation($tenant->id, $invitation->id, $owner->id, 1, $token);
        app()->call([$job, 'handle']);
        $this->assertSame('unsupported_transport', $invitation->fresh()->failure_code);
        Mail::assertSent(TeamInvitationMail::class, 1);
        config(['mail.default' => 'array']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Sensitive provider payload '.$token));
        try {
            app()->call([$job, 'handle']);
            $this->fail('Transport failure should signal retry.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString($token, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame('failed', $invitation->fresh()->delivery_status);
        $this->assertSame('delivery_failed', $invitation->fresh()->failure_code);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
    }

    public function test_encrypted_queue_payload_does_not_expose_the_token(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        [$invitation, $token] = $this->invitation($owner, $tenant, 'target@example.invalid');
        $job = new SendTeamInvitation($tenant->id, $invitation->id, $owner->id, 1, $token);
        $queue = 'tenant:'.$tenant->id.':invitation-test:'.Str::uuid();
        try {
            Queue::connection('redis')->push($job, '', $queue);
            $payload = Redis::lindex('queues:'.$queue, 0);
            $this->assertIsString($payload);
            $this->assertStringNotContainsString($token, $payload);
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            $decrypted = decrypt($decoded['data']['command']);
            $this->assertInstanceOf(SendTeamInvitation::class, unserialize($decrypted));
        } finally {
            Queue::connection('redis')->clear($queue);
        }
    }

    public function test_revocation_then_reactivation_does_not_restore_old_invitation_authority(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $admin = User::factory()->create();
        $source = $this->memberFor($tenant, $admin, ['tenant.view', 'tenant.members.invite', 'tenant.members.assign_roles'], delegable: true);
        $recipient = User::factory()->create();
        [$invitation, $token] = $this->invitation($admin, $tenant, $recipient->email);
        foreach (['revoked', 'active'] as $state) {
            $this->actingAs($owner)->patch(route('workspaces.members.status', [$tenant, $source]), [...$this->sensitiveData($owner), 'status' => $state])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $source->fresh()->access_version);
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertForbidden();
        $this->actingAs($admin)->post(route('workspaces.invitations.resend', [$tenant, $invitation]), $this->sensitiveData($admin))->assertSessionHasErrors('invitation');
        $this->assertDatabaseMissing('tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $recipient->id]);
    }

    public function test_role_authority_withdrawal_then_restoration_invalidates_old_invitation(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $admin = User::factory()->create();
        $source = $this->memberFor($tenant, $admin, ['tenant.view', 'tenant.members.invite', 'tenant.members.assign_roles'], delegable: true);
        $recipient = User::factory()->create();
        [$invitation, $token] = $this->invitation($admin, $tenant, $recipient->email);
        $roleId = DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $source->id)->sole()->role_id;
        foreach ([['tenant.view', 'tenant.members.assign_roles'], ['tenant.view', 'tenant.members.assign_roles', 'tenant.members.invite']] as $codes) {
            $this->actingAs($owner)->patch(route('workspaces.roles.update', [$tenant, $roleId]), [
                ...$this->sensitiveData($owner), 'name' => 'Administración acotada',
                'permissions' => array_map(fn ($code) => ['code' => $code, 'delegable' => true], $codes),
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $source->fresh()->access_version);
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $this->proof($invitation, $token)])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertForbidden();
    }

    public function test_claim_rate_limit_and_expired_session_proof_fail_closed_without_flashing_tokens(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        [$invitation, $token] = $this->invitation($owner, $tenant, $recipient->email);
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('team-invitations.claim', $invitation), ['token' => str_repeat('0', 64)])->assertSessionHasErrors('invitation');
        }
        $this->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertStatus(429);
        $proof = $this->proof($invitation, $token);
        $proof['until'] = now()->subSecond()->timestamp;
        $this->actingAs($recipient)->withSession(['team_invitation_proof' => $proof])
            ->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasErrors('invitation');
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertArrayNotHasKey('token', session()->getOldInput());
    }

    public function test_composite_foreign_keys_reject_cross_tenant_invitation_roles(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        [$invitation] = $this->invitation($owner, $a, 'foreign-key@example.invalid');
        $other = $this->role($b);
        try {
            DB::transaction(fn () => DB::table('team_invitation_roles')->where('tenant_id', $a->id)->where('invitation_id', $invitation->id)->update(['role_id' => $other->id]));
            $this->fail('Composite FK must reject a foreign role.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0]);
        }
    }

    public function test_existing_unverified_recipient_can_keep_claim_while_verifying_but_cannot_accept(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->unverified()->create();
        [$invitation, $token] = $this->invitation($owner, $tenant, $recipient->email);
        $this->actingAs($recipient)->post(route('team-invitations.claim', $invitation), ['token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('team-invitations.review'));
        $this->get(route('team-invitations.review'))->assertRedirect(route('verification.notice'));
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertRedirect(route('verification.notice'));
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseMissing('tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $recipient->id]);
        $recipient->forceFill(['email_verified_at' => now()])->save();
        $this->post(route('team-invitations.accept'), ['confirmation' => true])->assertSessionHasNoErrors()->assertRedirect(route('workspaces.show', $tenant));
    }
}
