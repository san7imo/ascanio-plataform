<?php

namespace App\Modules\IdentityAccess\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Mail\ResourceInvitationMail;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\DocumentSharingAuthority;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendResourceInvitation implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $tenantId, public string $invitationId, public string $requesterId, public int $version, #[\SensitiveParameter] private string $token) {}

    public function displayName(): string
    {
        return 'tenant:'.$this->tenantId.':resource-invitation:'.$this->invitationId;
    }

    public function handle(TenantContext $context, DocumentSharingAuthority $authority, RecordAudit $audit): void
    {
        $context->clear();
        try {
            DB::transaction(function () use ($authority, $audit) {
                $actor = User::query()->whereKey($this->requesterId)->lockForUpdate()->firstOrFail();
                $tenant = Tenant::query()->whereKey($this->tenantId)->lockForUpdate()->firstOrFail();
                $invitation = ResourceInvitation::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($this->invitationId);
                if (! $invitation->isAvailable() || $invitation->inviter_id !== $actor->id || $invitation->delivery_version !== $this->version
                    || ! hash_equals($invitation->token_hash, hash('sha256', $this->token)) || in_array($invitation->delivery_status, ['sent', 'simulated'], true)) {
                    return;
                }
                if (! $authority->constrain(ResourceInvitation::query(), 'resource_invitations')->where('resource_invitations.id', $invitation->id)->exists()) {
                    $invitation->update(['delivery_status' => 'blocked', 'failure_code' => 'authority_changed']);

                    return;
                }
                $transport = config('mail.mailers.'.config('mail.default').'.transport');
                if (! in_array($transport, ['smtp', 'array'], true)) {
                    $invitation->update(['delivery_status' => 'blocked', 'failure_code' => 'unsupported_transport']);

                    return;
                }
                $url = url('/resource-invitations/'.$invitation->id).'#token='.$this->token;
                Mail::to($invitation->recipient_email)->send(new ResourceInvitationMail($tenant->name, $url, $invitation->expires_at->toIso8601String()));
                $state = $transport === 'array' ? 'simulated' : 'sent';
                $invitation->update(['delivery_status' => $state, 'delivery_attempts' => $invitation->delivery_attempts + 1, 'last_attempt_at' => now(), 'delivered_at' => now(), 'failure_code' => null]);
                $audit->execute($actor, $tenant, 'resource_invitation.notification_'.$state, 'resource_invitation', $invitation->id, ['delivery_version' => $this->version]);
            }, 3);
        } catch (Throwable) {
            $this->markFailed('delivery_failed');
            throw new \RuntimeException('Resource invitation delivery failed; inspect the scoped state and retry.');
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
        ResourceInvitation::query()->where('tenant_id', $this->tenantId)->whereKey($this->invitationId)->where('inviter_id', $this->requesterId)
            ->where('delivery_version', $this->version)->where('status', 'pending')->whereNotIn('delivery_status', ['sent', 'simulated', 'blocked'])
            ->increment('delivery_attempts', 1, ['delivery_status' => 'failed', 'failure_code' => $code, 'last_attempt_at' => now()]);
    }
}
