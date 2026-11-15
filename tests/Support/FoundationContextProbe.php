<?php

namespace Tests\Support;

use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class FoundationContextProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $tenantId, public string $key, public bool $fail = false) {}

    public function handle(TenantContext $context): void
    {
        try {
            $context->tenant();
            $empty = false;
        } catch (AuthorizationException) {
            $empty = true;
        }
        Redis::rpush($this->key, json_encode(['phase' => 'probe', 'pid' => getmypid(), 'empty' => $empty], JSON_THROW_ON_ERROR));
        $tenant = Tenant::findOrFail($this->tenantId);
        $context->establish($tenant, $tenant->memberships()->where('is_owner', true)->sole());
        // Intentionally omit cleanup: framework hooks must also protect against
        // a future job leaking context on both the success and failure paths.
        if ($this->fail) {
            throw new \RuntimeException('Synthetic context probe failure.');
        }
    }
}
