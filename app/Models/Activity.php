<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
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

/**
 * Actividad / disciplina deportiva o cultural del club.
 * (No confundir con Spatie\Activitylog\Models\Activity, que es el registro de auditoría.)
 */
#[Fillable(['name', 'slug', 'summary', 'description', 'image_path', 'monthly_fee', 'capacity', 'min_age', 'max_age', 'instructor_id', 'is_public', 'allows_enrollment', 'is_active'])]
class Activity extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'capacity' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'is_public' => 'boolean',
            'allows_enrollment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ActivitySchedule::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', EnrollmentStatus::Active);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('name');
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->where('is_public', true)->orderBy('name');
    }

    public function availableSpots(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $used = $this->active_enrollments_count ?? $this->activeEnrollments()->count();

        return max(0, $this->capacity - $used);
    }

    public function acceptsAge(int $age): bool
    {
        return ($this->min_age === null || $age >= $this->min_age)
            && ($this->max_age === null || $age <= $this->max_age);
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
