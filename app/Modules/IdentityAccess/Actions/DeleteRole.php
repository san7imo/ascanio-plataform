<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DeleteRole
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private CheckDelegation $delegation, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $roleId, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        $this->transaction->execute($actor, $tenant, 'manageRoles', function ($actor, $tenant) use ($roleId, $proof) {
            $role = Role::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($roleId);
            abort_if($role->system_kind !== null, 403);
            $this->delegation->role($actor, $tenant, $role->id);
            $this->confirm->execute($actor, $proof);
            Membership::query()->where('tenant_id', $tenant->id)
                ->whereIn('id', DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->select('membership_id'))->increment('access_version');
            DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->delete();
            DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->delete();
            DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->update(['role_id' => null]);
            $role->delete();
            $this->audit->execute($actor, $tenant, 'role.deleted', 'role', $roleId, ['fields' => ['permissions', 'assignments']]);
        });
    }
}
