<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\Support\RedisProbeJob;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_database_is_the_dedicated_postgresql_database(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('ascanio_test', DB::selectOne('select current_database() as name')->name);
    }

    public function test_redis_cache_and_worker_process_a_real_job(): void
    {
        $key = 'infrastructure-test:'.Str::uuid();
        $queue = 'infrastructure-test-'.Str::uuid();

        try {
            Cache::store('redis')->put($key, 'cached', 60);
            $this->assertSame('cached', Cache::store('redis')->get($key));

            Queue::connection('redis')->push(new RedisProbeJob($key), '', $queue);
            $this->assertSame(0, Artisan::call('queue:work', [
                'connection' => 'redis', '--queue' => $queue,
                '--once' => true, '--tries' => 1, '--timeout' => 30,
            ]));
            $this->assertSame('processed', Redis::get($key));
        } finally {
            Cache::store('redis')->forget($key);
            Redis::del($key);
            Queue::connection('redis')->clear($queue);
        }
    }
}
