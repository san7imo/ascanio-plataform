<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

trait WorkspaceFixtures
{
    protected function workspaceFor(User $user, string $name = 'Espacio sintético'): Tenant
    {
        return app(CreateWorkspace::class)->execute($user, ['name' => $name, 'locale' => 'es', 'timezone' => 'America/Bogota'], (string) Str::uuid());
    }

    protected function memberFor(Tenant $tenant, User $user, array $capabilities = ['tenant.view'], bool $owner = false, string $scope = 'tenant', bool $delegable = false): Membership
    {
        return DB::transaction(function () use ($tenant, $user, $capabilities, $owner, $scope, $delegable) {
            $member = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'is_owner' => $owner, 'accepted_at' => now()]);
            $role = $owner ? Role::query()->where('tenant_id', $tenant->id)->where('system_kind', 'owner')->sole()
                : Role::create(['tenant_id' => $tenant->id, 'code' => (string) Str::uuid(), 'name' => 'Rol sintético']);

            foreach ($owner ? [] : $capabilities as $code) {
                $permission = Permission::firstOrCreate(['code' => $code]);
                DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $permission->id, 'is_delegable' => $delegable]);
            }

            DB::table('membership_roles')->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $role->id, 'access_scope' => $scope]);

            return $member;
        });
    }

    protected function enableMfa(User $user): User
    {
        $user = $user->fresh();
        $user->forceFill([
            'two_factor_secret' => $user->two_factor_secret ?: encrypt(app(Google2FA::class)->generateSecretKey()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    protected function sensitiveData(User $user): array
    {
        $user = $this->enableMfa($user);
        $clock = app(Google2FA::class);
        if (! $clock instanceof TestTotpClock) {
            $clock = new TestTotpClock;
            app()->instance(Google2FA::class, $clock);
        }
        $clock->advance((int) $user->last_sensitive_totp_step);

        return ['password' => 'password', 'code' => $clock->getCurrentOtp(decrypt($user->two_factor_secret))];
    }

    protected function proofFor(User $user): SensitiveProof
    {
        $data = $this->sensitiveData($user);

        return new SensitiveProof($data['password'], $data['code']);
    }
}
