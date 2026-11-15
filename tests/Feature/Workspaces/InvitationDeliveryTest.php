<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateTeamInvitation;
use App\Modules\IdentityAccess\Actions\ManageTeamInvitation;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Mail\TeamInvitationMail;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class InvitationDeliveryTest extends TestCase
{
    use WorkspaceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        Mail::fake();
    }

    protected function tearDown(): void
    {
        try {
            $this->artisan('migrate:fresh')->assertSuccessful();
        } finally {
            RefreshDatabaseState::$migrated = false;
            parent::tearDown();
        }
    }

    private function setupInvite(): array
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Consulta']);
        DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => Permission::query()->where('code', 'tenant.view')->sole()->id]);
        $proof = $this->proofFor($owner);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());

        return [$owner, $tenant, $role, $proof];
    }

    public function test_mail_waits_for_a_real_commit_and_rollback_dispatches_nothing(): void
    {
        [$owner, $tenant, $role, $proof] = $this->setupInvite();
        DB::beginTransaction();
        app(CreateTeamInvitation::class)->execute($owner, $tenant, 'commit@example.invalid', [['role_id' => $role->id, 'scope' => 'tenant']], 7, $proof);
        Mail::assertNothingSent();
        DB::rollBack();
        $this->assertDatabaseCount('team_invitations', 0);
        $this->assertNull($owner->fresh()->last_sensitive_totp_step);
        DB::beginTransaction();
        $invitation = app(CreateTeamInvitation::class)->execute($owner, $tenant, 'commit@example.invalid', [['role_id' => $role->id, 'scope' => 'tenant']], 7, $proof);
        Mail::assertNothingSent();
        DB::commit();
        Mail::assertSent(TeamInvitationMail::class, 1);
        $this->assertSame('simulated', $invitation->fresh()->delivery_status);
        $this->assertFalse(app(TenantContext::class)->matches($owner, $tenant));
    }

    public function test_queue_dispatch_failure_is_visible_and_resend_recovers_without_duplicate_grants(): void
    {
        [$owner, $tenant, $role, $proof] = $this->setupInvite();
        $manager = Queue::getFacadeRoot();
        Queue::shouldReceive('connection')->once()->andThrow(new \RuntimeException('Synthetic queue failure'));
        try {
            $invitation = app(CreateTeamInvitation::class)->execute($owner, $tenant, 'retry@example.invalid', [['role_id' => $role->id, 'scope' => 'tenant']], 7, $proof);
        } finally {
            Queue::swap($manager);
        }
        $this->assertSame('queue_unavailable', $invitation->failure_code);
        $this->assertSame('failed', $invitation->delivery_status);
        Mail::assertNothingSent();
        $expiry = $invitation->expires_at;
        $hash = $invitation->token_hash;
        $newProof = $this->proofFor($owner);
        app(ManageTeamInvitation::class)->execute($owner, $tenant, $invitation->id, true, $newProof);
        $this->assertSame(2, $invitation->fresh()->delivery_version);
        $this->assertNotSame($hash, $invitation->fresh()->token_hash);
        $this->assertEquals($expiry, $invitation->fresh()->expires_at);
        $this->assertSame('simulated', $invitation->fresh()->delivery_status);
        Mail::assertSent(TeamInvitationMail::class, 1);
        $this->assertSame(1, $tenant->memberships()->count());
    }

    public function test_invitation_schema_upgrades_existing_governance_and_preserves_history_on_rollback(): void
    {
        [$owner, $tenant, $role] = $this->setupInvite();
        $this->artisan('migrate:rollback', ['--step' => 2])->assertSuccessful();
        $this->assertDatabaseCount('permissions', 7);
        $this->artisan('migrate')->assertSuccessful();
        $this->assertDatabaseCount('permissions', count(CapabilityCatalog::OWNER));
        $event = DB::table('audit_events')->where('tenant_id', $tenant->id)->where('action', 'access.invitations.upgraded')->sole();
        $this->assertNull($event->actor_id);
        $this->assertSame('migration', $event->actor_type);
        $proof = $this->proofFor($owner);
        app(CreateTeamInvitation::class)->execute($owner, $tenant, 'history@example.invalid', [['role_id' => $role->id, 'scope' => 'tenant']], 7, $proof);
        try {
            // Exercise F3's own preservation guard; P1 independently protects its upgrade evidence.
            (require database_path('migrations/2026_11_15_000002_add_team_invitations.php'))->down();
            $this->fail('Invitation history must survive.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertDatabaseCount('team_invitations', 1);
    }
}
