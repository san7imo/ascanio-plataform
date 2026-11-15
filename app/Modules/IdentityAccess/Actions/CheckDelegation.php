<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckDelegation
{
    public function __construct(private WorkspaceAccess $access) {}

    public function codes(User $actor, Tenant $tenant, array $codes): void
    {
        if (! $this->access->canDelegateCodes($actor, $tenant, $codes)) {
            throw ValidationException::withMessages(['permissions' => 'Solo puedes administrar capacidades que tengas facultad de delegar en este espacio.']);
        }
    }

    public function role(User $actor, Tenant $tenant, string $roleId): void
    {
        $codes = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.tenant_id', $tenant->id)->where('role_permissions.role_id', $roleId)->pluck('permissions.code')->all();
        $this->codes($actor, $tenant, $codes);
    }
}
