<?php

namespace Tests\Feature\Parties;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\ChangeMembershipStatus;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Party;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Actions\SaveTenantParty;
use App\Modules\Parties\Models\TenantParty;
use App\Modules\Parties\Queries\PartyQueries;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class TenantPartyTest extends TestCase
{
    use RefreshDatabase, WorkspaceFixtures;

    public static function values(array $overrides = []): array
    {
        return [...[
            'kind' => 'person', 'display_name' => 'Contraparte sintética',
            'legal_name' => 'Nombre privado inicial', 'email' => 'contact@example.invalid', 'phone' => '+00 000 000',
            'notes' => 'Nota privada sintética', 'source_note' => 'Datos facilitados manualmente para prueba',
            'business_type_codes' => ['production', 'writing'],
            'identifiers' => [['scheme' => 'ipi', 'value' => '00012345678', 'issuer' => 'Declarado por la contraparte']],
        ], ...$overrides];
    }

    private function save(User $actor, Tenant $tenant, array $values = [], ?TenantParty $party = null): TenantParty
    {
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $actor->id)->sole());
        try {
            return app(SaveTenantParty::class)->execute($actor, $tenant, $party?->id, self::values($values));
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    public function test_create_is_local_provisional_and_does_not_associate_a_matching_account_or_global_identity(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $other = User::factory()->create(['email' => 'contact@example.invalid']);
        $this->workspaceFor($other);
        $globalCount = Party::count();
        $this->actingAs($owner)->post(route('parties.store', $tenant), self::values())->assertRedirect();
        $party = TenantParty::where('tenant_id', $tenant->id)->sole();
        $this->assertNull($party->party_id);
        $this->assertSame('provisional', $party->verification_status);
        $this->assertSame('manual', $party->source);
        $this->assertSame($globalCount, Party::count());
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(1, $tenant->memberships()->count());
        $revision = DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->sole();
        $snapshot = json_decode($revision->snapshot, true);
        $this->assertSame('00012345678', $snapshot['identifiers'][0]['value']);
        $this->assertCount(2, $snapshot['activities']);
        $event = DB::table('audit_events')->where('action', 'party.created')->sole();
        $this->assertStringNotContainsString('contact@example.invalid', $event->changes);
        $this->assertStringNotContainsString('Nota privada', $event->changes);
        $this->assertSame(['version' => 1, 'revision_id' => $revision->id], json_decode($event->changes, true));
    }

    public function test_organization_and_person_accept_missing_contacts_identifiers_and_activities_and_multiple_local_matches(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        foreach ([$a, $a, $b] as $tenant) {
            $party = $this->save($owner, $tenant, ['kind' => 'organization', 'email' => null, 'phone' => null, 'legal_name' => null, 'notes' => null, 'source_note' => null, 'business_type_codes' => [], 'identifiers' => []]);
            $this->assertSame('organization', $party->kind);
        }
        $this->assertDatabaseCount('tenant_parties', 3);
        $this->assertDatabaseCount('tenant_party_revisions', 3);
        $this->assertDatabaseCount('party_identifiers', 0);
    }

    public function test_lists_counts_pagination_search_and_nested_reads_never_cross_tenants(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $mine = $this->save($owner, $a, ['display_name' => 'Visible A']);
        $other = $this->save($owner, $b, ['display_name' => 'Secret B']);
        $this->actingAs($owner)->withSession(['current_workspace_id' => $b->id])->withHeaders(['X-Tenant-ID' => $b->id])->get(route('parties.index', $a))->assertInertia(fn (Assert $page) => $page->component('parties/Index')->where('parties.total', 1)->where('parties.data.0.id', $mine->id)->missing('parties.data.0.email')->missing('parties.data.0.identifiers'));
        $this->get(route('parties.index', [$a, 'search' => 'Secret B']))->assertInertia(fn (Assert $page) => $page->where('parties.total', 0));
        $this->get(route('parties.index', [$a, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->where('parties.total', 1)->has('parties.data', 0));
        $this->get(route('parties.show', [$a, $other->id]))->assertNotFound();
        $this->get(route('parties.edit', [$a, $other->id]))->assertNotFound();
        $this->patch(route('parties.update', [$a, $other->id]), self::values(['version' => 1]))->assertNotFound();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('parties.index', $a))->assertNotFound();
        $this->get(route('parties.show', [$a, $mine->id]))->assertNotFound();
    }

    public function test_summary_projection_omits_private_data_and_history_and_cannot_search_hidden_fields(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $party = $this->save($owner, $tenant);
        $viewer = User::factory()->create();
        $this->memberFor($tenant, $viewer, ['tenant.view', 'parties.view']);
        $this->actingAs($viewer)->get(route('parties.show', [$tenant, $party->id]))->assertInertia(fn (Assert $page) => $page->where('party.can_view_private', false)->where('party.can_update', false)->missing('party.private')->where('revisions', null));
        foreach (['contact@example.invalid', '00012345678', 'Nota privada', 'Nombre privado'] as $secret) {
            $this->get(route('parties.index', [$tenant, 'search' => $secret]))->assertInertia(fn (Assert $page) => $page->where('parties.total', 0));
        }
        $this->get(route('parties.edit', [$tenant, $party->id]))->assertForbidden();
        $this->post(route('parties.store', $tenant), self::values())->assertForbidden();
        $this->patch(route('parties.update', [$tenant, $party->id]), self::values(['version' => 1]))->assertForbidden();
        $this->assertDatabaseMissing('audit_events', ['actor_id' => $viewer->id, 'action' => 'party.private_viewed']);
    }

    public function test_write_capabilities_without_private_projection_do_not_allow_blind_edits_or_contact_disclosure(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $party = $this->save($owner, $tenant);
        $member = User::factory()->create();
        $this->memberFor($tenant, $member, ['tenant.view', 'parties.view', 'parties.create', 'parties.update']);
        $this->actingAs($member)->get(route('parties.create', $tenant))->assertForbidden();
        $this->get(route('parties.edit', [$tenant, $party->id]))->assertForbidden();
        $this->patch(route('parties.update', [$tenant, $party->id]), self::values(['version' => 1]))->assertForbidden();
        $this->get(route('parties.show', [$tenant, $party->id]))->assertInertia(fn (Assert $page) => $page->missing('party.private')->where('party.can_update', false));
        $this->assertSame(1, $party->fresh()->version);
    }

    public function test_current_private_access_is_audited_and_a_new_revision_never_rewrites_old_values(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $party = $this->save($owner, $tenant);
        $before = DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->sole();
        $this->actingAs($owner)->patch(route('parties.update', [$tenant, $party->id]), self::values([
            'version' => 1, 'display_name' => 'Nombre actualizado', 'legal_name' => 'Nombre legal nuevo', 'email' => 'updated@example.invalid',
            'identifiers' => [], 'business_type_codes' => ['publishing'], 'notes' => 'Nueva nota',
        ]))->assertRedirect(route('parties.show', [$tenant, $party->id]));
        $this->assertSame(2, $party->fresh()->version);
        $this->assertSame($before->snapshot, DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->where('id', $before->id)->value('snapshot'));
        $this->get(route('parties.show', [$tenant, $party->id]))->assertInertia(fn (Assert $page) => $page->where('party.private.email', 'updated@example.invalid')->has('party.private.identifiers', 0)->where('revisions.total', 2)->where('revisions.data.1.snapshot.email', 'contact@example.invalid')->where('revisions.data.1.snapshot.identifiers.0.value', '00012345678'));
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->id, 'actor_id' => $owner->id, 'action' => 'party.private_viewed']);
        $this->patch(route('parties.update', [$tenant, $party->id]), self::values(['version' => 1]))->assertSessionHasErrors('version');
        $this->assertSame(2, $party->fresh()->version);
        $this->assertSame(2, DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->count());
    }

    public function test_revision_pagination_preserves_the_original_snapshot_and_workspace_timezone(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(3, 0));
        $party = $this->save($owner, $tenant);
        for ($version = 1; $version <= 5; $version++) {
            $party = $this->save($owner, $tenant, ['version' => $version, 'display_name' => 'Revisión '.$version], $party);
        }
        $this->actingAs($owner)->get(route('parties.show', [$tenant, $party->id, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->where('revisions.total', 6)->has('revisions.data', 1)->where('revisions.data.0.version', 1)->where('revisions.data.0.recorded_at', '2026-11-14 22:00')->where('revisions.data.0.snapshot.display_name', 'Contraparte sintética'));
    }

    public function test_privileged_client_fields_and_invalid_children_cannot_create_or_promote_a_party(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $this->actingAs($owner);
        foreach (['tenant_id' => (string) Str::uuid(), 'party_id' => $tenant->operator_party_id, 'user_id' => $owner->id, 'verification_status' => 'verified', 'source' => 'consented_import'] as $field => $value) {
            $this->post(route('parties.store', $tenant), self::values([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->post(route('parties.store', $tenant), self::values(['identifiers' => [['scheme' => 'ipi', 'value' => '0001'], ['scheme' => 'ipi', 'value' => '0001']]]))->assertSessionHasErrors('identifiers.1.value');
        $this->post(route('parties.store', $tenant), self::values(['business_type_codes' => ['unknown']]))->assertSessionHasErrors('business_type_codes.0');
        $this->post(route('parties.store', $tenant), self::values(['identifiers' => [['scheme' => 'ipi', 'value' => '0001', 'tenant_id' => $tenant->id]]]))->assertSessionHasErrors('identifiers.0');
        $this->post(route('parties.store', $tenant), self::values(['identifiers' => [['scheme' => 'ipi', 'value' => 123]]]))->assertSessionHasErrors('identifiers.0.value');
        $this->assertDatabaseCount('tenant_parties', 0);
        $this->assertDatabaseCount('tenant_party_revisions', 0);
    }

    public function test_missing_context_denies_direct_queries_and_actions_even_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        app(TenantContext::class)->clear();
        foreach ([fn () => app(PartyQueries::class)->listing($owner, $tenant), fn () => app(SaveTenantParty::class)->execute($owner, $tenant, null, self::values())] as $operation) {
            try {
                $operation();
                $this->fail('Missing context must fail closed.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('tenant_parties', 0);
            }
        }
    }

    public function test_same_user_has_distinct_workspace_permissions_and_revocation_is_immediate(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $pa = $this->save($owner, $a);
        $pb = $this->save($owner, $b);
        $member = User::factory()->create();
        $membershipA = $this->memberFor($a, $member, ['tenant.view', 'parties.view', 'parties.view_private', 'parties.create', 'parties.update']);
        $this->memberFor($b, $member, ['tenant.view', 'parties.view']);
        $this->actingAs($member)->get(route('parties.show', [$a, $pa->id]))->assertInertia(fn (Assert $page) => $page->where('party.can_update', true)->where('party.private.email', 'contact@example.invalid'));
        $this->get(route('parties.show', [$b, $pb->id]))->assertInertia(fn (Assert $page) => $page->where('party.can_update', false)->missing('party.private'));
        $proof = $this->proofFor($owner);
        app(TenantContext::class)->establish($a, $a->memberships()->where('user_id', $owner->id)->sole());
        app(ChangeMembershipStatus::class)->execute($owner, $a, $membershipA->id, 'revoked', $proof);
        app(TenantContext::class)->clear();
        $this->get(route('parties.show', [$a, $pa->id]))->assertNotFound();
        $this->patch(route('parties.update', [$a, $pa->id]), self::values(['version' => 1]))->assertNotFound();
        $this->get(route('parties.show', [$b, $pb->id]))->assertOk();
    }

    public function test_assigned_resource_capabilities_do_not_grant_access_without_a_supported_assignment(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $party = $this->save($owner, $tenant);
        $member = User::factory()->create();
        $this->memberFor($tenant, $member, ['tenant.view', 'parties.view', 'parties.view_private'], scope: 'assigned_resources');
        $this->actingAs($member)->get(route('parties.show', [$tenant, $party->id]))->assertNotFound();
    }

    public function test_audit_failure_rolls_back_parent_children_and_revision(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $party = $this->save($owner, $tenant);
        $before = DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->sole()->snapshot;
        $this->mock(RecordAudit::class)->shouldReceive('execute')->andThrow(new \RuntimeException('Synthetic audit failure'));
        try {
            $this->save($owner, $tenant, ['version' => 1, 'email' => 'uncommitted@example.invalid', 'identifiers' => [], 'business_type_codes' => []], $party);
            $this->fail('Audit failure must roll back edits.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        $this->assertSame(1, $party->fresh()->version);
        $this->assertSame('contact@example.invalid', $party->fresh()->email);
        $this->assertSame($before, DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->sole()->snapshot);
        $this->assertDatabaseCount('party_identifiers', 1);
        $this->assertDatabaseCount('tenant_party_business_types', 2);
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

    public function test_postgresql_rejects_cross_tenant_children_and_immutable_history_mutation(): void
    {
        $owner = User::factory()->create();
        $a = $this->workspaceFor($owner);
        $b = $this->workspaceFor($owner);
        $party = $this->save($owner, $a);
        $this->reject(fn () => DB::table('party_identifiers')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $b->id, 'tenant_party_id' => $party->id, 'scheme' => 'ipi', 'value' => '001']), '23503');
        $this->reject(fn () => DB::table('tenant_party_business_types')->insert(['tenant_id' => $b->id, 'tenant_party_id' => $party->id, 'business_type_id' => DB::table('business_types')->value('id')]), '23503');
        $this->reject(fn () => DB::table('tenant_party_revisions')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $b->id, 'tenant_party_id' => $party->id, 'version' => 2, 'snapshot' => '{}', 'recorded_by' => $owner->id, 'recorded_at' => now()]), '23503');
        $this->reject(fn () => DB::table('tenant_party_revisions')->where('tenant_id', $a->id)->update(['snapshot' => '{}']), '23514');
        $this->reject(fn () => DB::table('tenant_party_revisions')->where('tenant_id', $a->id)->delete(), '23514');
        $this->reject(fn () => DB::statement('TRUNCATE tenant_party_revisions'), '23514');
        $this->reject(fn () => DB::table('tenant_parties')->where('tenant_id', $a->id)->where('id', $party->id)->update(['party_id' => $a->operator_party_id]), '23514');
        $this->assertDatabaseCount('tenant_party_revisions', 1);
    }
}
