<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ejercicio o rutina de competencia (Salto, Suelo, Viga…) con su plantilla de criterios. Se reutiliza entre competencias. */
#[Fillable(['name', 'description', 'is_active', 'sort_order'])]
class Exercise extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ExerciseCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
