<?php

use App\Modules\IdentityAccess\Mail\TeamInvitationMail;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\Support\SyntheticDocumentEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'ascanio_test'
    || config('database.connections.pgsql.url')) {
    throw new RuntimeException('Worker harness requires the dedicated test database.');
}

[$script, $queue, $key, $mode] = $argv;
if ($mode === 'document-scans') {
    SyntheticDocumentEnvironment::configure(false);
}
Notification::fake();
if ($mode === 'mail-failure') {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('synthetic-provider-secret'));
} else {
    Mail::fake();
}

$observe = static function (string $phase) use ($key): void {
    try {
        app(TenantContext::class)->tenant();
        $empty = false;
    } catch (AuthorizationException) {
        $empty = true;
    }
    Redis::rpush($key, json_encode(['phase' => $phase, 'pid' => getmypid(), 'empty' => $empty], JSON_THROW_ON_ERROR));
};
// Observers run after application hooks and resolve the current scoped binding.
Queue::before(static fn () => $observe('before'));
Queue::after(static fn () => $observe('after'));
Queue::exceptionOccurred(static fn () => $observe('exception'));
Queue::failing(static fn () => $observe('failed'));

$exit = Artisan::call('queue:work', [
    'connection' => 'redis', '--queue' => $queue, '--stop-when-empty' => true,
    '--sleep' => 0, '--tries' => 1, '--timeout' => 20, '--max-jobs' => 20,
]);
echo Artisan::output();
Redis::rpush($key, json_encode([
    'phase' => 'summary', 'pid' => getmypid(),
    'invitations' => $mode === 'mail-failure' ? 0 : Mail::sent(TeamInvitationMail::class)->count(),
    'accounts' => array_sum(array_map(static fn ($types) => array_sum(array_map('count', $types)), Notification::sentNotifications())),
], JSON_THROW_ON_ERROR));
exit($exit);
