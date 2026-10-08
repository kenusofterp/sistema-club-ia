<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\SettingsCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[Fillable(['group', 'key', 'value', 'type', 'label', 'help', 'sort_order'])]
class Setting extends Model
{
    use Auditable, BelongsToOrganization;

    /** Clave de caché de las configuraciones de una entidad. */
    public static function cacheKey(?int $organizationId): string
    {
        return 'settings.org.'.($organizationId ?? 'none');
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => Cache::forget(self::cacheKey($s->organization_id)));
        static::deleted(fn (Setting $s) => Cache::forget(self::cacheKey($s->organization_id)));
    }

    /** @return array<string, mixed> configuraciones de la entidad actual con su valor tipado */
    public static function allCached(): array
    {
        $organizationId = Organization::currentId();

        // Sin entidad actual (p. ej. instalación sin entidades) se usan los valores por defecto del catálogo,
        // salvo las imágenes, que solo existen una vez que se crea una entidad.
        if (! $organizationId) {
            return collect(SettingsCatalog::definitions())
                ->reject(fn (array $definition) => $definition['type'] === 'image')
                ->pluck('value', 'key')
                ->all();
        }

        return Cache::rememberForever(self::cacheKey($organizationId), function () {
            return static::query()->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return;
        }

        $setting->value = match ($setting->type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => $value === null ? null : (string) $value,
        };
        $setting->save();
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => $this->value === null ? null : (int) $this->value,
            'decimal' => $this->value === null ? null : (float) $this->value,
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }

    /** URL pública de una configuración de tipo imagen. */
    public static function imageUrl(string $key): ?string
    {
        $path = static::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
