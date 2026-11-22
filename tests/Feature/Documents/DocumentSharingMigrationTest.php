<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class DocumentSharingMigrationTest extends TestCase
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

    public function test_upgrade_preserves_d1_history_adds_only_owner_bundle_and_invalidates_old_authority_epoch(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $ordinary = $this->memberFor($tenant, User::factory()->create(), ['tenant.view', 'documents.view']);
        // Reconstruct D1's permission catalog; current CreateWorkspace includes G1.
        $permission = Permission::query()->where('code', 'documents.share')->sole();
        DB::table('role_permissions')->where('permission_id', $permission->id)->delete();
        $permission->delete();
        $before = DB::table('audit_events')->orderBy('id')->get()->toJson();
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($before, DB::table('audit_events')->where('actor_type', 'user')->orderBy('id')->get()->toJson());
        $permission = Permission::query()->where('code', 'documents.share')->sole();
        $this->assertSame(1, DB::table('role_permissions')->where('permission_id', $permission->id)->count());
        $this->assertSame(1, (int) $tenant->memberships()->where('user_id', $owner->id)->sole()->access_version);
        $this->assertSame(0, (int) $ordinary->fresh()->access_version);
        $event = DB::table('audit_events')->where('action', 'access.document_sharing.upgraded')->sole();
        $this->assertSame('migration', $event->actor_type);
        $this->assertNull($event->actor_id);
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Migration provenance must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('resource_grants'));
    }

    public function test_pristine_access_schema_can_roll_back_and_reapply(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('resource_grants'));
        $this->assertFalse(Schema::hasTable('resource_invitations'));
        $this->assertTrue(Schema::hasTable('document_versions'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('resource_grants'));
    }
}
