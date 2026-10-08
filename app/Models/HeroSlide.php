<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'subtitle', 'image_path', 'button_text', 'button_url', 'secondary_button_text', 'secondary_button_url', 'overlay_opacity', 'sort_order', 'is_active'])]
class HeroSlide extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'overlay_opacity' => 'integer', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return storage_url($this->image_path);
    }
}
