<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\Party;
use App\Modules\IdentityAccess\Models\Permission;
use App\Modules\IdentityAccess\Models\Person;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateWorkspace
{
    public const CAPABILITIES = CapabilityCatalog::OWNER;

    public function __construct(private RecordAudit $audit, private WorkspaceAccess $access) {}

    public function execute(User $actor, array $values, string $token): Tenant
    {
        return DB::transaction(function () use ($actor, $values, $token) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('create', Tenant::class);
            $settings = ['name' => $values['name'], 'locale' => $values['locale'], 'timezone' => $values['timezone']];
            $keyHash = hash('sha256', $token);
            $payloadHash = hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR));
            $existing = Tenant::query()->where('created_by', $actor->id)->where('creation_key_hash', $keyHash)->first();

            if ($existing) {
                abort_unless($this->access->capableMemberships($actor, 'tenant.view')
                    ->where('tenant_memberships.tenant_id', $existing->id)->exists(), 404);

                if (! hash_equals($existing->creation_payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['creation_token' => 'Este formulario ya se usó con otros datos. Abre uno nuevo.']);
                }

                return $existing;
            }

            if (! $actor->person_id) {
                $party = Party::create(['kind' => 'person', 'status' => 'declared', 'provenance' => 'self_declared_account']);
                Person::create(['party_id' => $party->id]);
                $actor->forceFill(['person_id' => $party->id])->save();
            } else {
                abort_unless(Party::query()->whereKey($actor->person_id)->where('kind', 'person')->where('status', 'declared')->exists(), 403);
            }

            $tenant = Tenant::create([
                ...$settings, 'operator_party_id' => $actor->person_id, 'created_by' => $actor->id,
                'creation_key_hash' => $keyHash, 'creation_payload_hash' => $payloadHash,
                'slug' => (string) Str::uuid(), 'status' => 'active',
            ]);
            $membership = Membership::create([
                'tenant_id' => $tenant->id, 'user_id' => $actor->id,
                'is_owner' => true, 'status' => 'active', 'accepted_at' => now(),
            ]);
            $role = Role::create(['tenant_id' => $tenant->id, 'code' => 'owner', 'name' => 'Propietario del espacio', 'system_kind' => 'owner']);

            foreach (self::CAPABILITIES as $code) {
                $permission = Permission::firstOrCreate(['code' => $code]);
                DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $permission->id, 'is_delegable' => in_array($code, CapabilityCatalog::DELEGABLE, true)]);
            }

            DB::table('membership_roles')->insert([
                'tenant_id' => $tenant->id, 'membership_id' => $membership->id,
                'role_id' => $role->id, 'access_scope' => 'tenant',
            ]);
            $this->audit->execute($actor, $tenant, 'tenant.created', 'tenant', $tenant->id, ['fields' => ['name', 'locale', 'timezone']]);
            $this->audit->execute($actor, $tenant, 'membership.created', 'membership', $membership->id, ['status' => 'active', 'is_owner' => true]);

            return $tenant;
        }, 3);
    }
}
