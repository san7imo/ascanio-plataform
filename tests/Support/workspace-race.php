<?php

// Subprocess harness for synthetic PostgreSQL races. Never read a real database.
use App\Models\User;
use App\Modules\IdentityAccess\Actions\AcceptTeamInvitation;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use App\Modules\IdentityAccess\Actions\ManageTeamInvitation;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Actions\RevokeMembership;
use App\Modules\IdentityAccess\Actions\TransferOwnership;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use App\Modules\Parties\Actions\SaveTenantParty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'ascanio_test'
    || config('database.connections.pgsql.url')) {
    throw new RuntimeException('Concurrency harness requires the dedicated test database.');
}

[$script, $operation, $userId, $targetId, $value, $barrier, $worker] = $argv;
$app->bind(RecordAudit::class, fn () => new class extends RecordAudit
{
    public function execute(User $actor, Tenant $tenant, string $action, string $type, string $id, array $changes): void
    {
        parent::execute($actor, $tenant, $action, $type, $id, $changes);
        usleep(150000); // Hold mutation locks long enough to overlap the other worker.
    }
});
file_put_contents($barrier.'/'.$worker.'.ready', 'ready');
$deadline = microtime(true) + 15;

while (! is_file($barrier.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race barrier timed out.');
    }

    usleep(10000);
}

try {
    $user = User::findOrFail($userId);

    if ($operation === 'edit-party') {
        $tenant = Tenant::findOrFail($targetId);
        $app->make(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $userId)->sole());
        $party = $app->make(SaveTenantParty::class)->execute($user, $tenant, $value, [
            'kind' => 'person', 'display_name' => 'Edición concurrente '.$worker,
            'business_type_codes' => [], 'identifiers' => [], 'version' => 1,
        ]);
        echo json_encode(['result' => 'ok', 'id' => $party->id]);
    } elseif ($operation === 'accept-invite') {
        $invitation = TeamInvitation::query()->where('tenant_id', $targetId)->findOrFail($value);
        $app->make(AcceptTeamInvitation::class)->execute($user, ['id' => $value, 'hash' => $invitation->token_hash, 'until' => time() + 3600]);
        echo json_encode(['result' => 'ok']);
    } elseif ($operation === 'revoke-invite') {
        $tenant = Tenant::findOrFail($targetId);
        $app->make(TenantContext::class)->establish($tenant, $tenant->memberships()->where('user_id', $userId)->sole());
        $proof = new SensitiveProof('password', (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)));
        $app->make(ManageTeamInvitation::class)->execute($user, $tenant, $value, false, $proof);
        echo json_encode(['result' => 'ok']);
    } elseif ($operation === 'create') {
        $tenant = $app->make(CreateWorkspace::class)->execute($user, ['name' => 'Concurrent synthetic', 'locale' => 'es', 'timezone' => 'UTC'], $value);
        echo json_encode(['result' => 'ok', 'id' => $tenant->id]);
    } elseif (in_array($operation, ['revoke', 'transfer'], true)) {
        $tenant = Tenant::findOrFail($targetId);
        $member = $tenant->memberships()->where('user_id', $userId)->firstOrFail();
        $app->make(TenantContext::class)->establish($tenant, $member);
        $proof = new SensitiveProof('password', (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)));
        $app->make($operation === 'transfer' ? TransferOwnership::class : RevokeMembership::class)->execute($user, $tenant, $value, $proof);
        echo json_encode(['result' => 'ok']);
    } elseif ($operation === 'suspend') {
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => 'suspended'])->save();
            usleep(150000);
        });
        echo json_encode(['result' => 'ok']);
    } else {
        throw new RuntimeException('Unknown race operation.');
    }
} catch (AuthorizationException|ValidationException $exception) {
    echo json_encode(['result' => 'denied']);
} catch (PDOException $exception) {
    if ($exception->errorInfo[0] !== '23514') {
        throw $exception;
    }

    echo json_encode(['result' => 'denied']);
} finally {
    $app->make(TenantContext::class)->clear();
}
