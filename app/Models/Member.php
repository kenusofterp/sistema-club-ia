<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\MemberStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'member_number', 'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'gender',
    'email', 'phone', 'address', 'city', 'member_category_id', 'status', 'admission_date', 'leave_date',
    'leave_reason', 'photo_path', 'person_id', 'user_id', 'holder_id', 'relationship', 'emergency_contact_name',
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
            $member->attachPerson();
        });

        static::updating(function (Member $member) {
            if ($member->isDirty(['document_type', 'document_number'])) {
                $other = Person::findByDocument($member->document_type, $member->document_number);
                if ($other && $other->id !== $member->person_id) {
                    throw new BusinessRuleException('Ese documento ya corresponde a otra persona registrada en el sistema.');
                }
            }
        });

        static::saved(function (Member $member) {
            $member->syncPerson();
        });
    }

    /**
     * Vincula la membresía con la persona del sistema (por documento) o la crea.
     * Los datos que la membresía no trae se completan con los de la persona.
     */
    private function attachPerson(): void
    {
        $person = $this->person_id
            ? Person::find($this->person_id)
            : Person::findByDocument($this->document_type, $this->document_number);

        if (! $person) {
            $this->person_id = Person::create([...$this->personalAttributes(), 'user_id' => $this->user_id])->id;

            return;
        }

        foreach ($person->personalData() as $field => $value) {
            if ($this->{$field} === null || $this->{$field} === '') {
                $this->{$field} = $value;
            }
        }
        $this->user_id ??= $person->user_id;
        $this->person_id = $person->id;
    }

    /** Lleva los cambios de datos personales a la persona y al resto de sus membresías. */
    private function syncPerson(): void
    {
        $fields = $this->wasRecentlyCreated
            ? Person::PERSONAL_FIELDS
            : array_values(array_intersect(Person::PERSONAL_FIELDS, array_keys($this->getChanges())));

        $person = $this->person()->first();
        if (! $person) {
            return;
        }

        $changes = array_intersect_key($this->personalAttributes(), array_flip($fields));
        if ($this->user_id && ! $person->user_id) {
            $person->user_id = $this->user_id;
        }
        $person->fill($changes);
        if ($person->isDirty()) {
            $person->save();
        }

        if ($changes) {
            Member::acrossOrganizations()->withTrashed()
                ->where('person_id', $person->id)
                ->whereKeyNot($this->id)
                ->update([...$changes, 'updated_at' => now()]);
        }
    }

    /** @return array<string, mixed> */
    private function personalAttributes(): array
    {
        return [
            ...$this->only(Person::PERSONAL_FIELDS),
            'birth_date' => $this->birth_date?->toDateString(),
        ];
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'holder_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Member::class, 'holder_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function tournamentParticipations(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    /** Mensajes recibidos (profesor / administración). */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(MemberMessage::class, 'member_message_recipients')->withPivot('read_at');
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

    /** Las notificaciones push llegan a los teléfonos donde la cuenta del socio las activó. */
    public function routeNotificationForWebPush(): Collection
    {
        return $this->user?->pushSubscriptions ?? new Collection;
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class)
            ->withPivot(['id', 'subscription_id', 'attendance', 'fee_id', 'notice_at', 'notice_reason'])
            ->withTimestamps();
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

    /**
     * Cuotas vencidas con la entidad. Las deudas con un profesor y los torneos no bloquean
     * ingreso, reservas ni inscripciones.
     */
    public function overdueFeesCount(): int
    {
        return $this->fees()
            ->where('status', FeeStatus::Overdue)
            ->whereNull('instructor_id')
            ->where('type', '!=', FeeType::Tournament)
            ->count();
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
