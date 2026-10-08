<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'body', 'image_path', 'meta_description', 'show_in_menu', 'menu_order', 'is_published'])]
class Page extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return ['show_in_menu' => 'boolean', 'is_published' => 'boolean', 'menu_order' => 'integer'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
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
