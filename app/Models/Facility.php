<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'image_path', 'hourly_rate', 'capacity', 'opens_at', 'closes_at', 'slot_minutes', 'max_slots_per_booking', 'is_public', 'is_bookable', 'is_active'])]
class Facility extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'capacity' => 'integer',
            'slot_minutes' => 'integer',
            'max_slots_per_booking' => 'integer',
            'is_public' => 'boolean',
            'is_bookable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Profesores que dan clases en esta sede. */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function scopeBookable(Builder $query): void
    {
        $query->where('is_active', true)->where('is_bookable', true)->orderBy('name');
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->where('is_public', true)->orderBy('name');
    }

    public function imageUrl(): ?string
    {
        return storage_url($this->image_path);
    }

    /**
     * Turnos del día según horario de apertura y duración del turno.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function slots(): array
    {
        $slots = [];
        $start = strtotime('1970-01-01 '.substr($this->opens_at, 0, 5));
        $close = strtotime('1970-01-01 '.substr($this->closes_at, 0, 5));
        $step = max(15, $this->slot_minutes) * 60;

        for ($t = $start; $t + $step <= $close; $t += $step) {
            $slots[] = ['start' => date('H:i', $t), 'end' => date('H:i', $t + $step)];
        }

        return $slots;
    }
}
