<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            if (empty($org->slug)) {
                $org->slug = static::generateUniqueSlug($org->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function canChangeSlug(): bool
    {
        return ! $this->users()->exists()
            && ! $this->branches()->exists()
            && ! OrganizationSubscription::withoutGlobalScopes()
                    ->where('organization_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class)->latestOfMany();
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(OrganizationUsageMonthly::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function kbCategories(): HasMany
    {
        return $this->hasMany(KbCategory::class);
    }

    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
