<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Actions\SaveRole;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase, WorkspaceFixtures;

    private function roleFor(Tenant $tenant, array $codes = ['tenant.view'], bool $delegable = false, string $name = 'Consulta'): Role
    {
        $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => $name]);
        foreach ($codes as $code) {
            DB::table('role_permissions')->insert([
                'tenant_id' => $tenant->id, 'role_id' => $role->id,
                'permission_id' => Permission::query()->where('code', $code)->sole()->id, 'is_delegable' => $delegable,
            ]);
        }

        return $role;
    }

    private function roleData(User $actor, array $codes = ['tenant.view'], bool $delegable = false): array
    {
        return [...$this->sensitiveData($actor), 'name' => 'Equipo sintético',
            'permissions' => array_map(fn ($code) => ['code' => $code, 'delegable' => $delegable], $codes)];
    }

    public function test_roles_search_count_pagination_and_props_are_workspace_scoped(): void
    {
        $actor = User::factory()->create();
        $a = $this->workspaceFor($actor, 'A');
        $b = $this->workspaceFor($actor, 'B');
        for ($i = 0; $i < 12; $i++) {
            $this->roleFor($a, name: 'Local '.$i);
        }
        $this->roleFor($b, name: 'Secreto B');
        $this->actingAs($actor)->get(route('workspaces.roles', $a))->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/Roles')->where('roles.total', 13)->has('roles.data', 10)
            ->has('capabilities', 6)->where('mfaReady', false)->missing('workspace.operator_party_id'));
        $this->get(route('workspaces.roles', [$a, 'search' => 'Secreto B', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('roles.total', 0)->has('roles.data', 0));
        $this->get(route('workspaces.roles', [$a, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('roles.data', 3));
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('workspaces.roles', $a))->assertNotFound();
        $member = $this->memberFor($b, $outsider);
        $this->get(route('workspaces.roles', $b))->assertForbidden();
        DB::table('membership_roles')->where('tenant_id', $b->id)->where('membership_id', $member->id)->update(['access_scope' => 'assigned_resources']);
        $this->get(route('workspaces.roles', $b))->assertNotFound();
    }

    public function test_role_create_update_delete_are_audited_without_secrets_and_assignments_are_removed(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $this->actingAs($actor)->post(route('workspaces.roles.store', $tenant), $this->roleData($actor))->assertSessionHasNoErrors();
        $role = Role::query()->where('tenant_id', $tenant->id)->whereNull('system_kind')->sole();
        $this->patch(route('workspaces.roles.update', [$tenant, $role]), $this->roleData($actor, ['tenant.view', 'tenant.members.view'], true))
            ->assertSessionHasNoErrors()->assertRedirect(route('workspaces.roles', $tenant));
        $target = $this->memberFor($tenant, User::factory()->create());
        DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $target->id)->delete();
        $this->put(route('workspaces.members.roles', [$tenant, $target]), [
            ...$this->sensitiveData($actor), 'assignments' => [['role_id' => $role->id, 'scope' => 'tenant']],
        ])->assertSessionHasNoErrors();
        $this->delete(route('workspaces.roles.destroy', [$tenant, $role]), $this->sensitiveData($actor))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('membership_roles', ['tenant_id' => $tenant->id, 'role_id' => $role->id]);
        $events = AuditEvent::query()->where('tenant_id', $tenant->id)->where('action', 'like', 'role.%')->get();
        $this->assertEqualsCanonicalizing(['role.created', 'role.updated', 'role.deleted'], $events->pluck('action')->all());
        $this->assertStringNotContainsString('password', $events->toJson());
        $this->assertStringNotContainsString('Equipo sintético', $events->toJson());
        $this->assertTrue($events->every(fn ($e) => $e->actor_id === $actor->id && $e->actor_type === 'user'));
    }

    public function test_held_but_non_delegable_capabilities_cannot_be_granted_or_removed_indirectly(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $admin = User::factory()->create();
        $this->memberFor($tenant, $admin, ['tenant.view', 'tenant.roles.manage', 'tenant.members.assign_roles', 'tenant.members.revoke'], delegable: true);
        // The admin holds settings.update, but cannot delegate it.
        $extra = $this->roleFor($tenant, ['tenant.settings.update']);
        $adminMember = $tenant->memberships()->where('user_id', $admin->id)->sole();
        DB::table('membership_roles')->insert(['tenant_id' => $tenant->id, 'membership_id' => $adminMember->id, 'role_id' => $extra->id, 'access_scope' => 'tenant']);
        $target = $this->memberFor($tenant, User::factory()->create(), ['tenant.settings.update']);
        $this->actingAs($admin)->post(route('workspaces.roles.store', $tenant), $this->roleData($admin, ['tenant.settings.update']))->assertSessionHasErrors('permissions');
        $this->patch(route('workspaces.roles.update', [$tenant, $extra]), $this->roleData($admin))->assertSessionHasErrors('permissions');
        $this->delete(route('workspaces.roles.destroy', [$tenant, $extra]), $this->sensitiveData($admin))->assertSessionHasErrors('permissions');
        $this->put(route('workspaces.members.roles', [$tenant, $target]), [...$this->sensitiveData($admin), 'assignments' => []])->assertSessionHasErrors('permissions');
        $target->update(['status' => 'suspended']);
        $this->patch(route('workspaces.members.status', [$tenant, $target]), [...$this->sensitiveData($admin), 'status' => 'active'])->assertSessionHasErrors('permissions');
        $this->assertSame('suspended', $target->fresh()->status);
        $this->get(route('workspaces.roles', $tenant))->assertInertia(fn (Assert $page) => $page->where('capabilities', fn ($items) => ! collect($items)->contains('code', 'tenant.settings.update')));
    }

    public function test_protected_owner_bundle_cannot_be_edited_deleted_assigned_or_forged(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $ownerRole = Role::query()->where('tenant_id', $tenant->id)->where('system_kind', 'owner')->sole();
        $ownerMember = $tenant->memberships()->sole();
        $target = $this->memberFor($tenant, User::factory()->create());
        $this->actingAs($actor)->patch(route('workspaces.roles.update', [$tenant, $ownerRole]), $this->roleData($actor))->assertForbidden();
        $this->delete(route('workspaces.roles.destroy', [$tenant, $ownerRole]), $this->sensitiveData($actor))->assertForbidden();
        $this->put(route('workspaces.members.roles', [$tenant, $target]), [...$this->sensitiveData($actor), 'assignments' => [['role_id' => $ownerRole->id, 'scope' => 'tenant']]])->assertForbidden();
        $this->put(route('workspaces.members.roles', [$tenant, $ownerMember]), [...$this->sensitiveData($actor), 'assignments' => []])->assertForbidden();
        $this->post(route('workspaces.roles.store', $tenant), $this->roleData($actor, ['tenant.ownership.transfer']))->assertSessionHasErrors('permissions.0.code');
        $this->post(route('workspaces.roles.store', $tenant), [...$this->roleData($actor), 'system_kind' => 'owner', 'tenant_id' => $tenant->id])->assertSessionHasErrors(['system_kind', 'tenant_id']);
        $this->assertTrue($ownerMember->fresh()->is_owner);
    }

    public function test_cross_workspace_roles_members_and_transfer_targets_fail_before_mutation(): void
    {
        $actor = User::factory()->create();
        $a = $this->workspaceFor($actor, 'A');
        $b = $this->workspaceFor($actor, 'B');
        $role = $this->roleFor($b);
        $outside = $b->memberships()->sole();
        $target = $this->memberFor($a, User::factory()->create());
        $this->actingAs($actor)->patch(route('workspaces.roles.update', [$a, $role]), $this->roleData($actor))->assertNotFound();
        $this->delete(route('workspaces.roles.destroy', [$a, $role]), $this->sensitiveData($actor))->assertNotFound();
        $this->put(route('workspaces.members.roles', [$a, $target]), [...$this->sensitiveData($actor), 'assignments' => [['role_id' => $role->id, 'scope' => 'tenant']]])->assertNotFound();
        $this->put(route('workspaces.members.roles', [$a, $outside]), [...$this->sensitiveData($actor), 'assignments' => []])->assertNotFound();
        $this->patch(route('workspaces.members.status', [$a, $outside]), [...$this->sensitiveData($actor), 'status' => 'suspended'])->assertNotFound();
        $this->post(route('workspaces.ownership.transfer', $a), [...$this->sensitiveData($actor), 'membership_id' => $outside->id, 'confirmation' => true])->assertNotFound();
        $this->assertSame('active', $outside->fresh()->status);
        $this->assertTrue($outside->fresh()->is_owner);
        $this->assertDatabaseCount('roles', 4);
    }

    public function test_missing_unconfirmed_invalid_and_reused_mfa_cannot_mutate_and_secrets_are_not_flashed(): void
    {
        $actor = User::factory()->create();
        $a = $this->workspaceFor($actor, 'A');
        $b = $this->workspaceFor($actor, 'B');
        $data = ['password' => 'password', 'code' => '123456', 'name' => 'Protected', 'permissions' => [['code' => 'tenant.view', 'delegable' => false]]];
        $this->actingAs($actor)->post(route('workspaces.roles.store', $a), $data)->assertSessionHasErrors('code');
        $data = $this->roleData($actor);
        $actor->fresh()->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->post(route('workspaces.roles.store', $a), $data)->assertSessionHasErrors('code');
        $this->enableMfa($actor);
        $this->post(route('workspaces.roles.store', $a), [...$data, 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('workspaces.roles.store', $a), [...$data, 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('code', session()->getOldInput());
        $this->post(route('workspaces.roles.store', $a), $data)->assertSessionHasNoErrors();
        $this->post(route('workspaces.roles.store', $b), $data)->assertSessionHasErrors('code');
        $this->assertSame(1, Role::query()->where('tenant_id', $a->id)->whereNull('system_kind')->count());
        $this->assertSame(0, Role::query()->where('tenant_id', $b->id)->whereNull('system_kind')->count());
        $this->assertArrayNotHasKey('last_sensitive_totp_step', $actor->fresh()->toArray());
    }

    public function test_governance_rate_limit_is_shared_across_operations_and_workspaces(): void
    {
        $actor = User::factory()->create();
        $a = $this->workspaceFor($actor);
        $b = $this->workspaceFor($actor);
        $data = ['name' => 'Attempt', 'permissions' => [['code' => 'tenant.view', 'delegable' => false]], 'password' => 'password', 'code' => '123456'];
        $this->actingAs($actor);
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('workspaces.roles.store', $i % 2 ? $a : $b), $data)->assertSessionHasErrors('code');
        }
        $this->post(route('workspaces.ownership.transfer', $b), $data)->assertStatus(429);
    }

    public function test_audit_failure_rolls_back_role_and_totp_consumption(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $data = $this->roleData($actor);
        $this->mock(RecordAudit::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->actingAs($actor)->withoutExceptionHandling();
        try {
            $this->post(route('workspaces.roles.store', $tenant), $data);
            $this->fail('Mutation should roll back.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertNull($actor->fresh()->last_sensitive_totp_step);
        $this->assertSame(0, Role::query()->where('tenant_id', $tenant->id)->whereNull('system_kind')->count());
        app()->forgetInstance(RecordAudit::class);
        $this->post(route('workspaces.roles.store', $tenant), $data)->assertSessionHasNoErrors();
    }

    public function test_assigned_resources_grants_no_workspace_access_and_changes_take_effect_immediately(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $viewer = User::factory()->create();
        $independent = $this->workspaceFor($viewer);
        $member = $this->memberFor($tenant, $viewer);
        $role = $this->roleFor($tenant, ['tenant.view', 'tenant.members.view']);
        $this->actingAs($actor)->put(route('workspaces.members.roles', [$tenant, $member]), [
            ...$this->sensitiveData($actor), 'assignments' => [['role_id' => $role->id, 'scope' => 'assigned_resources']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($viewer)->get(route('workspaces.show', $tenant))->assertNotFound();
        $this->get(route('workspaces.show', $independent))->assertOk();
        $this->actingAs($actor)->put(route('workspaces.members.roles', [$tenant, $member]), [
            ...$this->sensitiveData($actor), 'assignments' => [['role_id' => $role->id, 'scope' => 'tenant']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($viewer)->get(route('workspaces.members', $tenant))->assertOk();
        foreach (['suspended', 'active', 'revoked', 'active'] as $state) {
            $this->actingAs($actor)->patch(route('workspaces.members.status', [$tenant, $member]), [...$this->sensitiveData($actor), 'status' => $state])->assertSessionHasNoErrors();
            $response = $this->actingAs($viewer)->get(route('workspaces.show', $tenant));
            $state === 'active' ? $response->assertOk() : $response->assertNotFound();
            $this->get(route('workspaces.show', $independent))->assertOk();
        }
        $this->actingAs($actor)->delete(route('workspaces.roles.destroy', [$tenant, $role]), $this->sensitiveData($actor))->assertSessionHasNoErrors();
        $this->actingAs($viewer)->get(route('workspaces.show', $tenant))->assertNotFound();
    }

    public function test_membership_projection_hides_assignments_and_recipient_security_without_capability(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor(User::factory()->create());
        $member = $this->memberFor($tenant, $actor, ['tenant.view', 'tenant.members.view']);
        $this->actingAs($actor)->get(route('workspaces.members', $tenant))->assertInertia(fn (Assert $page) => $page
            ->has('availableRoles', 0)->where('members.data', fn ($items) => collect($items)->every(fn ($item) => ! array_key_exists('assignments', $item) && ! array_key_exists('can_receive_ownership', $item)
                && ! array_key_exists('two_factor_secret', $item) && ! array_key_exists('email', $item))));
        $this->put(route('workspaces.members.roles', [$tenant, $member]), [...$this->sensitiveData($actor), 'assignments' => []])->assertForbidden();
    }

    public function test_transfer_requires_real_ownership_not_merely_capability_and_owner_changes_require_owner(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $ownerMember = $tenant->memberships()->sole();
        $admin = User::factory()->create();
        $this->memberFor($tenant, $admin, CapabilityCatalog::OWNER, delegable: true);
        $this->actingAs($admin)->post(route('workspaces.ownership.transfer', $tenant), [...$this->sensitiveData($admin), 'membership_id' => $ownerMember->id, 'confirmation' => true])->assertForbidden();
        $this->patch(route('workspaces.members.status', [$tenant, $ownerMember]), [...$this->sensitiveData($admin), 'status' => 'suspended'])->assertForbidden();
    }

    public function test_transfer_requires_active_verified_mfa_recipient_and_explicit_confirmation(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $recipient = User::factory()->create();
        $member = $this->memberFor($tenant, $recipient);
        $this->actingAs($actor);
        $data = [...$this->sensitiveData($actor), 'membership_id' => $member->id, 'confirmation' => true];
        $this->post(route('workspaces.ownership.transfer', $tenant), $data)->assertSessionHasErrors('membership');
        $this->enableMfa($recipient);
        $recipient->fresh()->forceFill(['email_verified_at' => null])->save();
        $this->post(route('workspaces.ownership.transfer', $tenant), $data)->assertSessionHasErrors('membership');
        $recipient->fresh()->forceFill(['email_verified_at' => now(), 'status' => 'suspended'])->save();
        $this->post(route('workspaces.ownership.transfer', $tenant), $data)->assertSessionHasErrors('membership');
        $recipient->fresh()->forceFill(['status' => 'active'])->save();
        $member->update(['status' => 'suspended']);
        $this->post(route('workspaces.ownership.transfer', $tenant), $data)->assertSessionHasErrors('membership');
        $member->update(['status' => 'active']);
        $this->post(route('workspaces.ownership.transfer', $tenant), [...$data, 'confirmation' => false])->assertSessionHasErrors('confirmation');
        $this->post(route('workspaces.ownership.transfer', $tenant), [...$data, 'membership_id' => $tenant->memberships()->where('user_id', $actor->id)->sole()->id])->assertSessionHasErrors('membership');
        $this->assertNull($actor->fresh()->last_sensitive_totp_step);
    }

    public function test_transfer_preserves_independent_roles_and_operator_identity_but_moves_governance(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $operator = $tenant->operator_party_id;
        $source = $tenant->memberships()->sole();
        $recipient = $this->enableMfa(User::factory()->create());
        $target = $this->memberFor($tenant, $recipient);
        $viewer = $this->roleFor($tenant);
        DB::table('membership_roles')->insert(['tenant_id' => $tenant->id, 'membership_id' => $source->id, 'role_id' => $viewer->id, 'access_scope' => 'tenant']);
        $this->actingAs($actor)->post(route('workspaces.ownership.transfer', $tenant), [
            ...$this->sensitiveData($actor), 'membership_id' => $target->id, 'confirmation' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('workspaces.index'));
        $this->assertFalse($source->fresh()->is_owner);
        $this->assertTrue($target->fresh()->is_owner);
        $this->assertSame($operator, $tenant->fresh()->operator_party_id);
        $this->assertDatabaseHas('membership_roles', ['tenant_id' => $tenant->id, 'membership_id' => $source->id, 'role_id' => $viewer->id]);
        $this->get(route('workspaces.roles', $tenant))->assertForbidden();
        $this->get(route('workspaces.show', $tenant))->assertInertia(fn (Assert $page) => $page->where('workspace.can_transfer_ownership', false));
        $this->actingAs($recipient)->get(route('workspaces.roles', $tenant))->assertOk();
        $event = AuditEvent::query()->where('tenant_id', $tenant->id)->where('action', 'ownership.transferred')->sole();
        $this->assertEquals(['from_membership_id' => $source->id, 'to_membership_id' => $target->id], $event->changes);
        $this->assertSame($actor->id, $event->actor_id);
        $this->actingAs($recipient)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->patch(route('workspaces.members.status', [$tenant, $target]), [...$this->sensitiveData($recipient), 'status' => 'suspended'])->assertSessionHasErrors('membership');
    }

    public function test_transfer_without_independent_role_returns_safely_to_selector_and_revokes_all_old_sessions(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $recipient = $this->enableMfa(User::factory()->create());
        $target = $this->memberFor($tenant, $recipient);
        $this->actingAs($actor)->post(route('workspaces.ownership.transfer', $tenant), [
            ...$this->sensitiveData($actor), 'membership_id' => $target->id, 'confirmation' => true,
        ])->assertRedirect(route('workspaces.index'));
        $this->get(route('workspaces.show', $tenant))->assertNotFound();
        $this->get(route('workspaces.index'))->assertInertia(fn (Assert $page) => $page->where('workspaces.total', 0));
    }

    public function test_missing_context_denies_governance_even_for_owner(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $this->expectException(AuthorizationException::class);
        app(SaveRole::class)->execute($actor, $tenant, null, 'No context', [['code' => 'tenant.view', 'delegable' => false]], $this->proofFor($actor));
    }

    public function test_transfer_failure_restores_both_memberships_role_links_and_mfa_counter(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->workspaceFor($actor);
        $source = $tenant->memberships()->sole();
        $target = $this->memberFor($tenant, $this->enableMfa(User::factory()->create()));
        $data = [...$this->sensitiveData($actor), 'membership_id' => $target->id, 'confirmation' => true];
        $links = DB::table('membership_roles')->where('tenant_id', $tenant->id)->orderBy('membership_id')->orderBy('role_id')->get()->toJson();
        $this->mock(RecordAudit::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Synthetic transfer failure'));
        $this->actingAs($actor)->withoutExceptionHandling();
        try {
            $this->post(route('workspaces.ownership.transfer', $tenant), $data);
            $this->fail('Transfer must roll back.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic transfer failure', $e->getMessage());
        }
        $this->assertTrue($source->fresh()->is_owner);
        $this->assertFalse($target->fresh()->is_owner);
        $this->assertNull($actor->fresh()->last_sensitive_totp_step);
        $this->assertSame($links, DB::table('membership_roles')->where('tenant_id', $tenant->id)->orderBy('membership_id')->orderBy('role_id')->get()->toJson());
        $this->assertDatabaseCount('audit_events', 2);
    }
}
