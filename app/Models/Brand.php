<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'logo_url',
        'color',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name) ?: Str::random(8);
            }
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }

    public function kbCategories(): HasMany
    {
        return $this->hasMany(KbCategory::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getAiContext(): ?string
    {
        return $this->settings['ai_context'] ?? null;
    }
}
