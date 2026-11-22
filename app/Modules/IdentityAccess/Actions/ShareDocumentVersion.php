<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\DocumentSharingAuthority;
use App\Modules\IdentityAccess\Queries\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShareDocumentVersion
{
    public function __construct(private WorkspaceAccess $access, private ConfirmSensitiveAction $confirm, private QueueResourceInvitation $queue, private RecordAudit $audit, private DocumentSharingAuthority $authority) {}

    public function invite(User $actor, Tenant $tenant, string $documentId, string $versionId, string $email, string $mode, int $days, #[\SensitiveParameter] SensitiveProof $proof): ResourceInvitation
    {
        return DB::transaction(function () use ($actor, $tenant, $documentId, $versionId, $email, $mode, $days, $proof) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('viewAny', [Document::class, $tenant]);
            $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($documentId);
            Gate::forUser($actor)->authorize('share', [$document, $tenant]);
            $version = DocumentVersion::query()->where('tenant_id', $tenant->id)->where('document_id', $document->id)->findOrFail($versionId);
            $email = Str::lower(trim($email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || ! in_array($mode, ['metadata', 'file'], true) || $days < 1 || $days > 14) {
                throw ValidationException::withMessages(['invitation' => 'Revisa el correo, el acceso y la vigencia de uno a catorce días.']);
            }
            if ($mode === 'file') {
                abort_unless($this->access->canDelegate($actor, $tenant, 'documents.download'), 403);
            }
            abort_unless($version->scan_status === 'clean', 409, 'Comparte una versión cuyo análisis esté completado.');
            $this->confirm->execute($actor, $proof);
            $source = $tenant->memberships()->where('user_id', $actor->id)->sole();
            $token = bin2hex(random_bytes(32));
            $invitation = ResourceInvitation::create([
                'tenant_id' => $tenant->id, 'document_version_id' => $version->id, 'recipient_email' => $email,
                'inviter_id' => $actor->id, 'source_membership_id' => $source->id, 'source_access_version' => $source->access_version,
                'capability' => $mode === 'file' ? 'documents.download' : 'documents.view', 'projection' => $mode,
                'expires_at' => now()->addDays($days), 'token_hash' => hash('sha256', $token),
            ]);
            $this->audit->execute($actor, $tenant, 'resource_invitation.created', 'resource_invitation', $invitation->id, ['document_version_id' => $version->id, 'projection' => $mode, 'expires_at' => $invitation->expires_at->toIso8601String()]);
            $this->queue->execute($invitation, $token);

            return $invitation;
        }, 3);
    }

    public function manage(User $actor, Tenant $tenant, string $documentId, string $invitationId, bool $resend): void
    {
        DB::transaction(function () use ($actor, $tenant, $documentId, $invitationId, $resend) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('viewAny', [Document::class, $tenant]);
            $document = Document::query()->where('tenant_id', $tenant->id)->findOrFail($documentId);
            Gate::forUser($actor)->authorize('share', [$document, $tenant]);
            $invitation = ResourceInvitation::query()->where('tenant_id', $tenant->id)
                ->whereIn('document_version_id', DocumentVersion::query()->select('id')->where('tenant_id', $tenant->id)->where('document_id', $document->id))
                ->lockForUpdate()->findOrFail($invitationId);
            if ($resend) {
                abort_unless($invitation->inviter_id === $actor->id && $invitation->isAvailable(), 409);
                abort_unless($this->authority->constrain(ResourceInvitation::query(), 'resource_invitations')->where('resource_invitations.id', $invitation->id)->exists(), 403);
                $token = bin2hex(random_bytes(32));
                $invitation->update(['token_hash' => hash('sha256', $token), 'delivery_version' => $invitation->delivery_version + 1, 'delivery_status' => 'pending', 'delivery_attempts' => 0, 'last_attempt_at' => null, 'delivered_at' => null, 'failure_code' => null]);
                $this->queue->execute($invitation, $token);
                $action = 'resource_invitation.resent';
            } else {
                if ($invitation->status === 'accepted') {
                    $grant = ResourceGrant::query()->where('tenant_id', $tenant->id)->where('invitation_id', $invitation->id)->lockForUpdate()->sole();
                    if ($grant->revoked_at) {
                        return;
                    }
                    $grant->update(['revoked_at' => now()]);
                } elseif ($invitation->status === 'pending') {
                    $invitation->update(['status' => 'revoked']);
                } else {
                    return;
                }
                $action = 'resource_invitation.revoked';
            }
            $this->audit->execute($actor, $tenant, $action, 'resource_invitation', $invitation->id, ['document_version_id' => $invitation->document_version_id]);
        }, 3);
    }
}
