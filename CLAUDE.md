# Sistema de Club — notas para agentes

Laravel 13 + Livewire 4 (class components en `app/Livewire`, vistas en `resources/views/livewire`) + Tailwind 4 + PostgreSQL. UI y textos en español rioplatense.

- Reglas de negocio: solo en `app/Services/*`. Lanzar `App\Exceptions\BusinessRuleException` con mensaje para el usuario; en componentes usar `$this->attempt(fn () => ..., 'mensaje')` (trait `InteractsWithUi`); en la API se convierten en 422.
- Dinero: columnas `decimal(12,2)`, aritmética con `bcadd/bcsub/bccomp` (nunca floats). Mostrar con `money()`.
- Configuración editable: `setting('clave')` (cacheado). Nuevas claves → `App\Support\SettingsCatalog` + `php artisan db:seed --class=SettingsSeeder`.
- Permisos: catálogo en `App\Support\Permissions`; super-admin vía `Gate::before`. Rutas con `can:`; acciones Livewire con `$this->authorize()`. Menú lateral en `App\Support\AdminMenu`.
- Auditoría: los modelos usan el trait `App\Models\Concerns\Auditable` (spatie/laravel-activitylog v5, columna `attribute_changes`).
- Modelos usan atributos `#[Fillable]`. Enums en `app/Enums` con `label()`/`color()`; badge con `<x-badge :status="$enum" />`.
- Layouts como componentes `<x-layouts::site|admin|portal|auth>` o `#[Layout('layouts.admin')]`. Colores de marca vía CSS vars `--brand`/`--accent` (utilidades `brand-*`, `accent-*`).
- Multi-entidad: todo modelo del dominio usa `BelongsToOrganization` (filtro global por `Organization::current()`, que fija el middleware `ResolveOrganization`). En consola/jobs usar `Organization::runFor($org, fn …)`; para cruzar entidades, `Model::acrossOrganizations()` explícito. Unicidad/existencia por entidad con `org_unique()` / `org_exists()`. Settings y auditoría (`AuditLog`) son por entidad. Roles de Spatie con *teams* (`organization_id`); super administrador = `users.is_super_admin`. Socio de la entidad actual: `$user->currentMember()` (no `$user->member`).
- Tipo de entidad club/gimnasio/mixto: `club_mode()`, `uses_gym()`, `uses_club()`. Módulos de gimnasio tras el middleware `gym`. Reglas de planes en `SubscriptionService`; el ingreso se decide en `AccessService::evaluate()`.
- No usar `$slots` como propiedad de Livewire (reservada).
- No editar Blade con `php -r`/`preg_replace` (si el patrón falla, deja el archivo vacío): usar Edit/Write.
- Tests: `php artisan test` contra `club_db_test` (PostgreSQL). Mantenerlos en verde.
