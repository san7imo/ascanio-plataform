<?php

namespace App\Modules\Parties\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\Parties\Data\PartyInput;
use App\Modules\Parties\Models\TenantParty;
use App\Modules\Parties\Queries\PartyQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveTenantParty
{
    public function __construct(private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, ?string $id, array $values): TenantParty
    {
        return DB::transaction(function () use ($actor, $tenant, $id, $values) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('viewAny', [TenantParty::class, $tenant]);
            $party = $id ? TenantParty::query()->where('tenant_id', $tenant->id)->select(PartyQueries::SUMMARY)->lockForUpdate()->findOrFail($id) : null;
            Gate::forUser($actor)->authorize($party ? 'update' : 'create', $party ? [$party, $tenant] : [TenantParty::class, $tenant]);
            $values = PartyInput::validate($values, $party !== null);
            if ($party && $party->version !== (int) $values['version']) {
                throw ValidationException::withMessages(['version' => 'La ficha cambió desde que la abriste. Recarga y revisa la versión actual antes de guardar.']);
            }
            $attributes = array_intersect_key($values, array_flip(['kind', 'display_name', 'legal_name', 'email', 'phone', 'notes', 'source_note']));
            if ($party) {
                $party->fill([...$attributes, 'version' => $party->version + 1])->save();
            } else {
                $party = TenantParty::create([...$attributes, 'tenant_id' => $tenant->id, 'created_by' => $actor->id, 'version' => 1, 'source' => 'manual', 'verification_status' => 'provisional']);
            }
            DB::table('party_identifiers')->where('tenant_id', $tenant->id)->where('tenant_party_id', $party->id)->delete();
            foreach ($values['identifiers'] as $identifier) {
                DB::table('party_identifiers')->insert([...$identifier, 'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'tenant_party_id' => $party->id]);
            }
            DB::table('tenant_party_business_types')->where('tenant_id', $tenant->id)->where('tenant_party_id', $party->id)->delete();
            $activities = DB::table('business_types')->whereIn('code', $values['business_type_codes'])->orderBy('code')->get(['id', 'code', 'name']);
            foreach ($activities as $activity) {
                DB::table('tenant_party_business_types')->insert(['tenant_id' => $tenant->id, 'tenant_party_id' => $party->id, 'business_type_id' => $activity->id]);
            }
            $snapshot = [...$attributes, 'source' => 'manual', 'verification_status' => 'provisional',
                'identifiers' => $values['identifiers'], 'activities' => $activities->map(fn ($activity) => ['code' => $activity->code, 'name' => $activity->name])->all()];
            $revisionId = (string) Str::uuid();
            DB::table('tenant_party_revisions')->insert([
                'id' => $revisionId, 'tenant_id' => $tenant->id, 'tenant_party_id' => $party->id, 'version' => $party->version,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'recorded_by' => $actor->id, 'recorded_at' => now(),
            ]);
            $this->audit->execute($actor, $tenant, $id ? 'party.updated' : 'party.created', 'tenant_party', $party->id, ['version' => $party->version, 'revision_id' => $revisionId]);

            return $party;
        }, 3);
    }
}
