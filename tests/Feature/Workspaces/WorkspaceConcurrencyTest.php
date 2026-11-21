<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use App\Modules\IdentityAccess\Actions\InvitationRoles;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Actions\SaveTenantParty;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\WorkspaceFixtures;
use Tests\TestCase;

class WorkspaceConcurrencyTest extends TestCase
{
    use WorkspaceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Real commits must be visible to subprocesses. A dedicated fresh test
        // schema replaces transactional fixtures for these race tests.
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

    private function race(array $operations): array
    {
        $directory = sys_get_temp_dir().'/ascanio-race-'.Str::uuid();
        mkdir($directory, 0700);
        $connection = config('database.connections.pgsql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'ascanio_test', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        $processes = [];

        try {
            foreach ($operations as $index => $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/workspace-race.php'), ...$operation, $directory, (string) $index], base_path(), $env, timeout: 25);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;

            while (count(glob($directory.'/*.ready')) !== count($operations)) {
                if (microtime(true) > $deadline) {
                    $this->fail('Subprocesses failed to reach the race barrier.');
                }

                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail($process->getErrorOutput());
                    }
                }

                usleep(10000);
            }

            file_put_contents($directory.'/go', 'go');
            $results = [];

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertJson($process->getOutput(), $process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    public function test_two_concurrent_creations_with_one_key_create_one_atomic_workspace(): void
    {
        $user = User::factory()->create();
        $token = (string) Str::uuid();
        $operation = ['create', $user->id, '-', $token];
        $results = $this->race([$operation, $operation]);
        $this->assertSame('ok', $results[0]['result']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('tenant_memberships', 1);
        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_concurrent_owner_revocations_preserve_one_active_owner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $tenant = $this->workspaceFor($first);
        $firstMembership = $tenant->memberships()->sole();
        $secondMembership = $this->memberFor($tenant, $second, CreateWorkspace::CAPABILITIES, owner: true);
        $this->enableMfa($first);
        $this->enableMfa($second);
        $results = $this->race([
            ['revoke', $first->id, $tenant->id, $secondMembership->id],
            ['revoke', $second->id, $tenant->id, $firstMembership->id],
        ]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame(1, $tenant->memberships()->where('is_owner', true)->where('status', 'active')->count());
    }

    public function test_concurrent_account_suspensions_cannot_disable_both_owners(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $tenant = $this->workspaceFor($first);
        $this->memberFor($tenant, $second, CreateWorkspace::CAPABILITIES, owner: true);
        $results = $this->race([
            ['suspend', $first->id, $tenant->id, '-'],
            ['suspend', $second->id, $tenant->id, '-'],
        ]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame(1, User::query()->whereIn('id', [$first->id, $second->id])->where('status', 'active')->count());
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_foundation_migrations_roll_back_and_reapply_without_rewriting_scaffold_history(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 4])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'status'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('tenants'));
    }

    public function test_two_concurrent_transfers_from_one_owner_cannot_duplicate_governance(): void
    {
        $owner = $this->enableMfa(User::factory()->create());
        $tenant = $this->workspaceFor($owner);
        $first = $this->memberFor($tenant, $this->enableMfa(User::factory()->create()));
        $second = $this->memberFor($tenant, $this->enableMfa(User::factory()->create()));
        $results = $this->race([
            ['transfer', $owner->id, $tenant->id, $first->id],
            ['transfer', $owner->id, $tenant->id, $second->id],
        ]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame(1, $tenant->memberships()->where('is_owner', true)->where('status', 'active')->count());
        $this->assertDatabaseCount('audit_events', 3);
    }

    public function test_transfer_racing_recipient_self_revocation_preserves_an_active_owner(): void
    {
        $owner = $this->enableMfa(User::factory()->create());
        $tenant = $this->workspaceFor($owner);
        $recipient = $this->enableMfa(User::factory()->create());
        $target = $this->memberFor($tenant, $recipient, ['tenant.view', 'tenant.members.revoke'], delegable: true);
        $results = $this->race([
            ['transfer', $owner->id, $tenant->id, $target->id],
            ['revoke', $recipient->id, $tenant->id, $target->id],
        ]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame(1, $tenant->memberships()->where('is_owner', true)->where('status', 'active')->count());
    }

    public function test_two_party_edits_of_one_version_preserve_one_winner_and_both_historical_snapshots(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->sole());
        $party = app(SaveTenantParty::class)->execute($owner, $tenant, null, [
            'kind' => 'person', 'display_name' => 'Nombre original', 'business_type_codes' => [], 'identifiers' => [],
        ]);
        app(TenantContext::class)->clear();
        $operation = ['edit-party', $owner->id, $tenant->id, $party->id];
        $results = $this->race([$operation, $operation]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame(2, $party->fresh()->version);
        $this->assertDatabaseCount('tenant_party_revisions', 2);
        $first = DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->where('version', 1)->sole();
        $this->assertSame('Nombre original', json_decode($first->snapshot, true)['display_name']);
        $this->assertSame(1, DB::table('audit_events')->where('tenant_id', $tenant->id)->where('action', 'party.updated')->count());
    }

    private function invitationFixture(User $owner, $tenant, User $recipient)
    {
        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id, 'inviter_id' => $owner->id, 'recipient_email' => $recipient->email,
            'source_membership_id' => $source = $tenant->memberships()->where('user_id', $owner->id)->sole()->id,
            'source_access_version' => $tenant->memberships()->whereKey($source)->sole()->access_version,
            'token_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now()->addDay(),
        ]);
        $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Race viewer']);
        DB::table('role_permissions')->insert([
            'tenant_id' => $tenant->id, 'role_id' => $role->id,
            'permission_id' => Permission::query()->where('code', 'tenant.view')->sole()->id,
        ]);
        app(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $owner->id)->sole());
        app(InvitationRoles::class)->attach($owner, $tenant, $invitation, [['role_id' => $role->id, 'scope' => 'tenant']]);
        app(TenantContext::class)->clear();

        return $invitation;
    }

    public function test_two_invitation_acceptances_consume_once_and_create_one_membership(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        $invitation = $this->invitationFixture($owner, $tenant, $recipient);
        $operation = ['accept-invite', $recipient->id, $tenant->id, $invitation->id];
        $results = $this->race([$operation, $operation]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertSame(1, $tenant->memberships()->where('user_id', $recipient->id)->count());
        $this->assertDatabaseCount('membership_roles', 2);
    }

    public function test_invitation_acceptance_racing_revocation_has_one_consistent_outcome(): void
    {
        $owner = $this->enableMfa(User::factory()->create());
        $tenant = $this->workspaceFor($owner);
        $recipient = User::factory()->create();
        $invitation = $this->invitationFixture($owner, $tenant, $recipient);
        $results = $this->race([
            ['accept-invite', $recipient->id, $tenant->id, $invitation->id],
            ['revoke-invite', $owner->id, $tenant->id, $invitation->id],
        ]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);
        $this->assertSame(['denied', 'ok'], $outcomes);
        $this->assertSame($invitation->fresh()->status === 'accepted' ? 1 : 0, $tenant->memberships()->where('user_id', $recipient->id)->count());
    }
}
