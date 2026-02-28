<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'title',
        'message',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isGlobal(): bool
    {
        return is_null($this->organization_id);
    }

    public function isResolved(): bool
    {
        return !is_null($this->resolved_at);
    }

    public function isSystemGenerated(): bool
    {
        return is_null($this->created_by);
    }
}
