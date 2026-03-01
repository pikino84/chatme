<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbArticle extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'kb_category_id',
        'created_by',
        'updated_by',
        'title',
        'slug',
        'content',
        'status',
        'priority',
        'visible_on_webchat',
        'visible_on_whatsapp',
        'visible_on_instagram',
        'visible_on_facebook',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'visible_on_webchat' => 'boolean',
            'visible_on_whatsapp' => 'boolean',
            'visible_on_instagram' => 'boolean',
            'visible_on_facebook' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KbVersion::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isVisibleOn(string $channel): bool
    {
        return match ($channel) {
            'webchat' => $this->visible_on_webchat,
            'whatsapp' => $this->visible_on_whatsapp,
            'instagram' => $this->visible_on_instagram,
            'facebook' => $this->visible_on_facebook,
            default => false,
        };
    }
}
