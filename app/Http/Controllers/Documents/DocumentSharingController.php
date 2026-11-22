<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Documents\Queries\DocumentQueries;
use App\Modules\IdentityAccess\Actions\ShareDocumentVersion;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\DocumentSharingAuthority;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentSharingController extends Controller
{
    public function index(Request $request, Tenant $workspace, string $document, DocumentQueries $documents, WorkspaceQueries $workspaces, WorkspaceAccess $access, DocumentSharingAuthority $authority)
    {
        $record = $documents->find($request->user(), $workspace, $document);
        Gate::authorize('share', [$record, $workspace]);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $versions = DocumentVersion::query()->where('tenant_id', $workspace->id)->where('document_id', $record->id);

        return Inertia::render('documents/Sharing', [
            'workspace' => $workspaces->projection($workspace),
            'document' => ['id' => $record->id, 'title' => $record->title],
            'versions' => (clone $versions)->where('scan_status', 'clean')->orderByDesc('number')->get(['id', 'number', 'filename']),
            'canShareFile' => $access->canDelegate($request->user(), $workspace, 'documents.download'),
            'invitations' => ResourceInvitation::query()->where('tenant_id', $workspace->id)->whereIn('document_version_id', $versions->select('id'))
                ->orderByDesc('created_at')->paginate(10)->through(function ($invitation) use ($workspace, $request, $authority) {
                    $grant = ResourceGrant::query()->where('tenant_id', $workspace->id)->where('invitation_id', $invitation->id)->first();

                    $valid = $authority->constrain(ResourceInvitation::query(), 'resource_invitations')->where('resource_invitations.id', $invitation->id)->exists();

                    return ['id' => $invitation->id, 'recipient_email' => $invitation->recipient_email, 'projection' => $invitation->projection,
                        'status' => $grant?->revoked_at ? 'revoked' : ($invitation->expires_at->isPast() ? 'expired' : (! $valid ? 'authority_changed' : $invitation->status)),
                        'expires_at' => $invitation->expires_at->setTimezone($workspace->timezone)->format('Y-m-d H:i'),
                        'delivery_status' => $invitation->delivery_status,
                        'can_revoke' => ! $grant?->revoked_at && in_array($invitation->status, ['pending', 'accepted'], true),
                        'can_resend' => $valid && $invitation->inviter_id === $request->user()->id && $invitation->isAvailable(),
                    ];
                }),
        ]);
    }

    public function store(Request $request, Tenant $workspace, string $document, string $version, ShareDocumentVersion $action)
    {
        Gate::authorize('viewAny', [Document::class, $workspace]);
        $data = $request->validate([
            'recipient_email' => ['required', 'string', 'email:rfc', 'max:254'], 'projection' => ['required', 'in:metadata,file'],
            'days' => ['required', 'integer', 'between:1,14'], 'password' => ['required', 'string', 'max:1024'], 'code' => ['required', 'digits:6'],
            'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'], 'inviter_id' => ['prohibited'], 'capability' => ['prohibited'],
        ]);
        $action->invite($request->user(), $workspace, $document, $version, $data['recipient_email'], $data['projection'], $data['days'], new SensitiveProof($data['password'], $data['code']));

        return to_route('documents.sharing.index', [$workspace, $document]);
    }

    public function revoke(Request $request, Tenant $workspace, string $document, string $invitation, ShareDocumentVersion $action)
    {
        $action->manage($request->user(), $workspace, $document, $invitation, false);

        return to_route('documents.sharing.index', [$workspace, $document]);
    }

    public function resend(Request $request, Tenant $workspace, string $document, string $invitation, ShareDocumentVersion $action)
    {
        $action->manage($request->user(), $workspace, $document, $invitation, true);

        return to_route('documents.sharing.index', [$workspace, $document]);
    }
}
