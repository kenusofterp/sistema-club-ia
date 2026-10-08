<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => (string) $this->amount,
            'payment_date' => $this->payment_date->toDateString(),
            'method' => ['value' => $this->method->value, 'label' => $this->method->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'fees' => $this->whenLoaded('fees', fn () => $this->fees->map(fn ($fee) => [
                'id' => $fee->id,
                'concept' => $fee->concept,
                'amount' => (string) $fee->pivot->amount,
            ])),
        ];
    }
}
