<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'monthly_fee' => (string) $this->monthly_fee,
            'capacity' => $this->capacity,
            'available_spots' => $this->availableSpots(),
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'day_of_week' => $s->day_of_week,
                'day' => $s->dayName(),
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'location' => $s->location,
            ])),
        ];
    }
}
