<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\SensitiveProof;
use App\Modules\IdentityAccess\Models\Tenant;

class RevokeMembership
{
    public function __construct(private ChangeMembershipStatus $change) {}

    public function execute(User $actor, Tenant $tenant, string $membershipId, #[\SensitiveParameter] SensitiveProof $proof): void
    {
        $this->change->execute($actor, $tenant, $membershipId, 'revoked', $proof);
    }
}
