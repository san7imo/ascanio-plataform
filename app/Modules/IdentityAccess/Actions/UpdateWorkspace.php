<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateWorkspace
{
    public function __construct(private RecordAudit $audit) {}

    public function execute(User $actor, Tenant $tenant, array $values): void
    {
        DB::transaction(function () use ($actor, $tenant, $values) {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('update', $tenant);
            $tenant->fill(array_intersect_key($values, array_flip(['name', 'locale', 'timezone'])));
            $fields = array_keys($tenant->getDirty());

            if ($fields !== []) {
                $tenant->save();
                $this->audit->execute($actor, $tenant, 'tenant.updated', 'tenant', $tenant->id, ['fields' => $fields]);
            }
        }, 3);
    }
}
