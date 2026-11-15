<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\MemberRolesRequest;
use App\Http\Requests\Workspaces\MemberStatusRequest;
use App\Http\Requests\Workspaces\RoleRequest;
use App\Http\Requests\Workspaces\TransferOwnershipRequest;
use App\Modules\IdentityAccess\Actions\AssignMemberRoles;
use App\Modules\IdentityAccess\Actions\ChangeMembershipStatus;
use App\Modules\IdentityAccess\Actions\DeleteRole;
use App\Modules\IdentityAccess\Actions\SaveRole;
use App\Modules\IdentityAccess\Actions\TransferOwnership;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\GovernanceQueries;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GovernanceController extends Controller
{
    public function roles(Request $request, Tenant $workspace, GovernanceQueries $queries, WorkspaceQueries $workspaces): Response
    {
        return Inertia::render('workspaces/Roles', [
            'workspace' => $workspaces->projection($workspace),
            'roles' => $queries->roles($request->user(), $workspace, $request->string('search')->toString()),
            'capabilities' => $queries->capabilities($request->user(), $workspace),
            'mfaReady' => (bool) ($request->user()->two_factor_secret && $request->user()->two_factor_confirmed_at),
        ]);
    }

    public function storeRole(RoleRequest $request, Tenant $workspace, SaveRole $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, null, $request->validated('name'), $request->validated('permissions'), $request->proof());

        return $this->afterChange($workspace, 'manageRoles', 'workspaces.roles');
    }

    public function updateRole(RoleRequest $request, Tenant $workspace, string $role, SaveRole $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $role, $request->validated('name'), $request->validated('permissions'), $request->proof());

        return $this->afterChange($workspace, 'manageRoles', 'workspaces.roles');
    }

    public function deleteRole(RoleRequest $request, Tenant $workspace, string $role, DeleteRole $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $role, $request->proof());

        return $this->afterChange($workspace, 'manageRoles', 'workspaces.roles');
    }

    public function assignRoles(MemberRolesRequest $request, Tenant $workspace, string $membership, AssignMemberRoles $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $membership, $request->validated('assignments'), $request->proof());

        return $this->afterChange($workspace, 'viewMembers', 'workspaces.members');
    }

    public function changeStatus(MemberStatusRequest $request, Tenant $workspace, string $membership, ChangeMembershipStatus $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $membership, $request->validated('status'), $request->proof());

        return $this->afterChange($workspace, 'viewMembers', 'workspaces.members');
    }

    public function transfer(TransferOwnershipRequest $request, Tenant $workspace, TransferOwnership $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $request->validated('membership_id'), $request->proof());

        // The previous owner may have lost all workspace authority.
        return to_route('workspaces.index')->with('status', 'Gobierno del espacio transferido.');
    }

    private function afterChange(Tenant $workspace, string $ability, string $route): RedirectResponse
    {
        return Gate::allows('view', $workspace) && Gate::allows($ability, $workspace)
            ? to_route($route, $workspace) : to_route('workspaces.index');
    }
}
