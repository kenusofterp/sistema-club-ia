<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso. Una misma persona puede:
 * - administrar una o varias entidades (roles por entidad), o todas si es super administrador;
 * - ser socia de varias entidades (una membresía por entidad, ver members.user_id).
 */
#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path', 'is_active', 'last_login_at', 'preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, Notifiable, SoftDeletes;

    protected array $auditExcept = ['password', 'remember_token', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /** Membresía en la entidad actual. */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /** @var array<int|string, Member|null> membresía resuelta por entidad (evita reutilizar la de otra entidad) */
    private array $currentMemberCache = [];

    /** Membresía en la entidad actual, resuelta por entidad. */
    public function currentMember(): ?Member
    {
        $key = Organization::currentId() ?? 'none';

        if (! array_key_exists($key, $this->currentMemberCache)) {
            $this->currentMemberCache[$key] = $this->member()->first();
        }

        return $this->currentMemberCache[$key];
    }

    /** Membresías en todas las entidades. */
    public function memberships(): HasMany
    {
        return $this->hasMany(Member::class)->withoutGlobalScope('organization');
    }

    /** Sedes donde da clases el profesor, de todas las entidades. */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class)->withoutGlobalScope('organization');
    }

    /** Clases que dicta, de todas las entidades. */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'instructor_id')->withoutGlobalScope('organization');
    }

    public function preference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences ?? [], $key, $default);
    }

    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->forceFill(['preferences' => $preferences])->saveQuietly();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** @return array<int, int> entidades que el usuario puede administrar */
    public function adminOrganizationIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Organization::active()->pluck('id')->all();
        }

        return DB::table(config('permission.table_names.model_has_roles'))
            ->join('organizations', 'organizations.id', '=', 'model_has_roles.organization_id')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('organizations.is_active', true)
            ->whereNull('organizations.deleted_at')
            ->distinct()
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @var array<string, array<int, int>> */
    private array $permissionOrganizationsCache = [];

    /**
     * Entidades (activas) donde el usuario tiene un permiso por alguno de sus roles.
     * Sirve para pantallas que cruzan entidades, donde can() solo mira la entidad actual.
     *
     * @return array<int, int>
     */
    public function organizationIdsWith(string $permission): array
    {
        if ($this->isSuperAdmin()) {
            return $this->adminOrganizationIds();
        }

        return $this->permissionOrganizationsCache[$permission] ??= DB::table(config('permission.table_names.model_has_roles').' as mr')
            ->join(config('permission.table_names.role_has_permissions').' as rp', 'rp.role_id', '=', 'mr.role_id')
            ->join(config('permission.table_names.permissions').' as p', 'p.id', '=', 'rp.permission_id')
            ->join('organizations as o', 'o.id', '=', 'mr.organization_id')
            ->where('mr.model_type', $this->getMorphClass())
            ->where('mr.model_id', $this->id)
            ->where('p.name', $permission)
            ->where('o.is_active', true)
            ->whereNull('o.deleted_at')
            ->distinct()
            ->pluck('mr.organization_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Personal activo que tiene un permiso en una entidad (por sus roles allí). No incluye a los
     * super administradores, para no avisarles de cada entidad.
     *
     * @return Collection<int, User>
     */
    public static function withPermissionIn(string $permission, int $organizationId): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->whereIn('id', DB::table(config('permission.table_names.model_has_roles').' as mr')
                ->join(config('permission.table_names.role_has_permissions').' as rp', 'rp.role_id', '=', 'mr.role_id')
                ->join(config('permission.table_names.permissions').' as p', 'p.id', '=', 'rp.permission_id')
                ->where('mr.model_type', (new static)->getMorphClass())
                ->where('mr.organization_id', $organizationId)
                ->where('p.name', $permission)
                ->select('mr.model_id'))
            ->get();
    }

    public function hasPermissionIn(string $permission, int $organizationId): bool
    {
        return in_array($organizationId, $this->organizationIdsWith($permission), true);
    }

    /** @return array<int, int> entidades donde la persona es socia (no rechazada) */
    public function membershipOrganizationIds(): array
    {
        return $this->memberships()
            ->whereNotIn('status', [MemberStatus::Rejected->value, MemberStatus::Pending->value])
            ->whereHas('organization', fn ($q) => $q->where('is_active', true))
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Personal del club: super administrador (aunque todavía no haya entidades, para crear la primera)
     * o con algún rol en alguna entidad.
     */
    public function canAccessAdmin(): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || $this->adminOrganizationIds() !== []);
    }

    public function canAccessPortal(): bool
    {
        return $this->is_active && $this->membershipOrganizationIds() !== [];
    }

    public function homeRoute(): string
    {
        if (! $this->canAccessAdmin()) {
            return route('portal.dashboard');
        }

        // Profesores sin tablero: entran directo a sus clases del día.
        if (! $this->isSuperAdmin() && $this->organizationIdsWith('dashboard.ver') === [] && $this->organizationIdsWith('agenda.ver') !== []) {
            return route('admin.lessons.today');
        }

        return route('admin.dashboard');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
