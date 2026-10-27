<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Participante de competencias (puede ser de otra institución). Opcionalmente vinculada a un socio. */
#[Fillable(['first_name', 'last_name', 'document_number', 'institution', 'member_id'])]
class Competitor extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function sortableName(): string
    {
        return trim("{$this->last_name}, {$this->first_name}", ', ');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.mb_strtolower($term).'%';
        $query->where(function (Builder $q) use ($like) {
            $q->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like])
                ->orWhereRaw("LOWER(last_name || ' ' || first_name) LIKE ?", [$like])
                ->orWhereRaw('LOWER(document_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(institution) LIKE ?', [$like]);
        });
    }
}
