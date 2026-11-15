<?php

namespace App\Modules\IdentityAccess\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SendAccountEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $userId, public string $purpose, private string $emailHash, #[\SensitiveParameter] private ?string $token = null) {}

    public function displayName(): string
    {
        return 'account:'.$this->userId.':'.$this->purpose;
    }

    public function handle(TenantContext $context): void
    {
        // Account mail is global authentication, never the current/first tenant.
        $context->clear();
        try {
            $user = User::query()->find($this->userId);
            if (! $user || $user->status !== 'active' || ! hash_equals($this->emailHash, hash('sha256', Str::lower($user->email)))) {
                return;
            }
            if ($this->purpose === 'verify') {
                if ($user->hasVerifiedEmail()) {
                    return;
                }
                $notification = new VerifyEmail;
            } elseif ($this->purpose === 'reset' && $this->token && Password::broker()->tokenExists($user, $this->token)) {
                $notification = new ResetPassword($this->token);
            } else {
                return;
            }
            $transport = config('mail.mailers.'.config('mail.default').'.transport');
            if (! in_array($transport, ['smtp', 'array'], true)) {
                throw new \RuntimeException('Transporte de correo de cuenta no admitido. Configura SMTP o un transporte sintético.');
            }
            try {
                $user->notifyNow($notification);
            } catch (\Throwable) {
                // The queue command is encrypted; provider errors can contain
                // sensitive mail content, so never retain their message/trace.
                throw new \RuntimeException('No se pudo entregar el correo de cuenta. Revisa el transporte y reintenta el job.');
            }
        } finally {
            $context->clear();
        }
    }
}
