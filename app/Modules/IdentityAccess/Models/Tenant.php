<?php

namespace App\Modules\IdentityAccess\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['operator_party_id', 'created_by', 'creation_key_hash', 'creation_payload_hash'];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
