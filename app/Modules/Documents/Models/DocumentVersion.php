<?php

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'storage_path', 'upload_key_hash', 'payload_hash', 'uploaded_by', 'scan_requested_by', 'source_membership_id', 'source_access_version'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'size_bytes' => 'integer', 'scan_generation' => 'integer', 'scanned_at' => 'immutable_datetime'];
    }
}
