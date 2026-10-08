<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facility' => $this->whenLoaded('facility', fn () => ['id' => $this->facility->id, 'name' => $this->facility->name]),
            'date' => $this->date->toDateString(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'amount' => (string) $this->amount,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
        ];
    }
}
