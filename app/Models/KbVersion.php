<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbVersion extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'kb_article_id',
        'version_number',
        'title',
        'content',
        'changed_by',
        'change_summary',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
