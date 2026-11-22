<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class DocumentMigrationTest extends TestCase
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

    public function test_upgrade_from_p1_preserves_evidence_and_gives_only_the_protected_initial_bundle_new_authority(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $ordinary = $this->memberFor($tenant, User::factory()->create());
        $ids = DB::table('permissions')->where('code', 'like', 'documents.%')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        $before = DB::table('audit_events')->orderBy('id')->get()->toJson();
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($before, DB::table('audit_events')->where('actor_type', 'user')->orderBy('id')->get()->toJson());
        $event = DB::table('audit_events')->where('action', 'access.documents.upgraded')->sole();
        $this->assertNull($event->actor_id);
        $this->assertSame('migration', $event->actor_type);
        $this->assertSame(1, (int) $tenant->memberships()->where('is_owner', true)->sole()->access_version);
        $this->assertSame(0, (int) $ordinary->fresh()->access_version);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $owner->id)->sole());
        $this->assertTrue(Gate::forUser($owner)->allows('create', [Document::class, $tenant]));
        app(TenantContext::class)->clear();
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Upgrade evidence must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('document_versions'));
    }

    public function test_pristine_schema_rolls_back_and_reapplies_but_existing_documents_prevent_history_loss(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('documents'));
        $this->artisan('migrate')->assertSuccessful();
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        Document::create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'title' => 'Synthetic historical metadata']);
        try {
            $this->artisan('migrate:rollback', ['--step' => 1])->run();
            $this->fail('Document history must survive rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history must be preserved', $exception->getMessage());
        }
        $this->assertDatabaseCount('documents', 1);
    }
}
