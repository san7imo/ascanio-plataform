<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\RevokeMembershipRequest;
use App\Http\Requests\Workspaces\WorkspaceRequest;
use App\Modules\IdentityAccess\Actions\CreateWorkspace;
use App\Modules\IdentityAccess\Actions\RevokeMembership;
use App\Modules\IdentityAccess\Actions\UpdateWorkspace;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\GovernanceQueries;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceQueries $queries): Response
    {
        return Inertia::render('workspaces/Index', ['workspaces' => $queries->workspaces($request->user(), $request->string('search')->toString())]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Tenant::class);
        $token = (string) Str::uuid();
        $tokens = array_slice([...$request->session()->get('workspace_creation_tokens', []), $token], -5);
        $request->session()->put('workspace_creation_tokens', $tokens);

        return Inertia::render('workspaces/Create', ['creationToken' => $token]);
    }

    public function store(WorkspaceRequest $request, CreateWorkspace $action): RedirectResponse
    {
        $tenant = $action->execute($request->user(), $request->safe()->only(['name', 'locale', 'timezone']), $request->validated('creation_token'));
        $request->session()->put('current_workspace_id', $tenant->id);

        return to_route('workspaces.show', $tenant);
    }

    public function show(Request $request, Tenant $workspace, WorkspaceQueries $queries): Response
    {
        Gate::authorize('view', $workspace);

        return Inertia::render('workspaces/Show', ['workspace' => $queries->projection($workspace)]);
    }

    public function select(Request $request, Tenant $workspace): RedirectResponse
    {
        Gate::authorize('view', $workspace);
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace);
    }

    public function update(WorkspaceRequest $request, Tenant $workspace, UpdateWorkspace $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $request->safe()->only(['name', 'locale', 'timezone']));

        return to_route('workspaces.show', $workspace)->with('status', 'Espacio actualizado.');
    }

    public function members(Request $request, Tenant $workspace, WorkspaceQueries $queries, GovernanceQueries $governance): Response
    {
        Gate::authorize('viewMembers', $workspace);

        return Inertia::render('workspaces/Members', [
            'workspace' => $queries->projection($workspace),
            'members' => $queries->members($request->user(), $workspace, $request->string('search')->toString()),
            'availableRoles' => Gate::allows('assignRoles', $workspace) ? $governance->assignableRoles($request->user(), $workspace) : [],
            'mfaReady' => (bool) ($request->user()->two_factor_secret && $request->user()->two_factor_confirmed_at),
        ]);
    }

    public function revoke(RevokeMembershipRequest $request, Tenant $workspace, string $membership, RevokeMembership $action): RedirectResponse
    {
        Gate::authorize('revokeMember', $workspace);
        $action->execute($request->user(), $workspace, $membership, $request->proof());

        return Gate::allows('view', $workspace) && Gate::allows('viewMembers', $workspace)
            ? to_route('workspaces.members', $workspace) : to_route('workspaces.index');
    }
}
