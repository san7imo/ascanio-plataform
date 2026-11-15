<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokeMembership
{
    public function __construct(private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $membershipId): void
    {
        DB::transaction(function () use ($actor, $tenant, $membershipId) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('revokeMember', $tenant);
            $membership = $tenant->memberships()->lockForUpdate()->findOrFail($membershipId);

            if ($membership->status === 'revoked') {
                return;
            }

            if ($membership->is_owner && ! $tenant->memberships()->where('is_owner', true)->where('status', 'active')
                ->whereKeyNot($membership->id)->whereHas('user', fn ($query) => $query->where('status', 'active'))->exists()) {
                throw ValidationException::withMessages(['membership' => 'El espacio debe conservar al menos un propietario activo.']);
            }

            $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
            // No permission snapshot is cached. Every request rechecks this row,
            // including other sessions, while independent memberships remain valid.
            $this->audit->execute($actor, $tenant, 'membership.revoked', 'membership', $membership->id, ['status' => 'revoked']);
        }, 3);
    }
}
