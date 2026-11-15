<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeMembershipStatus
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private CheckDelegation $delegation, private WorkspaceAccess $access, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $membershipId, string $status, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        $this->transaction->execute($actor, $tenant, 'revokeMember', function ($actor, $tenant) use ($membershipId, $status, $proof) {
            $member = $tenant->memberships()->lockForUpdate()->findOrFail($membershipId);
            if (! in_array($status, ['active', 'suspended', 'revoked'], true)) {
                throw ValidationException::withMessages(['status' => 'Selecciona un estado válido.']);
            }
            if ($member->is_owner) {
                abort_unless($this->access->isOwner($actor, $tenant), 403);
                if ($status !== 'active' && ! $tenant->memberships()->where('is_owner', true)->where('status', 'active')
                    ->whereKeyNot($member->id)->whereHas('user', fn ($query) => $query->where('status', 'active'))->exists()) {
                    throw ValidationException::withMessages(['membership' => 'El espacio debe conservar al menos un propietario activo.']);
                }
            } else {
                // Reactivation must not resurrect authority the actor cannot delegate.
                foreach (DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->pluck('role_id') as $roleId) {
                    $this->delegation->role($actor, $tenant, $roleId);
                }
            }
            $this->confirm->execute($actor, $proof);
            if ($member->status === $status) {
                return;
            }
            $previous = $member->status;
            $member->increment('access_version');
            $member->update(['status' => $status, 'revoked_at' => $status === 'revoked' ? now() : null]);
            $this->audit->execute($actor, $tenant, 'membership.'.$status, 'membership', $member->id, ['previous' => $previous, 'status' => $status]);
        });
    }
}
