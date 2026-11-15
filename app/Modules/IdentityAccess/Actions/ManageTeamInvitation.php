<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTeamInvitation
{
    public function __construct(private GovernanceTransaction $transaction, private ConfirmSensitiveAction $confirm, private InvitationRoles $roles, private CheckDelegation $delegation, private QueueTeamInvitation $queue, private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, string $id, bool $resend, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        $this->transaction->execute($actor, $tenant, 'inviteMembers', function ($actor, $tenant) use ($id, $resend, $proof) {
            $invitation = TeamInvitation::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($id);
            if ($invitation->status !== 'pending' || ($resend && ! $invitation->isAvailable())) {
                throw ValidationException::withMessages(['invitation' => 'Esta invitación ya no admite esta operación.']);
            }
            if ($resend) {
                abort_unless($invitation->inviter_id === $actor->id, 403);
                $source = $tenant->memberships()->whereKey($invitation->source_membership_id)->where('user_id', $actor->id)->sole();
                if ((int) $source->access_version !== (int) $invitation->source_access_version) {
                    throw ValidationException::withMessages(['invitation' => 'Cambió tu autoridad. Revoca esta invitación y crea una nueva.']);
                }
                $this->roles->validated($actor, $tenant, $invitation);
            } else {
                foreach (DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('invitation_id', $id)->get() as $row) {
                    $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                    $this->delegation->codes($actor, $tenant, array_column($snapshot['permissions'], 'code'));
                }
            }
            $this->confirm->execute($actor, $proof);
            if ($resend) {
                $token = bin2hex(random_bytes(32));
                $invitation->update([
                    'token_hash' => hash('sha256', $token), 'delivery_version' => $invitation->delivery_version + 1,
                    'delivery_status' => 'pending', 'delivery_attempts' => 0, 'failure_code' => null, 'delivered_at' => null, 'last_attempt_at' => null,
                ]);
                $this->queue->execute($invitation, $token);
            } else {
                $invitation->update(['status' => 'revoked', 'revoked_at' => now()]);
            }
            $this->audit->execute($actor, $tenant, $resend ? 'invitation.resent' : 'invitation.revoked', 'team_invitation', $id, ['delivery_version' => $invitation->delivery_version, 'status' => $invitation->status]);
        });
    }
}
