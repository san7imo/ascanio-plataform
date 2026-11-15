<?php

namespace App\Modules\IdentityAccess\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    use HasUuids;

    protected $fillable = ['kind', 'status', 'provenance'];
}
