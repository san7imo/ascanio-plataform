<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\ManageInvitationRequest;
use App\Http\Requests\Workspaces\TeamInvitationRequest;
use App\Modules\IdentityAccess\Actions\CreateTeamInvitation;
use App\Modules\IdentityAccess\Actions\ManageTeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\GovernanceQueries;
use App\Modules\IdentityAccess\Queries\TeamInvitationQueries;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    public function index(Request $request, Tenant $workspace, TeamInvitationQueries $queries, GovernanceQueries $roles, WorkspaceQueries $workspaces)
    {
        return Inertia::render('workspaces/Invitations', [
            'workspace' => $workspaces->projection($workspace),
            'invitations' => $queries->listing($request->user(), $workspace, $request->string('search')->toString()),
            'availableRoles' => $roles->assignableRoles($request->user(), $workspace),
            'mfaReady' => (bool) ($request->user()->two_factor_secret && $request->user()->two_factor_confirmed_at),
        ]);
    }

    public function store(TeamInvitationRequest $request, Tenant $workspace, CreateTeamInvitation $action)
    {
        $action->execute($request->user(), $workspace, $request->validated('recipient_email'), $request->validated('assignments'), (int) $request->validated('days'), $request->proof());

        return to_route('workspaces.invitations', $workspace);
    }

    public function revoke(ManageInvitationRequest $request, Tenant $workspace, string $invitation, ManageTeamInvitation $action)
    {
        $action->execute($request->user(), $workspace, $invitation, false, $request->proof());

        return to_route('workspaces.invitations', $workspace);
    }

    public function resend(ManageInvitationRequest $request, Tenant $workspace, string $invitation, ManageTeamInvitation $action)
    {
        $action->execute($request->user(), $workspace, $invitation, true, $request->proof());

        return to_route('workspaces.invitations', $workspace);
    }
}
