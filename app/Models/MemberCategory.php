<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'monthly_fee', 'admission_fee', 'min_age', 'max_age', 'is_active', 'sort_order'])]
class MemberCategory extends Model
{
    use Auditable, BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'admission_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'min_age' => 'integer',
            'max_age' => 'integer',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function acceptsAge(int $age): bool
    {
        return ($this->min_age === null || $age >= $this->min_age)
            && ($this->max_age === null || $age <= $this->max_age);
    }

    public function ageRangeLabel(): string
    {
        return match (true) {
            $this->min_age !== null && $this->max_age !== null => "{$this->min_age} a {$this->max_age} años",
            $this->min_age !== null => "Desde {$this->min_age} años",
            $this->max_age !== null => "Hasta {$this->max_age} años",
            default => 'Todas las edades',
        };
    }
}
