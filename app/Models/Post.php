<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'image_path', 'status', 'published_at', 'author_id'])]
class Post extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['status' => PostStatus::class, 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    public function imageUrl(): ?string
    {
        return storage_url($this->image_path);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
