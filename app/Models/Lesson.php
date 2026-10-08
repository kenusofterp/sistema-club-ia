<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** Clase de un profesor en una sede (individual o grupal). */
#[Fillable(['facility_id', 'instructor_id', 'series_id', 'activity_id', 'date', 'start_time', 'end_time', 'status', 'price', 'notes', 'created_by', 'given_at', 'cancelled_at', 'cancel_reason'])]
class Lesson extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => LessonStatus::class,
            'date' => 'date',
            'price' => 'decimal:2',
            'given_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class)->withoutGlobalScope('organization')->withTrashed();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(LessonSeries::class, 'series_id')->withoutGlobalScope('organization');
    }

    /** Nivel / actividad (clase de nivel: alumnos = inscriptos, cuota mensual, sin cobro por clase). */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withoutGlobalScope('organization')->withTrashed();
    }

    public function isLevelLesson(): bool
    {
        return $this->activity_id !== null;
    }

    /** Título para mostrar: el nivel o, en clases particulares, los alumnos. */
    public function title(): string
    {
        if ($this->activity_id) {
            return $this->activity->name;
        }

        return match ($this->students->count()) {
            0 => 'Sin alumnos',
            1 => $this->students->first()->fullName(),
            default => $this->students->count().' alumnos',
        };
    }

    public function placeName(): string
    {
        return $this->facility?->name ?? $this->activity?->schedules->first()?->location ?? '';
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withoutGlobalScope('organization')
            ->withPivot(['id', 'subscription_id', 'attendance', 'fee_id', 'notice_at', 'notice_reason'])
            ->withTimestamps()
            ->orderBy('last_name');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', LessonStatus::Cancelled);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /** Clases que se superponen con el horario dado (mismo día). */
    public function scopeOverlapping(Builder $query, string $date, string $start, string $end): void
    {
        $query->whereDate('date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).' a '.substr($this->end_time, 0, 5);
    }

    public function minutes(): int
    {
        return (int) ((strtotime($this->end_time) - strtotime($this->start_time)) / 60);
    }

    public function isScheduled(): bool
    {
        return $this->status === LessonStatus::Scheduled;
    }

    public function attendanceOf(Member $member): ?AttendanceStatus
    {
        $student = $this->students->firstWhere('id', $member->id);

        return $student ? AttendanceStatus::from($student->pivot->attendance) : null;
    }
}
