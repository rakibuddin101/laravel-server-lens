<?php

namespace Rakib\ServerLens\Models;

use Illuminate\Database\Eloquent\Model;

class IpBlock extends Model
{
    protected $table = 'server_lens_ip_blocks';

    protected $fillable = [
        'ip_address', 'reason', 'is_active', 'expires_at', 'blocked_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];
}
