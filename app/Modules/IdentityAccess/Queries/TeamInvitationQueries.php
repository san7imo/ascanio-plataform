<?php

namespace App\Modules\IdentityAccess\Queries;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\InvitationAuthority;
use App\Modules\IdentityAccess\Actions\InvitationRoles;
use App\Modules\IdentityAccess\CapabilityCatalog;
use App\Modules\IdentityAccess\Models\TeamInvitation;
use App\Modules\IdentityAccess\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamInvitationQueries
{
    public function __construct(private InvitationRecipient $recipient, private InvitationAuthority $authority, private WorkspaceAccess $access, private InvitationRoles $roleRules) {}

    public function listing(User $actor, Tenant $tenant, string $search = '')
    {
        Gate::forUser($actor)->authorize('inviteMembers', $tenant);

        return TeamInvitation::query()->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where('recipient_email', 'ilike', '%'.mb_substr($search, 0, 120).'%'))
            ->orderByDesc('created_at')->orderBy('id')->paginate(10)->withQueryString()
            ->through(fn ($invitation) => [
                'id' => $invitation->id, 'recipient' => $invitation->recipient_email,
                'status' => $invitation->status === 'pending' && $invitation->expires_at->isPast() ? 'expired' : $invitation->status,
                'expires_at' => $invitation->expires_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'delivery_status' => $invitation->delivery_status, 'failure_code' => $invitation->failure_code,
                'can_revoke' => $invitation->status === 'pending' && $this->canRevoke($actor, $tenant, $invitation),
                'can_resend' => $this->canResend($actor, $tenant, $invitation),
                'roles' => $this->roleProjection($invitation),
            ]);
    }

    public function incoming(User $actor, #[\SensitiveParameter] array $proof): array
    {
        try {
            $invitation = $this->recipient->find($proof, $actor->fresh());

            return $this->authority->execute($invitation, fn ($inviter, $tenant) => [
                'available' => true, 'workspace' => $tenant->name,
                'expires_at' => $invitation->expires_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'timezone' => $tenant->timezone, 'roles' => $this->roleProjection($invitation),
            ]);
        } catch (AuthorizationException|ValidationException|ModelNotFoundException) {
            return ['available' => false];
        }
    }

    private function canRevoke(User $actor, Tenant $tenant, TeamInvitation $invitation): bool
    {
        $codes = DB::table('team_invitation_roles')->where('tenant_id', $tenant->id)->where('invitation_id', $invitation->id)->pluck('snapshot')
            ->flatMap(fn ($snapshot) => array_column(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['permissions'], 'code'))->all();

        return $this->access->canDelegateCodes($actor, $tenant, $codes);
    }

    private function canResend(User $actor, Tenant $tenant, TeamInvitation $invitation): bool
    {
        if (! $invitation->isAvailable() || $invitation->inviter_id !== $actor->id
            || ! $tenant->memberships()->whereKey($invitation->source_membership_id)->where('user_id', $actor->id)->where('access_version', $invitation->source_access_version)->exists()) {
            return false;
        }
        try {
            $this->roleRules->validated($actor, $tenant, $invitation);

            return true;
        } catch (AuthorizationException|ValidationException|ModelNotFoundException) {
            return false;
        }
    }

    private function roleProjection(TeamInvitation $invitation): array
    {
        return DB::table('team_invitation_roles')->where('tenant_id', $invitation->tenant_id)->where('invitation_id', $invitation->id)->orderBy('id')->get()
            ->map(function ($row) {
                $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);

                return [
                    'id' => $row->id, 'name' => $snapshot['name'], 'scope' => $row->access_scope,
                    'capabilities' => array_map(fn ($p) => ['label' => CapabilityCatalog::LABELS[$p['code']] ?? 'Capacidad no disponible', 'delegable' => $p['delegable']], $snapshot['permissions']),
                ];
            })->all();
    }
}
