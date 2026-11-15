<?php

namespace App\Modules\IdentityAccess\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\InvitationAuthority;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Mail\TeamInvitationMail;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendTeamInvitation implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $tenantId, public string $invitationId, public string $requesterId, public int $version, #[\SensitiveParameter] private string $token) {}

    public function displayName(): string
    {
        return 'tenant:'.$this->tenantId.':team-invitation:'.$this->invitationId;
    }

    public function handle(TenantContext $context, InvitationAuthority $authority, RecordAudit $audit): void
    {
        $context->clear();
        try {
            DB::transaction(function () use ($authority, $audit) {
                User::query()->whereKey($this->requesterId)->lockForUpdate()->firstOrFail();
                Tenant::query()->whereKey($this->tenantId)->lockForUpdate()->firstOrFail();
                $invitation = TeamInvitation::query()->where('tenant_id', $this->tenantId)->lockForUpdate()->findOrFail($this->invitationId);
                if (! $invitation->isAvailable() || $invitation->inviter_id !== $this->requesterId
                    || $invitation->delivery_version !== $this->version || ! hash_equals($invitation->token_hash, hash('sha256', $this->token))
                    || in_array($invitation->delivery_status, ['sent', 'simulated'], true)) {
                    return;
                }
                $invitation->update(['delivery_attempts' => $invitation->delivery_attempts + 1, 'last_attempt_at' => now()]);
                try {
                    $authority->execute($invitation, function ($actor, $tenant) use ($invitation, $audit) {
                        $transport = config('mail.mailers.'.config('mail.default').'.transport');
                        if (! in_array($transport, ['smtp', 'array'], true)) {
                            $invitation->update(['delivery_status' => 'blocked', 'failure_code' => 'unsupported_transport']);

                            return;
                        }
                        $url = url('/team-invitations/'.$invitation->id).'#token='.$this->token;
                        Mail::to($invitation->recipient_email)->send(new TeamInvitationMail($tenant->name, $url, $invitation->expires_at->toIso8601String()));
                        $state = $transport === 'array' ? 'simulated' : 'sent';
                        $invitation->update(['delivery_status' => $state, 'delivered_at' => now(), 'failure_code' => null]);
                        $audit->execute($actor, $tenant, 'invitation.notification_'.$state, 'team_invitation', $invitation->id, ['delivery_version' => $this->version]);
                    });
                } catch (AuthorizationException|ValidationException|ModelNotFoundException) {
                    $invitation->update(['delivery_status' => 'blocked', 'failure_code' => 'authority_changed']);
                }
            }, 3);
        } catch (Throwable) {
            $this->markFailed('delivery_failed');
            // Provider errors and serialized tokens must never reach failed-job logs.
            throw new \RuntimeException('Team invitation delivery failed; inspect the scoped delivery state and retry.');
        } finally {
            $context->clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $this->markFailed('attempts_exhausted');
        } finally {
            $context->clear();
        }
    }

    private function markFailed(string $code): void
    {
        $query = TeamInvitation::query()->where('tenant_id', $this->tenantId)->whereKey($this->invitationId)
            ->where('delivery_version', $this->version)->where('status', 'pending')->whereNotIn('delivery_status', ['sent', 'simulated']);
        if ($code === 'delivery_failed') {
            $query->increment('delivery_attempts', 1, ['delivery_status' => 'failed', 'failure_code' => $code, 'last_attempt_at' => now()]);
        } else {
            $query->update(['delivery_status' => 'failed', 'failure_code' => $code]);
        }
    }
}
