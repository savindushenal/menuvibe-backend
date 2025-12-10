<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'response_body',
        'response_status',
        'attempt_number',
        'delivered_at',
        'failed_at',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Check if delivery was successful
     */
    public function wasSuccessful()
    {
        return $this->delivered_at !== null && $this->response_status >= 200 && $this->response_status < 300;
    }

    /**
     * Check if should retry
     */
    public function shouldRetry()
    {
        return $this->failed_at !== null && 
               $this->attempt_number < $this->webhook->max_retries &&
               !$this->wasSuccessful();
    }

    /**
     * Relationships
     */
    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }
}
