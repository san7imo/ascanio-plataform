<?php

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'created_by'];
}
