<?php

namespace App\Modules\IdentityAccess\Mail;

use Illuminate\Mail\Mailable;

class ResourceInvitationMail extends Mailable
{
    public function __construct(private string $workspaceName, #[\SensitiveParameter] private string $claimUrl, private string $expiresAt) {}

    public function build(): self
    {
        return $this->subject('Un documento compartido contigo en Ascanio')->view('mail.resource-invitation', [
            'workspaceName' => $this->workspaceName, 'claimUrl' => $this->claimUrl, 'expiresAt' => $this->expiresAt,
        ]);
    }
}
