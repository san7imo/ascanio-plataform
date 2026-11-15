<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Jobs\SendTeamInvitation;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueTeamInvitation
{
    public function execute(TeamInvitation $invitation, #[\SensitiveParameter] string $token): void
    {
        $tenantId = $invitation->tenant_id;
        $id = $invitation->id;
        $requesterId = $invitation->inviter_id;
        $version = $invitation->delivery_version;
        DB::afterCommit(function () use ($tenantId, $id, $requesterId, $version, $token) {
            try {
                SendTeamInvitation::dispatch($tenantId, $id, $requesterId, $version, $token);
            } catch (\Throwable $exception) {
                // The committed invitation remains visible and retryable. Do not
                // log queue/provider exceptions that may include mail secrets.
                TeamInvitation::query()->where('tenant_id', $tenantId)->whereKey($id)->where('delivery_version', $version)
                    ->where('status', 'pending')->where('delivery_status', 'pending')->update(['delivery_status' => 'failed', 'failure_code' => 'queue_unavailable']);
                Log::warning('Team invitation dispatch failed.', ['tenant_id' => $tenantId, 'invitation_id' => $id, 'delivery_version' => $version, 'exception_type' => $exception::class]);
            }
        });
    }
}
