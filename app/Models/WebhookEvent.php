<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A persisted record of an inbound gateway webhook, used for deduplication and
 * auditing. See the create_webhook_events_table migration for semantics.
 */
class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'provider',
        'charge_reference',
        'dedup_key',
        'status',
        'payload',
        'last_error',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
