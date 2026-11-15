<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
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
        // schema replaces transactional fixtures for these three race tests.
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

    public function test_the_new_migration_rolls_back_and_reapplies_without_rewriting_scaffold_history(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'status'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('tenants'));
    }
}
