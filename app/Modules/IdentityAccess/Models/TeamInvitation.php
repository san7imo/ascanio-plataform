<?php

namespace App\Modules\IdentityAccess\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['purpose' => 'team_membership', 'status' => 'pending', 'delivery_version' => 1, 'delivery_status' => 'pending', 'delivery_attempts' => 0];

    protected $hidden = ['token_hash', 'recipient_email'];

    protected function casts(): array
    {
        return [
            'delivery_version' => 'integer', 'delivery_attempts' => 'integer',
            'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->purpose === 'team_membership' && $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
