<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\Documents\Actions\UploadDocumentVersion;
use App\Modules\IdentityAccess\Actions\CreateTeamInvitation;
use App\Modules\IdentityAccess\Actions\SaveRole;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Actions\SaveTenantParty;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SyntheticDocumentEnvironment;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class GovernanceRuntimeRoleTest extends TestCase
{
    use WorkspaceFixtures;

    public function test_actual_restricted_login_can_operate_but_cannot_rewrite_audit_or_disable_its_guards(): void
    {
        // TestCase rejects non-testing DBs before any DDL. No SET ROLE from an
        // administrative session: runtime authenticates using its own LOGIN.
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        $original = config('database.connections.pgsql');
        config(['database.connections.governance_migration' => $original]);
        $migration = DB::connection('governance_migration');
        $this->assertSame('ascanio_test', $migration->selectOne('SELECT current_database() AS name')->name);
        $role = 'ascanio_runtime_test_'.bin2hex(random_bytes(8));
        $password = bin2hex(random_bytes(32));
        $created = false;

        try {
            try {
                $migration->getPdo()->exec('CREATE ROLE '.$role.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD '.$migration->getPdo()->quote($password));
                $created = true;
            } catch (\PDOException) {
                throw new \RuntimeException('Dedicated test provisioning requires a migration login with CREATEROLE. Credentials are omitted.');
            }
            $migration->unprepared("GRANT CONNECT ON DATABASE ascanio_test TO {$role}; GRANT USAGE ON SCHEMA public TO {$role}");
            // Current application tables, not a grant on all future business data.
            $tables = 'users,password_reset_tokens,sessions,cache,cache_locks,jobs,job_batches,failed_jobs,parties,persons,organizations,tenants,tenant_memberships,roles,role_permissions,membership_roles,team_invitations,team_invitation_roles,tenant_parties,party_identifiers,tenant_party_business_types,documents';
            $migration->unprepared("GRANT SELECT,INSERT,UPDATE,DELETE ON {$tables} TO {$role}");
            $migration->unprepared("GRANT SELECT ON permissions,audit_events,business_types TO {$role}");
            $migration->unprepared("GRANT SELECT,INSERT ON tenant_party_revisions,document_versions TO {$role}");
            $migration->unprepared("GRANT UPDATE (scan_status,scan_generation,scan_requested_by,source_membership_id,source_access_version,failure_code,scanned_at,updated_at) ON document_versions TO {$role}");
            $migration->unprepared("GRANT INSERT (id,tenant_id,actor_id,action,resource_type,resource_id,changes,occurred_at) ON audit_events TO {$role}");
            $migration->unprepared("GRANT USAGE,SELECT ON SEQUENCE jobs_id_seq,failed_jobs_id_seq TO {$role}");
            $actor = User::factory()->create();

            config(['database.connections.pgsql.username' => $role, 'database.connections.pgsql.password' => $password]);
            DB::purge('pgsql');
            $identity = DB::selectOne('SELECT current_user AS actor, session_user AS login');
            $this->assertSame($role, $identity->actor);
            $this->assertSame($role, $identity->login);
            $flags = DB::selectOne('SELECT rolsuper,rolcreatedb,rolcreaterole,rolinherit,rolbypassrls FROM pg_roles WHERE rolname = current_user');
            foreach ((array) $flags as $flag) {
                $this->assertFalse($flag);
            }
            $this->assertFalse(DB::selectOne("SELECT pg_has_role(current_user, ?, 'MEMBER') AS value", [$original['username']])->value);
            $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_class WHERE relnamespace = 'public'::regnamespace AND relowner = current_user::regrole")->n);
            $this->assertFalse(DB::selectOne("SELECT has_schema_privilege(current_user, 'public', 'CREATE') AS value")->value);

            $tenant = $this->workspaceFor($actor, 'Runtime synthetic');
            app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());
            $proof = $this->proofFor($actor);
            $roleModel = app(SaveRole::class)->execute($actor, $tenant, null, 'Restricted role', [
                ['code' => 'tenant.view', 'delegable' => false],
            ], $proof);
            $inviteProof = $this->proofFor($actor);
            $invitation = app(CreateTeamInvitation::class)->execute($actor, $tenant, 'runtime-invite@example.invalid', [['role_id' => $roleModel->id, 'scope' => 'tenant']], 7, $inviteProof);
            $this->assertSame('simulated', $invitation->delivery_status);
            app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());
            $party = app(SaveTenantParty::class)->execute($actor, $tenant, null, [
                'kind' => 'organization', 'display_name' => 'Runtime counterpart', 'business_type_codes' => ['publishing'], 'identifiers' => [],
            ]);
            app(SaveTenantParty::class)->execute($actor, $tenant, $party->id, [
                'kind' => 'organization', 'display_name' => 'Updated runtime counterpart', 'business_type_codes' => ['publishing'], 'identifiers' => [], 'version' => 1,
            ]);
            $this->assertDatabaseCount('tenant_party_revisions', 2);
            SyntheticDocumentEnvironment::configure();
            $version = app(UploadDocumentVersion::class)->execute($actor, $tenant, null, [
                'title' => 'Runtime document', 'upload_key' => (string) Str::uuid(),
            ], UploadedFile::fake()->createWithContent('runtime.txt', 'Synthetic runtime document'));
            $this->assertSame('clean', $version->fresh()->scan_status);
            $response = $this->actingAs($actor)->get(route('documents.download', [$tenant, $version->document_id, $version->id]));
            $response->assertOk();
            $this->assertSame('Synthetic runtime document', $response->streamedContent());
            $this->assertDatabaseCount('audit_events', 10);
            $before = DB::table('audit_events')->orderBy('id')->get()->toJson();

            $forbidden = [
                "UPDATE document_versions SET sha256 = 'forged'",
                'DELETE FROM document_versions',
                'TRUNCATE document_versions',
                'ALTER TABLE document_versions DISABLE TRIGGER document_version_immutable',
                'DROP FUNCTION protect_document_version()',
                "UPDATE tenant_party_revisions SET snapshot = '{}'",
                'DELETE FROM tenant_party_revisions',
                'TRUNCATE tenant_party_revisions',
                'ALTER TABLE tenant_party_revisions DISABLE TRIGGER party_revision_immutable',
                'DROP FUNCTION reject_party_revision_mutation()',
                "UPDATE business_types SET name = 'forged'",
                "UPDATE audit_events SET action = 'forged'",
                'DELETE FROM audit_events',
                'TRUNCATE audit_events',
                'ALTER TABLE audit_events DISABLE TRIGGER audit_append_only',
                'DROP TABLE audit_events',
                'DROP FUNCTION reject_audit_mutation()',
                'SET ROLE "'.str_replace('"', '""', $original['username']).'"',
                'SET session_replication_role = replica',
                "INSERT INTO audit_events (id,tenant_id,actor_id,actor_type,action,resource_type,resource_id,changes,occurred_at) VALUES ('".Str::uuid()."','{$tenant->id}',NULL,'migration','forged','role','".Str::uuid()."','{}',now())",
            ];
            foreach ($forbidden as $sql) {
                try {
                    DB::statement($sql);
                    $this->fail('Runtime accepted a forbidden operation: '.$sql);
                } catch (QueryException $exception) {
                    $this->assertSame('42501', $exception->errorInfo[0], $sql);
                }
            }
            $this->assertSame($before, DB::table('audit_events')->orderBy('id')->get()->toJson());
            $this->assertSame($role, DB::selectOne('SELECT session_user AS name')->name);
        } finally {
            app(TenantContext::class)->clear();
            Storage::disk('documents')->deleteDirectory('tenants');
            DB::purge('pgsql');
            config(['database.connections.pgsql' => $original]);
            if ($created) {
                // The ephemeral role owns no objects. Revoke its grants, then
                // remove only this generated test login, never the migration role.
                $migration->unprepared("DROP OWNED BY {$role}; DROP ROLE {$role}");
            }
            DB::purge('governance_migration');
            $this->artisan('migrate:fresh')->assertSuccessful();
            RefreshDatabaseState::$migrated = false;
        }
    }
}
