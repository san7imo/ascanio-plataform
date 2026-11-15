<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferOwnership
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $membershipId, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        Gate::forUser($actor)->authorize('transferOwnership', $tenant);
        $recipientId = $tenant->memberships()->findOrFail($membershipId)->user_id;

        $this->transaction->execute($actor, $tenant, 'transferOwnership', function ($actor, $tenant, $accounts) use ($membershipId, $recipientId, $proof) {
            $source = $tenant->memberships()->where('user_id', $actor->id)->lockForUpdate()->sole();
            $target = $tenant->memberships()->lockForUpdate()->findOrFail($membershipId);
            $recipient = $accounts->get($recipientId);
            if ($target->user_id !== $recipientId || ! $recipient || $source->id === $target->id || $target->is_owner
                || $target->status !== 'active' || $recipient->status !== 'active' || ! $recipient->hasVerifiedEmail()
                || ! $recipient->two_factor_secret || ! $recipient->two_factor_confirmed_at) {
                throw ValidationException::withMessages(['membership' => 'Elige otro miembro activo con cuenta activa, correo verificado y MFA confirmado.']);
            }
            $role = Role::query()->where('tenant_id', $tenant->id)->where('system_kind', 'owner')->sole();
            $this->confirm->execute($actor, $proof);
            $target->update(['is_owner' => true]);
            DB::table('membership_roles')->updateOrInsert([
                'tenant_id' => $tenant->id, 'membership_id' => $target->id, 'role_id' => $role->id,
            ], ['access_scope' => 'tenant']);
            $source->update(['is_owner' => false]);
            DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $source->id)->where('role_id', $role->id)->delete();
            $this->audit->execute($actor, $tenant, 'ownership.transferred', 'membership', $target->id, [
                'from_membership_id' => $source->id, 'to_membership_id' => $target->id,
            ]);
        }, [$recipientId]);
    }
}
