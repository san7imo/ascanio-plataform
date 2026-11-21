<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Actions\UpdateWorkspace;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\Party;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase, WorkspaceFixtures;

    private function form(): array
    {
        $response = $this->get(route('workspaces.create'))->assertOk();
        $token = $response->viewData('page')['props']['creationToken'];

        return ['name' => 'Operación independiente', 'locale' => 'es', 'timezone' => 'America/Bogota', 'creation_token' => $token];
    }

    public function test_creation_retries_are_idempotent_and_one_account_operates_two_workspaces(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = $this->form();
        $this->post(route('workspaces.store'), $data)->assertSessionHasNoErrors();
        $first = Tenant::sole();
        $this->post(route('workspaces.store'), $data)->assertRedirect(route('workspaces.show', $first));
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('audit_events', 2);
        $secondData = [...$this->form(), 'name' => 'Otra actividad'];
        $this->post(route('workspaces.store'), $secondData)->assertSessionHasNoErrors();
        $second = Tenant::query()->whereKeyNot($first->id)->sole();

        $this->assertDatabaseCount('parties', 1);
        $this->assertSame($user->fresh()->person_id, $first->operator_party_id);
        $this->assertSame($first->operator_party_id, $second->operator_party_id);
        $this->assertSame('self_declared_account', Party::sole()->provenance);
        $this->assertDatabaseCount('permissions', count(CapabilityCatalog::OWNER));

        foreach ([$first, $second, $first] as $tenant) {
            $this->post(route('workspaces.select', $tenant))->assertRedirect(route('workspaces.show', $tenant))->assertSessionHas('current_workspace_id', $tenant->id);
            $this->get(route('workspaces.show', $tenant))->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Show')->where('workspace.id', $tenant->id)->where('workspace.can_update', true)
                ->missing('workspace.operator_party_id')->missing('workspace.creation_key_hash'));
            $this->assertFalse(app(TenantContext::class)->matches($user, $tenant));
        }
    }

    public function test_a_reused_key_with_changed_payload_and_an_unissued_key_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->form();
        $this->post(route('workspaces.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('workspaces.store'), [...$data, 'name' => 'Changed'])->assertSessionHasErrors('creation_token');
        $this->post(route('workspaces.store'), [...$data, 'creation_token' => (string) Str::uuid()])->assertSessionHasErrors('creation_token');
        $this->assertDatabaseCount('tenants', 1);
        DB::table('role_permissions')->where('tenant_id', Tenant::sole()->id)->delete();
        $this->post(route('workspaces.store'), $data)->assertNotFound();
    }

    public function test_creation_ignores_no_privileged_identity_or_tenant_fields(): void
    {
        $owner = User::factory()->create();
        $other = $this->workspaceFor($owner);
        $this->actingAs(User::factory()->create());
        $data = $this->form();
        $this->post(route('workspaces.store'), [...$data,
            'tenant_id' => $other->id, 'owner_id' => $owner->id,
            'operator_party_id' => $other->operator_party_id, 'person_id' => $owner->fresh()->person_id,
            'is_owner' => true, 'status' => 'active',
        ])->assertSessionHasErrors(['tenant_id', 'owner_id', 'operator_party_id', 'person_id', 'is_owner', 'status']);
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_a_failure_rolls_back_identity_workspace_permissions_membership_and_audit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = $this->form();
        $this->mock(RecordAudit::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Synthetic failure'));
        $this->withoutExceptionHandling();

        try {
            $this->post(route('workspaces.store'), $data);
            $this->fail('Creation should roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }

        foreach (['parties', 'persons', 'tenants', 'tenant_memberships', 'roles', 'audit_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($user->fresh()->person_id);
        $this->assertDatabaseCount('permissions', count(CapabilityCatalog::OWNER));
    }

    public function test_workspace_discovery_search_counts_and_pagination_are_account_and_capability_bounded(): void
    {
        $user = User::factory()->create();
        $own = $this->workspaceFor($user, 'Visible');
        $other = $this->workspaceFor(User::factory()->create(), 'Secreto');
        $noScope = $this->workspaceFor(User::factory()->create(), 'Asignados');
        $this->memberFor($noScope, $user, ['tenant.view'], scope: 'assigned_resources');
        $this->actingAs($user)->withSession(['current_workspace_id' => $other->id]);
        $this->get(route('workspaces.index'))->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/Index')->where('workspaces.total', 1)->has('workspaces.data', 1)
            ->where('workspaces.data.0', ['id' => $own->id, 'name' => 'Visible']));
        $this->get(route('workspaces.index', ['search' => 'Secreto', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.total', 0)->has('workspaces.data', 0));
        $this->get(route('workspaces.show', $noScope))->assertNotFound();
    }

    public function test_an_outsider_cannot_read_update_select_list_or_revoke_another_workspace(): void
    {
        $tenant = $this->workspaceFor(User::factory()->create());
        $member = $tenant->memberships()->sole();
        $this->actingAs(User::factory()->create());
        $this->get(route('workspaces.show', $tenant))->assertNotFound();
        $this->get(route('workspaces.members', $tenant))->assertNotFound();
        $this->post(route('workspaces.select', $tenant))->assertNotFound();
        $this->patch(route('workspaces.update', $tenant), ['name' => 'Stolen', 'locale' => 'es', 'timezone' => 'UTC'])->assertNotFound();
        $this->delete(route('workspaces.members.revoke', [$tenant, $member]), ['password' => 'password'])->assertNotFound();
        $this->assertSame('Espacio sintético', $tenant->fresh()->name);
        $this->assertSame('active', $member->fresh()->status);
    }

    public function test_one_user_has_different_capabilities_per_workspace_without_stale_permissions(): void
    {
        $user = User::factory()->create();
        $a = $this->workspaceFor($user, 'A');
        $b = $this->workspaceFor(User::factory()->create(), 'B');
        $member = $this->memberFor($b, $user);
        $this->actingAs($user);
        $this->get(route('workspaces.show', $a))->assertInertia(fn (Assert $page) => $page->where('workspace.can_update', true));
        $this->post(route('workspaces.select', $b))->assertRedirect();
        $this->get(route('workspaces.show', $b))->assertInertia(fn (Assert $page) => $page->where('workspace.can_update', false)->where('workspace.can_view_members', false));
        $this->get(route('workspaces.members', $b))->assertForbidden();
        $this->patch(route('workspaces.update', $b), ['name' => 'No', 'locale' => 'es', 'timezone' => 'UTC'])->assertForbidden();
        DB::table('membership_roles')->where('tenant_id', $b->id)->where('membership_id', $member->id)->delete();
        $this->get(route('workspaces.show', $b))->assertNotFound();
        $this->get(route('workspaces.index'))->assertInertia(fn (Assert $page) => $page->where('workspaces.total', 1));
        $this->get(route('workspaces.show', $a))->assertOk();
        $this->assertFalse(app(TenantContext::class)->matches($user, $b));
    }

    public function test_settings_ignore_session_and_headers_as_authority_and_audit_only_redacted_fields(): void
    {
        $user = User::factory()->create();
        $a = $this->workspaceFor($user, 'A');
        $b = $this->workspaceFor(User::factory()->create(), 'B');
        $this->actingAs($user)->withSession(['current_workspace_id' => $b->id])->withHeader('X-Tenant-ID', $b->id);
        $this->patch(route('workspaces.update', $a), ['name' => 'Privado', 'locale' => 'pt', 'timezone' => 'Europe/Madrid'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workspaces.show', $a));
        $this->assertSame('Privado', $a->fresh()->name);
        $this->assertSame('B', $b->fresh()->name);
        $event = AuditEvent::query()->where('tenant_id', $a->id)->where('action', 'tenant.updated')->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame(['fields' => ['name', 'locale', 'timezone']], $event->changes);
        $this->assertStringNotContainsString('Privado', $event->toJson());
        $this->patch(route('workspaces.update', $a), ['name' => 'No', 'locale' => 'es', 'timezone' => 'UTC', 'tenant_id' => $b->id])->assertSessionHasErrors('tenant_id');
    }

    public function test_member_fields_search_counts_and_nested_mutations_are_scoped(): void
    {
        $user = User::factory()->create();
        $a = $this->workspaceFor($user, 'A');
        $b = $this->workspaceFor(User::factory()->create(['name' => 'Fuera']), 'B');
        $outside = $b->memberships()->sole();
        $this->actingAs($user)->get(route('workspaces.members', $a))->assertInertia(fn (Assert $page) => $page
            ->where('members.total', 1)->has('members.data', 1)->where('members.data.0.name', $user->name)
            ->missing('members.data.0.email')->missing('members.data.0.user_id')->missing('members.data.0.person_id'));
        $this->get(route('workspaces.members', [$a, 'search' => 'Fuera', 'page' => 2]))->assertInertia(fn (Assert $page) => $page->where('members.total', 0)->has('members.data', 0));
        $this->delete(route('workspaces.members.revoke', [$a, $outside]), $this->sensitiveData($user))->assertNotFound();
        $this->assertSame('active', $outside->fresh()->status);
    }

    public function test_revocation_is_immediate_and_does_not_revoke_independent_memberships(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $viewer = User::factory()->create();
        $b = $this->workspaceFor($viewer, 'Independiente');
        $member = $this->memberFor($a, $viewer);
        $this->actingAs($viewer)->get(route('workspaces.show', $a))->assertOk();
        $this->actingAs($owner)->delete(route('workspaces.members.revoke', [$a, $member]), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->delete(route('workspaces.members.revoke', [$a, $member]), $this->sensitiveData($owner))->assertSessionHasNoErrors()->assertRedirect();
        $this->delete(route('workspaces.members.revoke', [$a, $member]), $this->sensitiveData($owner))->assertSessionHasNoErrors();
        $this->assertSame(1, AuditEvent::query()->where('tenant_id', $a->id)->where('action', 'membership.revoked')->count());
        $this->actingAs($viewer)->withSession(['current_workspace_id' => $a->id])->get(route('workspaces.show', $a))->assertNotFound();
        $this->get(route('workspaces.show', $b))->assertOk();
        $this->assertAuthenticatedAs($viewer);
    }

    public function test_the_last_owner_cannot_be_revoked_or_delete_their_account(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $member = $tenant->memberships()->sole();
        $this->actingAs($owner)->delete(route('workspaces.members.revoke', [$tenant, $member]), $this->sensitiveData($owner))->assertSessionHasErrors('membership');
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertAuthenticated();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertNotNull($owner->fresh());
    }

    #[DataProvider('inactiveStates')]
    public function test_inactive_accounts_cannot_login_or_reuse_an_existing_session(string $status): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => $status])->save();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->actingAs($user)->get(route('workspaces.index'))->assertForbidden();
        $this->assertGuest();
    }

    public static function inactiveStates(): array
    {
        return [['suspended'], ['revoked']];
    }

    public function test_suspended_workspaces_are_hidden_and_fail_closed(): void
    {
        $user = User::factory()->create();
        $tenant = $this->workspaceFor($user);
        $tenant->update(['status' => 'suspended']);
        $this->actingAs($user)->get(route('workspaces.show', $tenant))->assertNotFound();
        $this->post(route('workspaces.select', $tenant))->assertNotFound();
        $this->get(route('workspaces.index'))->assertInertia(fn (Assert $page) => $page->where('workspaces.total', 0));
    }

    public function test_absent_context_does_not_authorize_an_action_even_for_the_owner(): void
    {
        $user = User::factory()->create();
        $tenant = $this->workspaceFor($user);
        $this->expectException(AuthorizationException::class);
        app(UpdateWorkspace::class)->execute($user, $tenant, ['name' => 'No context']);
    }

    public function test_unverified_and_anonymous_accounts_cannot_begin_workspace_operations(): void
    {
        $this->get(route('workspaces.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('workspaces.create'))->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_invalid_metadata_and_malformed_route_ids_do_not_reach_private_data(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('workspaces.store'), [...$this->form(), 'locale' => 'xx', 'timezone' => 'Not/AZone', 'name' => ''])->assertSessionHasErrors(['name', 'locale', 'timezone']);
        $this->get('/workspaces/not-a-uuid')->assertNotFound();
        $this->assertDatabaseCount('tenants', 0);
    }
}
