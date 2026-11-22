<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use App\Modules\IdentityAccess\Models\ResourceInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\ResourceInvitationRecipient;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Support\Facades\DB;

class AcceptResourceInvitation
{
    public function __construct(private ResourceInvitationRecipient $recipient, private RecordAudit $audit, private TenantContext $context) {}

    public function execute(User $user, #[\SensitiveParameter] array $proof): ResourceGrant
    {
        $this->context->clear();
        try {
            $initial = $this->recipient->find($proof, $user->fresh());

            return DB::transaction(function () use ($user, $proof, $initial) {
                $accounts = User::query()->whereIn('id', [$user->id, $initial->inviter_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($initial->tenant_id);
                ResourceInvitation::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($initial->id);
                $user = $accounts->get($user->id);
                abort_unless($user, 403);
                $invitation = $this->recipient->find($proof, $user);
                $grant = ResourceGrant::create([
                    'tenant_id' => $tenant->id, 'document_version_id' => $invitation->document_version_id, 'invitation_id' => $invitation->id,
                    'user_id' => $user->id, 'inviter_id' => $invitation->inviter_id,
                    'source_membership_id' => $invitation->source_membership_id, 'source_access_version' => $invitation->source_access_version,
                    'capability' => $invitation->capability, 'projection' => $invitation->projection, 'expires_at' => $invitation->expires_at,
                ]);
                $invitation->update(['status' => 'accepted', 'accepted_by_id' => $user->id, 'accepted_at' => now()]);
                $this->audit->execute($user, $tenant, 'resource_invitation.accepted', 'resource_grant', $grant->id, ['invitation_id' => $invitation->id, 'document_version_id' => $grant->document_version_id, 'projection' => $grant->projection]);

                return $grant;
            }, 3);
        } finally {
            $this->context->clear();
        }
    }
}
