<?php

namespace App\Http\Controllers;

use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Actions\AcceptResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\ResourceInvitationRecipient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResourceInvitationClaimController extends Controller
{
    public function landing(Request $request, string $invitation)
    {
        // No lookup, token consumption or private metadata on anonymous GET.
        return Inertia::render('invitations/ResourceClaim', ['invitationId' => $invitation])->toResponse($request)
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store, private');
    }

    public function claim(Request $request, string $invitation, ResourceInvitationRecipient $recipient)
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $proof = ['id' => $invitation, 'hash' => hash('sha256', $data['token']), 'until' => now()->addHour()->timestamp];
        $recipient->find($proof, $request->user()?->fresh(), requireVerification: false);
        $request->session()->put('resource_invitation_proof', $proof);

        return to_route('resource-invitations.review');
    }

    public function review(Request $request, ResourceInvitationRecipient $recipient)
    {
        $invitation = $recipient->find($request->session()->get('resource_invitation_proof', []), $request->user());
        $tenant = Tenant::query()->findOrFail($invitation->tenant_id);
        $version = DocumentVersion::query()->where('tenant_id', $tenant->id)->findOrFail($invitation->document_version_id);

        return Inertia::render('invitations/ResourceReview', ['invitation' => [
            'workspace' => $tenant->name, 'filename' => $version->filename, 'number' => $version->number, 'sha256' => $version->sha256,
            'projection' => $invitation->projection, 'timezone' => $tenant->timezone,
            'expires_at' => $invitation->expires_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
        ]]);
    }

    public function accept(Request $request, AcceptResourceInvitation $action)
    {
        $request->validate(['confirmation' => ['accepted'], 'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'], 'capability' => ['prohibited'], 'projection' => ['prohibited'], 'token' => ['prohibited']]);
        $grant = $action->execute($request->user(), $request->session()->get('resource_invitation_proof', []));
        $request->session()->forget('resource_invitation_proof');

        return to_route('shared-documents.show', $grant->id);
    }
}
