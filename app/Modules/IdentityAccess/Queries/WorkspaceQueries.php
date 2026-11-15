<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class WorkspaceQueries
{
    public function __construct(private WorkspaceAccess $access, private GovernanceQueries $governance) {}

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

        $assign = Gate::forUser($user)->allows('assignRoles', $tenant);
        $transfer = Gate::forUser($user)->allows('transferOwnership', $tenant);

        return $tenant->memberships()->with($transfer ? 'user:id,name,status,email_verified_at,two_factor_confirmed_at,two_factor_secret' : 'user:id,name')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'ilike', '%'.mb_substr($search, 0, 120).'%')))
            ->orderBy('accepted_at')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn ($member) => [
                'id' => $member->id, 'name' => $member->user->name,
                'status' => $member->status, 'is_owner' => $member->is_owner,
                'can_assign' => $this->governance->canAssign($user, $tenant, $member),
                'can_change_status' => $this->governance->canChangeStatus($user, $tenant, $member),
                'accepted_at' => $member->accepted_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                ...($assign ? ['assignments' => $this->governance->assignments($user, $tenant, $member->id)] : []),
                ...($transfer ? ['can_receive_ownership' => ! $member->is_owner && $member->status === 'active'
                    && $member->user_id !== $user->id && $member->user->status === 'active' && $member->user->hasVerifiedEmail()
                    && (bool) $member->user->two_factor_confirmed_at && (bool) $member->user->two_factor_secret] : []),
            ]);
    }

    public function projection(Tenant $tenant): array
    {
        Gate::authorize('view', $tenant);

        return [
            'id' => $tenant->id, 'name' => $tenant->name, 'locale' => $tenant->locale, 'timezone' => $tenant->timezone,
            'can_update' => Gate::allows('update', $tenant), 'can_view_members' => Gate::allows('viewMembers', $tenant),
            'can_revoke_members' => Gate::allows('revokeMember', $tenant),
            'can_invite_members' => Gate::allows('inviteMembers', $tenant),
            'can_manage_roles' => Gate::allows('manageRoles', $tenant),
            'can_assign_roles' => Gate::allows('assignRoles', $tenant),
            'can_transfer_ownership' => Gate::allows('transferOwnership', $tenant),
        ];
    }
}
