<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Persona del sistema (base única, no pertenece a ninguna entidad).
 * Sus membresías (Member) son por entidad y copian los datos personales; Member mantiene la sincronización.
 */
#[Fillable([
    'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'gender', 'email', 'phone',
    'address', 'city', 'photo_path', 'user_id', 'emergency_contact_name', 'emergency_contact_phone', 'medical_notes',
])]
class Person extends Model
{
    use Auditable, SoftDeletes;

    /** Datos que comparten todas las membresías de la persona. */
    public const PERSONAL_FIELDS = [
        'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'gender', 'email', 'phone',
        'address', 'city', 'photo_path', 'emergency_contact_name', 'emergency_contact_phone', 'medical_notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Person $person) {
            $person->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Membresías en todas las entidades. */
    public function memberships(): HasMany
    {
        return $this->hasMany(Member::class)->withoutGlobalScope('organization');
    }

    public static function findByDocument(?string $type, ?string $number): ?self
    {
        if (! $type || ! $number) {
            return null;
        }

        return static::where('document_type', $type)->where('document_number', trim($number))->first();
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
                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
        });
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** @return array<string, mixed> datos personales para copiar en una membresía */
    public function personalData(): array
    {
        return [
            ...$this->only(self::PERSONAL_FIELDS),
            'birth_date' => $this->birth_date?->toDateString(),
        ];
    }
}
