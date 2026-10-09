<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Mensaje enviado a un grupo de alumnos (push + portal). */
#[Fillable(['sender_id', 'title', 'body', 'context'])]
class MemberMessage extends Model
{
    use Auditable, BelongsToOrganization;

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_message_recipients')->withPivot('read_at');
    }
}
