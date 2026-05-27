<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppWebSession extends Model
{
    protected $table = 'whatsapp_web_sessions';

    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_QR_PENDING = 'qr_pending';
    public const STATUS_CONNECTING = 'connecting';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_LOGGED_OUT = 'logged_out';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'channel_id',
        'instance_name',
        'instance_id',
        'instance_apikey',
        'status',
        'qr_raw',
        'connected_phone',
        'connected_name',
        'qr_generated_at',
        'last_connected_at',
        'last_disconnected_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'qr_generated_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'last_disconnected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function isPairing(): bool
    {
        return in_array($this->status, [self::STATUS_QR_PENDING, self::STATUS_CONNECTING], true);
    }
}
