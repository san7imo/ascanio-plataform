<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptTeamInvitationRequest;
use App\Modules\IdentityAccess\Actions\AcceptTeamInvitation;
use App\Modules\IdentityAccess\Queries\InvitationRecipient;
use App\Modules\IdentityAccess\Queries\TeamInvitationQueries;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamInvitationClaimController extends Controller
{
    public function landing(Request $request, string $invitation)
    {
        // No invitation lookup/metadata for an anonymous GET or mail scanner.
        return Inertia::render('invitations/Claim', ['invitationId' => $invitation])->toResponse($request)->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store, private');
    }

    public function claim(Request $request, string $invitation, InvitationRecipient $recipient)
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']], ['token.*' => 'Abre un enlace de invitación válido.']);
        $proof = ['id' => $invitation, 'hash' => hash('sha256', $data['token']), 'until' => now()->addHour()->timestamp];
        $recipient->find($proof, $request->user()?->fresh(), requireVerification: false);
        $request->session()->put('team_invitation_proof', $proof);

        return to_route('team-invitations.review');
    }

    public function review(Request $request, TeamInvitationQueries $queries)
    {
        return Inertia::render('invitations/Review', ['invitation' => $queries->incoming($request->user(), $request->session()->get('team_invitation_proof', []))]);
    }

    public function accept(AcceptTeamInvitationRequest $request, AcceptTeamInvitation $action, WorkspaceAccess $access)
    {
        $membership = $action->execute($request->user(), $request->session()->get('team_invitation_proof', []));
        $request->session()->forget('team_invitation_proof');

        return $access->capableMemberships($request->user(), 'tenant.view')->where('tenant_memberships.tenant_id', $membership->tenant_id)->exists()
            ? to_route('workspaces.show', $membership->tenant_id) : to_route('workspaces.index');
    }
}
