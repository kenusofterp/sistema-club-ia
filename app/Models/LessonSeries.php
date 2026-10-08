<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Clase que se repite semanalmente: genera una Lesson por cada día indicado entre starts_on y ends_on. */
#[Fillable(['facility_id', 'instructor_id', 'days_of_week', 'start_time', 'end_time', 'starts_on', 'ends_on', 'price', 'notes', 'created_by'])]
class LessonSeries extends Model
{
    use Auditable, BelongsToOrganization;

    protected $table = 'lesson_series';

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class)->withTrashed();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'series_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'lesson_series_member');
    }
}
