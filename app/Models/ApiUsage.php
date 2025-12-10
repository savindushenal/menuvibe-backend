<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUsage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'api_usage';

    protected $fillable = [
        'api_key_id',
        'user_id',
        'endpoint',
        'method',
        'response_status',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'request_params',
        'response_summary',
        'created_at',
    ];

    protected $casts = [
        'request_params' => 'array',
        'response_summary' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log API request
     */
    public static function log($data)
    {
        return self::create(array_merge($data, [
            'created_at' => now(),
        ]));
    }
}
