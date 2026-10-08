<?php

namespace App\Models;

use App\Enums\PlanAccessType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Plan / membresía de gimnasio.
 * access_windows: [{"days":[1..7], "from":"06:00", "to":"14:00"}] — vacío = cualquier horario de apertura.
 */
#[Fillable(['name', 'instructor_id', 'description', 'price', 'duration_unit', 'duration_value', 'access_type', 'visit_limit', 'visit_period', 'access_windows', 'is_featured', 'is_public', 'is_active', 'sort_order'])]
class Plan extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    public const DURATION_UNITS = ['meses' => 'Meses', 'dias' => 'Días'];

    public const VISIT_PERIODS = ['semana' => 'por semana', 'mes' => 'por mes', 'plan' => 'en todo el plan'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_value' => 'integer',
            'access_type' => PlanAccessType::class,
            'visit_limit' => 'integer',
            'access_windows' => 'array',
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Profesor dueño del pack (null = plan de la entidad). */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('price');
    }

    /** Planes de la entidad (excluye los packs de clases de los profesores). */
    public function scopeOfOrganization(Builder $query): void
    {
        $query->whereNull('instructor_id');
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->orderBy('price');
    }

    /** Último día de vigencia para un plan que comienza en $start. */
    public function endDateFrom(Carbon $start): Carbon
    {
        $end = $this->duration_unit === 'dias'
            ? $start->copy()->addDays($this->duration_value)
            : $start->copy()->addMonthsNoOverflow($this->duration_value);

        return $end->subDay();
    }

    public function durationLabel(): string
    {
        $unit = $this->duration_unit === 'dias'
            ? ($this->duration_value === 1 ? 'día' : 'días')
            : ($this->duration_value === 1 ? 'mes' : 'meses');

        return "{$this->duration_value} {$unit}";
    }

    public function visitLimitLabel(): string
    {
        return $this->visit_limit
            ? $this->visit_limit.' '.($this->visit_limit === 1 ? 'visita' : 'visitas').' '.(self::VISIT_PERIODS[$this->visit_period] ?? '')
            : 'Visitas ilimitadas';
    }

    /** ¿El plan permite ingresar en este momento según sus franjas horarias? */
    public function allowsTime(Carbon $at): bool
    {
        $windows = $this->access_windows ?? [];
        if ($windows === []) {
            return true;
        }

        $time = $at->format('H:i');
        foreach ($windows as $window) {
            if (in_array($at->dayOfWeekIso, array_map('intval', $window['days'] ?? []), true)
                && $time >= ($window['from'] ?? '00:00') && $time <= ($window['to'] ?? '23:59')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> descripción legible de las franjas horarias */
    public function windowsLabels(): array
    {
        return collect($this->access_windows ?? [])->map(function ($window) {
            $days = collect($window['days'] ?? [])->sort()->map(fn ($d) => mb_substr(ActivitySchedule::DAYS[(int) $d] ?? '', 0, 3))->implode(', ');

            return "{$days} de {$window['from']} a {$window['to']}";
        })->all();
    }
}
