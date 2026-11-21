<?php

namespace App\Modules\Parties\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\Parties\Models\TenantParty;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PartyQueries
{
    public const SUMMARY = ['id', 'tenant_id', 'kind', 'display_name', 'version', 'source', 'verification_status', 'created_at', 'updated_at'];

    public function __construct(private RecordAudit $audit) {}

    public function listing(User $actor, Tenant $tenant, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', [TenantParty::class, $tenant]);

        // Only the visible local display name is searchable, including counts.
        return TenantParty::query()->where('tenant_id', $tenant->id)->select(self::SUMMARY)
            ->when($search !== '', fn ($query) => $query->where('display_name', 'ilike', '%'.mb_substr($search, 0, 120).'%'))
            ->orderBy('display_name')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn ($party) => $this->summary($party, $tenant));
    }

    public function find(User $actor, Tenant $tenant, string $id): TenantParty
    {
        Gate::forUser($actor)->authorize('viewAny', [TenantParty::class, $tenant]);
        $party = TenantParty::query()->where('tenant_id', $tenant->id)->select(self::SUMMARY)->findOrFail($id);
        Gate::forUser($actor)->authorize('view', [$party, $tenant]);

        return $party;
    }

    public function detail(User $actor, Tenant $tenant, TenantParty $party): array
    {
        Gate::forUser($actor)->authorize('view', [$party, $tenant]);
        $result = $this->summary($party, $tenant);
        $private = Gate::forUser($actor)->allows('viewPrivate', [$party, $tenant]);
        $result['can_view_private'] = $private;
        $result['can_update'] = Gate::forUser($actor)->allows('update', [$party, $tenant]);
        if ($private) {
            $full = TenantParty::query()->where('tenant_id', $tenant->id)->findOrFail($party->id);
            $result['private'] = [
                'legal_name' => $full->legal_name, 'email' => $full->email, 'phone' => $full->phone,
                'notes' => $full->notes, 'source_note' => $full->source_note,
                'identifiers' => DB::table('party_identifiers')->where('tenant_id', $tenant->id)->where('tenant_party_id', $party->id)
                    ->orderBy('scheme')->orderBy('value')->get(['scheme', 'value', 'issuer'])->all(),
                'activities' => DB::table('tenant_party_business_types')->join('business_types', 'business_types.id', '=', 'tenant_party_business_types.business_type_id')
                    ->where('tenant_party_business_types.tenant_id', $tenant->id)->where('tenant_party_id', $party->id)
                    ->orderBy('business_types.code')->get(['business_types.code', 'business_types.name'])->all(),
            ];
            $this->audit->execute($actor, $tenant, 'party.private_viewed', 'tenant_party', $party->id, ['version' => $full->version]);
        }

        return $result;
    }

    public function revisions(User $actor, Tenant $tenant, TenantParty $party): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewPrivate', [$party, $tenant]);

        return DB::table('tenant_party_revisions')->where('tenant_id', $tenant->id)->where('tenant_party_id', $party->id)
            ->orderByDesc('version')->paginate(5, ['id', 'version', 'snapshot', 'recorded_at'])->withQueryString()
            ->through(fn ($revision) => [
                'id' => $revision->id, 'version' => $revision->version,
                'recorded_at' => CarbonImmutable::parse($revision->recorded_at)->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'snapshot' => json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR),
            ]);
    }

    public function activities(User $actor, Tenant $tenant): array
    {
        Gate::forUser($actor)->authorize('viewAny', [TenantParty::class, $tenant]);

        return DB::table('business_types')->orderBy('name')->get(['code', 'name'])->all();
    }

    private function summary(TenantParty $party, Tenant $tenant): array
    {
        return [
            'id' => $party->id, 'kind' => $party->kind, 'display_name' => $party->display_name,
            'version' => $party->version, 'source' => $party->source, 'verification_status' => $party->verification_status,
            'updated_at' => $party->updated_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
        ];
    }
}
