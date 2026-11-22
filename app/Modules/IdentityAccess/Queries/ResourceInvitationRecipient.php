<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceInvitationRecipient
{
    public function __construct(private DocumentSharingAuthority $authority) {}

    public function find(#[\SensitiveParameter] array $proof, ?User $recipient = null, bool $requireVerification = true): ResourceInvitation
    {
        $query = $this->authority->constrain(ResourceInvitation::query(), 'resource_invitations');
        $invitation = isset($proof['id']) ? $query->where('resource_invitations.id', $proof['id'])
            ->where('resource_invitations.purpose', 'document_version_access')->where('resource_invitations.token_hash', $proof['hash'] ?? '')->first() : null;
        if (! $invitation || ! $invitation->isAvailable() || ($proof['until'] ?? 0) < now()->timestamp
            || ! hash_equals($invitation->token_hash, $proof['hash'] ?? '')
            || ($recipient && ($recipient->status !== 'active' || ($requireVerification && ! $recipient->hasVerifiedEmail()) || Str::lower($recipient->email) !== $invitation->recipient_email))) {
            throw ValidationException::withMessages(['invitation' => 'La invitación no está disponible. Usa el enlace vigente y la cuenta verificada del destinatario.']);
        }

        return $invitation;
    }
}
