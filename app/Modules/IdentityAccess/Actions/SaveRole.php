<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveRole
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private CheckDelegation $delegation, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, ?string $roleId, string $name, array $permissions, #[\SensitiveParameter] SensitiveProof $proof): Role
    {
        return $this->transaction->execute($actor, $tenant, 'manageRoles', function ($actor, $tenant) use ($roleId, $name, $permissions, $proof) {
            $role = $roleId ? Role::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($roleId) : null;
            abort_if($role?->system_kind !== null, 403, 'El rol de propietario se administra mediante transferencia.');

            if ($role) {
                $this->delegation->role($actor, $tenant, $role->id);
            }

            $this->delegation->codes($actor, $tenant, array_column($permissions, 'code'));
            $this->confirm->execute($actor, $proof);
            $role ??= new Role(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid()]);
            $created = ! $role->exists;
            $role->name = $name;
            $role->save();
            Membership::query()->where('tenant_id', $tenant->id)
                ->whereIn('id', DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->select('membership_id'))->increment('access_version');
            DB::table('role_permissions')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->delete();

            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert([
                    'tenant_id' => $tenant->id, 'role_id' => $role->id,
                    'permission_id' => Permission::query()->where('code', $permission['code'])->sole()->id,
                    'is_delegable' => (bool) $permission['delegable'],
                ]);
            }

            $this->audit->execute($actor, $tenant, $created ? 'role.created' : 'role.updated', 'role', $role->id, ['fields' => ['name', 'permissions'], 'permissions' => $permissions]);

            return $role;
        });
    }
}
