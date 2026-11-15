<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignMemberRoles
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private CheckDelegation $delegation, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $membershipId, array $assignments, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        $this->transaction->execute($actor, $tenant, 'assignRoles', function ($actor, $tenant) use ($membershipId, $assignments, $proof) {
            $member = $tenant->memberships()->lockForUpdate()->findOrFail($membershipId);
            abort_if($member->is_owner, 403, 'El propietario se administra mediante transferencia.');
            if ($member->status !== 'active') {
                throw ValidationException::withMessages(['membership' => 'Reactiva la membresía antes de cambiar sus roles.']);
            }
            foreach (DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->pluck('role_id') as $roleId) {
                $this->delegation->role($actor, $tenant, $roleId);
            }
            foreach ($assignments as $assignment) {
                $role = Role::query()->where('tenant_id', $tenant->id)->findOrFail($assignment['role_id']);
                abort_if($role->system_kind !== null, 403);
                $this->delegation->role($actor, $tenant, $role->id);
                if (! in_array($assignment['scope'], ['tenant', 'assigned_resources'], true)) {
                    throw ValidationException::withMessages(['assignments' => 'Selecciona un alcance válido.']);
                }
            }
            $this->confirm->execute($actor, $proof);
            $member->increment('access_version');
            DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->delete();
            foreach ($assignments as $assignment) {
                DB::table('membership_roles')->insert([
                    'tenant_id' => $tenant->id, 'membership_id' => $member->id,
                    'role_id' => $assignment['role_id'], 'access_scope' => $assignment['scope'],
                ]);
            }
            $this->audit->execute($actor, $tenant, 'membership.roles_changed', 'membership', $member->id, ['assignments' => $assignments]);
        });
    }
}
