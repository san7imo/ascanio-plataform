<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationRecipient
{
    public function find(#[\SensitiveParameter] array $proof, ?User $recipient = null, bool $requireVerification = true): TeamInvitation
    {
        // Bounded collaboration bootstrap before membership exists: exact ID,
        // purpose and token proof, not a global invitation/email directory.
        $invitation = isset($proof['id']) ? TeamInvitation::query()->whereKey($proof['id'])->where('purpose', 'team_membership')->where('token_hash', $proof['hash'] ?? '')->first() : null;
        if (! $invitation || ! $invitation->isAvailable() || ($proof['until'] ?? 0) < now()->timestamp
            || ! hash_equals($invitation->token_hash, $proof['hash'] ?? '')) {
            throw ValidationException::withMessages(['invitation' => 'La invitación no está disponible. Abre un enlace vigente.']);
        }
        if ($recipient && ($recipient->status !== 'active' || ($requireVerification && ! $recipient->hasVerifiedEmail())
            || Str::lower($recipient->email) !== $invitation->recipient_email)) {
            throw ValidationException::withMessages(['invitation' => 'Inicia sesión con la cuenta verificada a la que se envió esta invitación.']);
        }

        return $invitation;
    }
}
