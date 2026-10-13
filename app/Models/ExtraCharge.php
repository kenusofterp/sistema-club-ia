<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Adicional (vestimenta / artículo) que se cobra a los alumnos elegidos: de un torneo
 * (ej. la malla) o suelto (ej. la remera de un nivel). Cada alumno tiene su propio cargo.
 */
#[Fillable(['name', 'description', 'price', 'due_date', 'tournament_id', 'activity_id', 'created_by'])]
class ExtraCharge extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class)->withTrashed();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Para qué es: «Torneo Primavera», «Juvenil A» o «Alumnos elegidos». */
    public function contextLabel(): string
    {
        return match (true) {
            $this->tournament_id !== null => 'Torneo '.$this->tournament?->name,
            $this->activity_id !== null => (string) $this->activity?->name,
            default => 'Alumnos elegidos',
        };
    }
}
