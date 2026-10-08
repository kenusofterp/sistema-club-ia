<?php

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Member */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'member_number' => $this->member_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'document' => ['type' => $this->document_type, 'number' => $this->document_number],
            'birth_date' => $this->birth_date?->toDateString(),
            'email' => $this->email,
            'phone' => $this->phone,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'admission_date' => $this->admission_date?->toDateString(),
            'photo_url' => $this->photoUrl(),
            'verification_url' => $this->verificationUrl(),
        ];
    }
}
