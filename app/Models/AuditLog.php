<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'event',
        'actor_type',
        'actor_id',
        'actor_role',
        'subject_type',
        'subject_id',
        'success',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'success' => 'boolean',
    ];
}
