<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Models\ResourceGrant;
use Illuminate\Database\Eloquent\Builder;

final class DocumentGrantAccess
{
    public function __construct(private DocumentSharingAuthority $authority) {}

    public function query(User $actor): Builder
    {
        // Deliberate account-bounded collaboration bootstrap. It resolves custody
        // from a current grant owned by this user, never from a client tenant ID.
        return $this->authority->constrain(ResourceGrant::query(), 'resource_grants')
            ->join('users as recipient', 'recipient.id', '=', 'resource_grants.user_id')
            ->where('recipient.id', $actor->id)->where('recipient.status', 'active')->whereNotNull('recipient.email_verified_at')
            ->whereNull('resource_grants.revoked_at')->where('resource_grants.expires_at', '>', now());
    }

    public function find(User $actor, string $id): ResourceGrant
    {
        return $this->query($actor)->where('resource_grants.id', $id)->firstOrFail();
    }
}
