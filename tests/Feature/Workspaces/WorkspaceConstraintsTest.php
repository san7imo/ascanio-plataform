<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\Organization;
use App\Modules\IdentityAccess\Models\Party;
use App\Modules\IdentityAccess\Models\Person;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class WorkspaceConstraintsTest extends TestCase
{
    use RefreshDatabase, WorkspaceFixtures;

    private function reject(callable $mutation, string $sqlState): void
    {
        try {
            DB::transaction(function () use ($mutation) {
                $mutation();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected PostgreSQL to reject the mutation.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        }
    }

    public function test_global_parties_require_exactly_their_compatible_subtype(): void
    {
        $this->reject(fn () => Party::create(['kind' => 'person', 'provenance' => 'synthetic']), '23514');
        $this->reject(function () {
            $party = Party::create(['kind' => 'person', 'provenance' => 'synthetic']);
            Organization::create(['party_id' => $party->id]);
        }, '23503');

        DB::transaction(function () {
            $party = Party::create(['kind' => 'organization', 'provenance' => 'synthetic']);
            Organization::create(['party_id' => $party->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        });
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_a_subtype_cannot_be_deleted_or_reassigned_leaving_its_identity_invalid(): void
    {
        $tenant = $this->workspaceFor(User::factory()->create());
        $this->reject(fn () => DB::table('persons')->where('party_id', $tenant->operator_party_id)->delete(), '23503');
        $this->reject(function () use ($tenant) {
            $party = Party::create(['kind' => 'person', 'provenance' => 'synthetic']);
            DB::table('persons')->where('party_id', $tenant->operator_party_id)->update(['party_id' => $party->id]);
        }, '23514');
        $this->reject(function () {
            $party = Party::create(['kind' => 'person', 'provenance' => 'synthetic']);
            Person::create(['party_id' => $party->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
            DB::table('persons')->where('party_id', $party->id)->delete();
        }, '23514');
    }

    public function test_composite_keys_reject_cross_workspace_role_and_membership_links(): void
    {
        $a = $this->workspaceFor(User::factory()->create());
        $b = $this->workspaceFor(User::factory()->create());
        $member = $a->memberships()->sole();
        $role = Role::query()->where('tenant_id', $b->id)->sole();
        $this->reject(fn () => DB::table('membership_roles')->insert([
            'tenant_id' => $a->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => 'tenant',
        ]), '23503');
        $permission = DB::table('permissions')->first();
        $this->reject(fn () => DB::table('role_permissions')->insert([
            'tenant_id' => $a->id, 'role_id' => $role->id, 'permission_id' => $permission->id,
        ]), '23503');
    }

    public function test_membership_uniqueness_status_and_scope_are_database_constraints(): void
    {
        $user = User::factory()->create();
        $tenant = $this->workspaceFor($user);
        $member = $tenant->memberships()->sole();
        $this->reject(fn () => $tenant->memberships()->create(['user_id' => $user->id, 'accepted_at' => now()]), '23505');
        $this->reject(fn () => $member->update(['status' => 'revoked']), '23514');
        $this->reject(fn () => DB::table('membership_roles')->where('tenant_id', $tenant->id)->update(['access_scope' => 'all_tenants']), '23514');
    }

    public function test_account_and_membership_changes_cannot_remove_the_final_active_owner(): void
    {
        $user = User::factory()->create();
        $tenant = $this->workspaceFor($user);
        $member = $tenant->memberships()->sole();
        $this->reject(fn () => $member->update(['status' => 'revoked', 'revoked_at' => now()]), '23514');
        $this->reject(fn () => $user->forceFill(['status' => 'suspended'])->save(), '23514');
        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame('active', $member->fresh()->status);
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_audit_history_rejects_updates_deletes_and_truncation(): void
    {
        $tenant = $this->workspaceFor(User::factory()->create());
        $event = AuditEvent::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->reject(fn () => DB::table('audit_events')->where('tenant_id', $tenant->id)->where('id', $event->id)->update(['action' => 'forged']), '23514');
        $this->reject(fn () => DB::table('audit_events')->where('tenant_id', $tenant->id)->delete(), '23514');
        $this->reject(fn () => DB::statement('TRUNCATE audit_events'), '23514');
        $this->assertDatabaseCount('audit_events', 2);
    }
}
