<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GovernanceTransaction
{
    public function execute(User $actor, Tenant $tenant, string $ability, Closure $mutation, array $participantIds = []): mixed
    {
        return DB::transaction(function () use ($actor, $tenant, $ability, $mutation, $participantIds) {
            // Transfer locks both known participant accounts in deterministic order.
            // This is bounded account-security access, never a global directory.
            $accounts = User::query()->whereIn('id', [$actor->id, ...$participantIds])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $accounts->get($actor->id);
            abort_unless($actor, 403);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize($ability, $tenant);

            return $mutation($actor, $tenant, $accounts);
        }, 3);
    }
}
