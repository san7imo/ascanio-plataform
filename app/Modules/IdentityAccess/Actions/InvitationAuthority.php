<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class InvitationAuthority
{
    public function __construct(private TenantContext $context, private InvitationRoles $roles) {}

    public function execute(TeamInvitation $invitation, Closure $operation): mixed
    {
        $this->context->clear();
        try {
            $tenant = Tenant::query()->findOrFail($invitation->tenant_id);
            $actor = User::query()->findOrFail($invitation->inviter_id);
            $member = $tenant->memberships()->whereKey($invitation->source_membership_id)->where('user_id', $actor->id)->firstOrFail();
            if ((int) $member->access_version !== (int) $invitation->source_access_version) {
                throw new AuthorizationException;
            }
            $this->context->establish($tenant, $member);
            Gate::forUser($actor)->authorize('view', $tenant);
            Gate::forUser($actor)->authorize('inviteMembers', $tenant);
            $rows = $this->roles->validated($actor, $tenant, $invitation);

            return $operation($actor, $tenant, $rows);
        } finally {
            $this->context->clear();
        }
    }
}
