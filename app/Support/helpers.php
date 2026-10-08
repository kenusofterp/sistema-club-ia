<?php

use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (Throwable) {
            // Antes de migrar la base (instalación) no hay tabla de configuraciones.
            return $default;
        }
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount): string
    {
        $symbol = setting('club.currency_symbol', '$');
        $decimals = (int) setting('club.currency_decimals', 2);

        return $symbol.' '.number_format((float) $amount, $decimals, ',', '.');
    }
}

if (! function_exists('storage_url')) {
    function storage_url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('club_mode')) {
    /** Tipo de la entidad actual: club, gimnasio o mixto (club con gimnasio). */
    function club_mode(): string
    {
        return Organization::current()?->type ?? 'club';
    }
}

if (! function_exists('uses_gym')) {
    function uses_gym(): bool
    {
        return club_mode() !== 'club';
    }
}

if (! function_exists('uses_club')) {
    function uses_club(): bool
    {
        return club_mode() !== 'gimnasio';
    }
}

if (! function_exists('org_unique')) {
    /** Regla de unicidad limitada a la entidad actual (p. ej. el mismo DNI puede ser socio en dos clubes). */
    function org_unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)->where('organization_id', Organization::currentId());
    }
}

if (! function_exists('org_exists')) {
    /** Regla "exists" limitada a la entidad actual. */
    function org_exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('organization_id', Organization::currentId());
    }
}
