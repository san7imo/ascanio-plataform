<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTeamInvitation
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private InvitationRoles $roles, private QueueTeamInvitation $queue, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $email, array $assignments, int $days, #[\SensitiveParameter] SensitiveProof $proof): TeamInvitation
    {
        $invitation = $this->transaction->execute($actor, $tenant, 'inviteMembers', function ($actor, $tenant) use ($email, $assignments, $days, $proof) {
            $email = Str::lower(trim($email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $days < 1 || $days > 14 || ! $assignments || count($assignments) > 20) {
                throw ValidationException::withMessages(['invitation' => 'Revisa el destinatario, los roles y la vigencia de uno a catorce días.']);
            }
            $previous = TeamInvitation::query()->where('tenant_id', $tenant->id)->where('recipient_email', $email)->where('status', 'pending')->lockForUpdate()->first();
            if ($previous?->isAvailable()) {
                throw ValidationException::withMessages(['recipient_email' => 'Ya hay una invitación pendiente para este correo en el espacio.']);
            }
            $this->confirm->execute($actor, $proof);
            if ($previous) {
                $previous->update(['status' => 'expired']);
                $this->audit->execute($actor, $tenant, 'invitation.expired', 'team_invitation', $previous->id, ['status' => 'expired']);
            }
            $source = $tenant->memberships()->where('user_id', $actor->id)->sole();
            $token = bin2hex(random_bytes(32));
            $invitation = TeamInvitation::create([
                'tenant_id' => $tenant->id, 'inviter_id' => $actor->id, 'recipient_email' => $email,
                'source_membership_id' => $source->id, 'source_access_version' => $source->access_version,
                'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays($days),
            ]);
            $this->roles->attach($actor, $tenant, $invitation, $assignments);
            $this->audit->execute($actor, $tenant, 'invitation.created', 'team_invitation', $invitation->id, ['assignments' => $assignments, 'expires_at' => $invitation->expires_at->toIso8601String()]);
            $this->queue->execute($invitation, $token);

            return $invitation;
        });

        return TeamInvitation::query()->where('tenant_id', $tenant->id)->findOrFail($invitation->id);
    }
}
