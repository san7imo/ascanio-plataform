<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parties\PartyRequest;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use App\Modules\Parties\Actions\SaveTenantParty;
use App\Modules\Parties\Data\PartyInput;
use App\Modules\Parties\Models\TenantParty;
use App\Modules\Parties\Queries\PartyQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantPartyController extends Controller
{
    public function index(Request $request, Tenant $workspace, PartyQueries $queries, WorkspaceQueries $workspaces): Response
    {
        Gate::authorize('viewAny', [TenantParty::class, $workspace]);
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('parties/Index', [
            'workspace' => $workspaces->projection($workspace),
            'parties' => $queries->listing($request->user(), $workspace, $request->string('search')->toString()),
            'search' => $request->string('search')->toString(),
            'canCreate' => Gate::allows('create', [TenantParty::class, $workspace]),
        ]);
    }

    public function create(Request $request, Tenant $workspace, PartyQueries $queries, WorkspaceQueries $workspaces): Response
    {
        Gate::authorize('create', [TenantParty::class, $workspace]);

        return Inertia::render('parties/Edit', ['workspace' => $workspaces->projection($workspace), 'party' => null, 'activities' => $queries->activities($request->user(), $workspace)]);
    }

    public function store(PartyRequest $request, Tenant $workspace, SaveTenantParty $action): RedirectResponse
    {
        $party = $action->execute($request->user(), $workspace, null, $request->safe()->only(PartyInput::FIELDS));

        return to_route('parties.show', [$workspace, $party->id]);
    }

    public function show(Request $request, Tenant $workspace, string $party, PartyQueries $queries, WorkspaceQueries $workspaces): Response
    {
        $record = $queries->find($request->user(), $workspace, $party);
        $projection = $queries->detail($request->user(), $workspace, $record);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('parties/Show', [
            'workspace' => $workspaces->projection($workspace), 'party' => $projection,
            'revisions' => $projection['can_view_private'] ? $queries->revisions($request->user(), $workspace, $record) : null,
        ]);
    }

    public function edit(Request $request, Tenant $workspace, string $party, PartyQueries $queries, WorkspaceQueries $workspaces): Response
    {
        $record = $queries->find($request->user(), $workspace, $party);
        Gate::authorize('update', [$record, $workspace]);

        return Inertia::render('parties/Edit', [
            'workspace' => $workspaces->projection($workspace), 'party' => $queries->detail($request->user(), $workspace, $record),
            'activities' => $queries->activities($request->user(), $workspace),
        ]);
    }

    public function update(PartyRequest $request, Tenant $workspace, string $party, SaveTenantParty $action): RedirectResponse
    {
        $record = $action->execute($request->user(), $workspace, $party, $request->safe()->only(PartyInput::FIELDS));

        return to_route('parties.show', [$workspace, $record->id]);
    }
}
