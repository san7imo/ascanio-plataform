<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait WorkspaceFixtures
{
    protected function workspaceFor(User $user, string $name = 'Espacio sintético'): Tenant
    {
        return app(CreateWorkspace::class)->execute($user, ['name' => $name, 'locale' => 'es', 'timezone' => 'America/Bogota'], (string) Str::uuid());
    }

    protected function memberFor(Tenant $tenant, User $user, array $capabilities = ['tenant.view'], bool $owner = false, string $scope = 'tenant'): Membership
    {
        return DB::transaction(function () use ($tenant, $user, $capabilities, $owner, $scope) {
            $member = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'is_owner' => $owner, 'accepted_at' => now()]);
            $role = Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Rol sintético']);

            foreach ($capabilities as $code) {
                $permission = Permission::firstOrCreate(['code' => $code]);
                DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $permission->id]);
            }

            DB::table('membership_roles')->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => $scope]);

            return $member;
        });
    }
}
