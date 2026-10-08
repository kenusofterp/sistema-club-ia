<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activity_id', 'day_of_week', 'start_time', 'end_time', 'location'])]
class ActivitySchedule extends Model
{
    public const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).' a '.substr($this->end_time, 0, 5);
    }
}
