<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ficha médica de un socio: respuestas por id de campo (MedicalFormField). */
#[Fillable(['member_id', 'answers', 'updated_by'])]
class MedicalRecord extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return ['answers' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function answer(MedicalFormField $field): mixed
    {
        return ($this->answers ?? [])[$field->id] ?? null;
    }
}
