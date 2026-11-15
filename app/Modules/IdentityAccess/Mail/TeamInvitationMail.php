<?php

namespace App\Modules\IdentityAccess\Mail;

use Illuminate\Mail\Mailable;

class TeamInvitationMail extends Mailable
{
    public function __construct(private string $workspaceName, #[\SensitiveParameter] private string $claimUrl, private string $expiresAt) {}

    public function build(): self
    {
        return $this->subject('Invitación a un equipo en Ascanio')->view('mail.team-invitation', [
            'workspaceName' => $this->workspaceName, 'claimUrl' => $this->claimUrl, 'expiresAt' => $this->expiresAt,
        ]);
    }
}
