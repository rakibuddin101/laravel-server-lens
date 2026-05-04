<?php

namespace Rakib\ServerLens\Models;

use Illuminate\Database\Eloquent\Model;

class TrafficLog extends Model
{
    protected $table = 'server_lens_traffic_logs';

    public $timestamps = false;

    protected $fillable = [
        'ip_address', 'user_agent', 'method', 'url', 'path',
        'status_code', 'response_time', 'referrer', 'user_id',
        'session_id', 'classification', 'action_taken',
        'country_code', 'country_name', 'city', 'bot_name',
        'is_ajax', 'metadata', 'created_at',
    ];

    protected $casts = [
        'is_ajax'    => 'boolean',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];
}
