<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'member_number', 'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'gender',
    'email', 'phone', 'address', 'city', 'member_category_id', 'status', 'admission_date', 'leave_date',
    'leave_reason', 'photo_path', 'user_id', 'holder_id', 'relationship', 'emergency_contact_name',
    'emergency_contact_phone', 'medical_notes', 'notes',
])]
class Member extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, Notifiable, SoftDeletes;

    public const DOCUMENT_TYPES = ['DNI' => 'DNI', 'CI' => 'Cédula de identidad', 'PAS' => 'Pasaporte', 'OTRO' => 'Otro'];

    public const GENDERS = ['F' => 'Femenino', 'M' => 'Masculino', 'X' => 'No binario / Otro'];

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'birth_date' => 'date',
            'admission_date' => 'date',
            'leave_date' => 'date',
        ];
    }

    // ---- Relaciones ----

    public function category(): BelongsTo
    {
        return $this->belongsTo(MemberCategory::class, 'member_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'holder_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Member::class, 'holder_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', EnrollmentStatus::Active);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'enrollments')
            ->withPivot(['id', 'status', 'start_date', 'end_date'])
            ->wherePivot('status', EnrollmentStatus::Active->value);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function openFees(): HasMany
    {
        return $this->fees()->whereIn('status', FeeStatus::open());
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Planes activos y vigentes hoy. */
    public function currentSubscriptions(): HasMany
    {
        return $this->subscriptions()->current()->with('plan.activities.schedules');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    // ---- Scopes ----

    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.mb_strtolower($term).'%';
        $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like])
                ->orWhereRaw("LOWER(last_name || ' ' || first_name) LIKE ?", [$like])
                ->orWhereRaw('LOWER(document_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(member_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
        });
    }

    // ---- Reglas / helpers ----

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function sortableName(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    public function age(): int
    {
        return (int) $this->birth_date->diffInYears(now());
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    /** Saldo adeudado (cuotas abiertas: importe + recargo − pagado). */
    public function balance(): string
    {
        return (string) $this->openFees()
            ->selectRaw('COALESCE(SUM(amount + surcharge - paid_amount), 0) as balance')
            ->value('balance');
    }

    public function overdueFeesCount(): int
    {
        return $this->fees()->where('status', FeeStatus::Overdue)->count();
    }

    public function photoUrl(): ?string
    {
        return storage_url($this->photo_path);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function verificationUrl(): string
    {
        return route('member.verify', $this->uuid);
    }
}
