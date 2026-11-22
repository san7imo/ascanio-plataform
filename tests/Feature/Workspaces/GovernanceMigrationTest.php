<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\CapabilityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class GovernanceMigrationTest extends TestCase
{
    use WorkspaceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
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

    public function test_f1_upgrade_preserves_evidence_and_ownership_with_two_tenants(): void
    {
        $first = User::factory()->create();
        $a = $this->workspaceFor($first);
        $second = User::factory()->create();
        $b = $this->workspaceFor($second);
        $this->memberFor($a, $second, owner: true);
        // Reconstruct the exact F1 schema from the current fixture, then upgrade.
        $this->artisan('migrate:rollback', ['--step' => 5])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('roles', 'system_kind'));
        $this->assertDatabaseCount('permissions', 4);
        $old = DB::table('audit_events')->orderBy('id')->get()->toJson();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_15_000001_add_workspace_governance.php'])->assertSuccessful();
        $fields = ['id', 'tenant_id', 'actor_id', 'action', 'resource_type', 'resource_id', 'changes', 'occurred_at'];
        $this->assertSame($old, DB::table('audit_events')->where('actor_type', 'user')->orderBy('id')->get($fields)->toJson());
        foreach ([$a, $b] as $tenant) {
            $role = DB::table('roles')->where('tenant_id', $tenant->id)->where('system_kind', 'owner')->sole();
            $this->assertSame(7, DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->count());
            $this->assertSame(6, DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->where('is_delegable', true)->count());
            foreach ($tenant->memberships()->where('is_owner', true)->get() as $member) {
                $this->assertDatabaseHas('membership_roles', ['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => 'tenant']);
            }
            $event = DB::table('audit_events')->where('tenant_id', $tenant->id)->where('actor_type', 'migration')->sole();
            $this->assertNull($event->actor_id);
            $this->assertSame('access.foundation.upgraded', $event->action);
            $this->assertSame(array_values(array_filter(CapabilityCatalog::OWNER, fn ($code) => str_starts_with($code, 'tenant.') && $code !== 'tenant.members.invite')), json_decode($event->changes, true)['capabilities']);
        }
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Rollback must preserve migration evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('evidence must be preserved', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('audit_events', 'actor_type'));
        $this->assertDatabaseCount('audit_events', 6);
    }

    public function test_unexpected_f1_owner_assignment_fails_closed_and_upgrade_is_atomic(): void
    {
        $tenant = $this->workspaceFor(User::factory()->create());
        $member = $this->memberFor($tenant, User::factory()->create());
        $this->artisan('migrate:rollback', ['--step' => 5])->assertSuccessful();
        $ownerRole = DB::table('roles')->where('tenant_id', $tenant->id)->where('code', 'owner')->sole();
        DB::table('membership_roles')->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $ownerRole->id, 'access_scope' => 'tenant']);
        try {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_15_000001_add_workspace_governance.php'])->run();
            $this->fail('Unexpected mapping must require explicit review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Review the F1 owner bundle', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('roles', 'system_kind'));
        $this->assertDatabaseCount('permissions', 4);
        $this->assertDatabaseCount('audit_events', 2);
        DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->where('role_id', $ownerRole->id)->delete();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_15_000001_add_workspace_governance.php'])->assertSuccessful();
        $this->assertDatabaseCount('audit_events', 3);
    }
}
