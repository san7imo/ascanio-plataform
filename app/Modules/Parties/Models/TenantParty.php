<?php

namespace App\Modules\Parties\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantParty extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    // Responses use explicit projections, never serialize the private model.
    protected $hidden = ['tenant_id', 'party_id', 'legal_name', 'email', 'phone', 'notes', 'source_note', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
