<?php

namespace Tests\Feature\Parties;

use App\Models\User;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Actions\SaveTenantParty;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class PartyMigrationTest extends TestCase
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

    public function test_upgrade_preserves_existing_history_adds_only_initial_owner_capabilities_and_invalidates_old_authority_epochs(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2])->assertSuccessful();
        $first = User::factory()->create();
        $a = $this->workspaceFor($first);
        $b = $this->workspaceFor(User::factory()->create());
        $ordinary = $this->memberFor($a, User::factory()->create());
        // Current code creates the enlarged initial bundle. Reconstruct F4's
        // exact catalog/assignments before applying the additive P1 migration.
        $ids = DB::table('permissions')->where(fn ($query) => $query->where('code', 'like', 'parties.%')->orWhere('code', 'like', 'documents.%'))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        $before = DB::table('audit_events')->orderBy('id')->get()->toJson();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_15_000003_add_tenant_parties.php'])->assertSuccessful();
        $this->assertSame($before, DB::table('audit_events')->where('actor_type', 'user')->orderBy('id')->get()->toJson());
        foreach ([$a, $b] as $tenant) {
            $event = DB::table('audit_events')->where('tenant_id', $tenant->id)->where('action', 'access.parties.upgraded')->sole();
            $this->assertNull($event->actor_id);
            $this->assertSame('migration', $event->actor_type);
            $this->assertCount(4, json_decode($event->changes, true)['capabilities']);
            $this->assertSame(1, (int) $tenant->memberships()->where('is_owner', true)->sole()->access_version);
        }
        $this->assertSame(0, (int) $ordinary->fresh()->access_version);
        $this->assertDatabaseCount('tenant_parties', 0);
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Upgrade evidence must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('tenant_parties'));
    }

    public function test_pristine_schema_can_be_reapplied_but_recorded_party_history_cannot_be_downgraded(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('tenant_parties'));
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_15_000003_add_tenant_parties.php'])->assertSuccessful();
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());
        app(SaveTenantParty::class)->execute($owner, $tenant, null, TenantPartyTest::values());
        app(TenantContext::class)->clear();
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Snapshots must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertDatabaseCount('tenant_party_revisions', 1);
    }
}
