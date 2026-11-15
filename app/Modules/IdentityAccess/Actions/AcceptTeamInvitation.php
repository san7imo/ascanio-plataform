<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Membership;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\InvitationRecipient;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptTeamInvitation
{
    public function __construct(private InvitationRecipient $recipient, private InvitationAuthority $authority, private TenantContext $context, private RecordAudit $audit) {}

    public function execute(User $user, #[\SensitiveParameter] array $proof): Membership
    {
        $this->context->clear();
        try {
            $initial = $this->recipient->find($proof, $user->fresh());

            return DB::transaction(function () use ($user, $proof, $initial) {
                $accounts = User::query()->whereIn('id', [$user->id, $initial->inviter_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                Tenant::query()->lockForUpdate()->findOrFail($initial->tenant_id);
                TeamInvitation::query()->where('tenant_id', $initial->tenant_id)->lockForUpdate()->findOrFail($initial->id);
                $user = $accounts->get($user->id);
                abort_unless($user, 403);
                $invitation = $this->recipient->find($proof, $user);

                return $this->authority->execute($invitation, function ($inviter, $tenant, $rows) use ($user, $invitation) {
                    $member = $tenant->memberships()->where('user_id', $user->id)->lockForUpdate()->first();
                    if ($member && ($member->status !== 'active' || $member->is_owner)) {
                        throw ValidationException::withMessages(['invitation' => 'Tu membresía requiere gestión del propietario; no se reactiva ni modifica por invitación.']);
                    }
                    if ($member) {
                        $member->increment('access_version');
                    }
                    $member ??= Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'is_owner' => false, 'accepted_at' => now()]);
                    foreach ($rows as $row) {
                        $current = DB::table('membership_roles')->where('tenant_id', $tenant->id)->where('membership_id', $member->id)->where('role_id', $row->role_id)->first();
                        DB::table('membership_roles')->updateOrInsert([
                            'tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $row->role_id,
                        ], ['access_scope' => $current?->access_scope === 'tenant' ? 'tenant' : $row->access_scope]);
                    }
                    $invitation->update(['status' => 'accepted', 'accepted_by_id' => $user->id, 'accepted_at' => now()]);
                    $this->audit->execute($user, $tenant, 'invitation.accepted', 'team_invitation', $invitation->id, ['membership_id' => $member->id, 'inviter_id' => $inviter->id]);

                    return $member;
                });
            }, 3);
        } finally {
            $this->context->clear();
        }
    }
}
