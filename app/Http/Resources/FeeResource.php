<?php

namespace App\Http\Resources;

use App\Models\Fee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fee */
class FeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'concept' => $this->concept,
            'period' => $this->period?->format('Y-m'),
            'amount' => (string) $this->amount,
            'surcharge' => (string) $this->surcharge,
            'paid_amount' => (string) $this->paid_amount,
            'balance' => $this->balance(),
            'due_date' => $this->due_date->toDateString(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
        ];
    }
}
