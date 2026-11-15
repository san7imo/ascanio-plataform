<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\Tenant;

class RecordAudit
{
    public function execute(User $actor, Tenant $tenant, string $action, string $type, string $id, array $changes): void
    {
        AuditEvent::create([
            'tenant_id' => $tenant->id, 'actor_id' => $actor->id,
            'action' => $action, 'resource_type' => $type, 'resource_id' => $id,
            'changes' => $changes, 'occurred_at' => now(),
        ]);
    }
}
