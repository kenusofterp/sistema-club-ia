<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Entidad administrada (club o gimnasio). Todo el dominio se filtra por la entidad "actual",
 * que resuelve el middleware ResolveOrganization (por dominio en la web pública, por sesión en
 * el panel y el portal) o se fija explícitamente en comandos y jobs con runFor().
 */
#[Fillable(['name', 'slug', 'type', 'domain', 'is_default', 'is_active'])]
class Organization extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPES = [
        'club' => 'Club',
        'gimnasio' => 'Gimnasio',
        'mixto' => 'Club con gimnasio',
    ];

    private static ?Organization $current = null;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('organizations.hosts'));
    }

    // ---- Entidad actual ----

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function currentId(): ?int
    {
        return self::$current?->id;
    }

    public static function setCurrent(?self $organization): void
    {
        self::$current = $organization;
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization?->id);
    }

    /**
     * Ejecuta un bloque en el contexto de una entidad y restaura la anterior.
     *
     * @template T
     *
     * @param  callable(self): T  $callback
     * @return T
     */
    public static function runFor(self|int $organization, callable $callback): mixed
    {
        $organization = $organization instanceof self ? $organization : self::query()->findOrFail($organization);
        $previous = self::$current;
        self::setCurrent($organization);

        try {
            return $callback($organization);
        } finally {
            self::setCurrent($previous);
        }
    }

    /** Entidad que corresponde a un host: dominio propio, subdominio "{slug}.*" o la entidad por defecto. */
    public static function forHost(string $host): ?self
    {
        $host = strtolower($host);

        $match = self::query()->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('domain', $host)->orWhere('slug', explode('.', $host)[0]))
            ->orderByRaw('domain = ? desc', [$host])
            ->first();

        return $match ?? self::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    // ---- Relaciones ----

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    // ---- Helpers ----

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderByDesc('is_default')->orderBy('name');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function usesGym(): bool
    {
        return $this->type !== 'club';
    }

    public function usesClub(): bool
    {
        return $this->type !== 'gimnasio';
    }

    /** URL pública de la entidad (dominio propio o subdominio del dominio de la aplicación). */
    public function url(string $path = '/'): string
    {
        $base = parse_url(config('app.url'));
        $scheme = $base['scheme'] ?? 'http';
        $port = isset($base['port']) ? ':'.$base['port'] : '';
        $host = $this->domain ?: ($this->is_default ? $base['host'] : $this->slug.'.'.($base['host'] ?? 'localhost'));

        return "{$scheme}://{$host}{$port}".'/'.ltrim($path, '/');
    }
}
