<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Competencia con jueceo (torneo que organiza el club): niveles/grupos, ejercicios con plantilla congelada
 * al iniciar, puestos de 3 jueces y colas de pasada. Las pantallas públicas se abren con display_token.
 */
#[Fillable(['name', 'date', 'location', 'status', 'tournament_id', 'scoring_strategy', 'started_at', 'finished_at', 'created_by'])]
class Competition extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Competition $competition) {
            $competition->display_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => CompetitionStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CompetitionLevel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(CompetitionExercise::class)->orderBy('sort_order')->orderBy('id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(CompetitionStation::class)->orderBy('sort_order')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class)->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === CompetitionStatus::Draft;
    }

    public function isInProgress(): bool
    {
        return $this->status === CompetitionStatus::InProgress;
    }

    public function isFinished(): bool
    {
        return $this->status === CompetitionStatus::Finished;
    }
}
