<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class WorkspaceQueries
{
    public function __construct(private WorkspaceAccess $access) {}

    public function workspaces(User $user, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('create', Tenant::class);

        // Account-bounded workspace discovery is the explicit exception to
        // requiring a selected tenant; only this user's active memberships count.
        return Tenant::query()->where('status', 'active')
            ->whereIn('id', $this->access->capableMemberships($user, 'tenant.view')->select('tenant_memberships.tenant_id'))
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', '%'.mb_substr($search, 0, 120).'%'))
            ->select(['id', 'name'])->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
    }

    public function members(User $user, Tenant $tenant, string $search = ''): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('viewMembers', $tenant);

        return $tenant->memberships()->with('user:id,name')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'ilike', '%'.mb_substr($search, 0, 120).'%')))
            ->orderBy('accepted_at')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn ($member) => [
                'id' => $member->id, 'name' => $member->user->name,
                'status' => $member->status, 'is_owner' => $member->is_owner,
                'accepted_at' => $member->accepted_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
            ]);
    }

    public function projection(Tenant $tenant): array
    {
        Gate::authorize('view', $tenant);

        return [
            'id' => $tenant->id, 'name' => $tenant->name, 'locale' => $tenant->locale, 'timezone' => $tenant->timezone,
            'can_update' => Gate::allows('update', $tenant), 'can_view_members' => Gate::allows('viewMembers', $tenant),
            'can_revoke_members' => Gate::allows('revokeMember', $tenant),
        ];
    }
}
