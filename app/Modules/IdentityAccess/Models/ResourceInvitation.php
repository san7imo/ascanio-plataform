<?php

namespace App\Modules\IdentityAccess\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ResourceInvitation extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['purpose' => 'document_version_access', 'status' => 'pending', 'delivery_version' => 1, 'delivery_status' => 'pending', 'delivery_attempts' => 0];

    protected $hidden = ['token_hash', 'recipient_email', 'source_membership_id', 'source_access_version'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'delivery_version' => 'integer'];
    }

    public function isAvailable(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
