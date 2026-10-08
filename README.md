# Sistema de gestión de Club Social y Deportivo

Sitio institucional configurable + panel de administración + portal de socios (PWA) + API.

**Stack:** Laravel 13 · PHP 8.4+ · PostgreSQL · Livewire 4 (incluye Alpine.js) · Tailwind CSS 4 · Spatie Permission / Activitylog · Sanctum · Scheduler + Queue.

## Puesta en marcha

```bash
composer setup                 # instala dependencias, migra, siembra datos base y compila assets
php artisan db:seed --class=DemoSeeder   # (opcional) 2 clubes + 1 gimnasio con socios, cuotas, pagos y planes
composer dev                   # servidor + cola + logs + Vite
php artisan schedule:work      # en otra terminal: procesos automáticos
```

Configurar antes en `.env`: conexión `DB_*` a PostgreSQL y `SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`
(con comillas simples si la clave tiene `$`). Para reinstalar limpio: `php artisan migrate:fresh --seed`.

- Sitio: `http://localhost:8000`
- Ingreso: `/ingresar` → el personal entra a `/admin`, los socios a `/portal`.
- Demo (solo con `DemoSeeder`): socio en las tres entidades `socio@demo.com` / `Socio1234`; personal `tesoreria@demo.com` (tesorero en ambos clubes) y `recepcion@demo.com` (recepción del gimnasio) / `Demo1234`.

## Varias entidades (clubes y gimnasios)

La plataforma administra **varias entidades independientes** (*Administración > Entidades*, solo super administrador):

- Cada entidad tiene sus propios socios, categorías, cuotas, pagos y recibos (numeración propia), actividades, planes, instalaciones, configuración, auditoría y **sitio web**.
- **Sitio por entidad:** se resuelve por dominio propio (campo *Dominio*) o por subdominio con su identificador. En desarrollo: `http://localhost:8000` (principal), `http://atletico.localhost:8000`, `http://gimnasio.localhost:8000`.
- **Una persona, una cuenta:** puede ser socia de varias entidades (una membresía y un número de socio en cada una). Al aprobarla en otra entidad, se vincula a su cuenta existente (mismo correo o documento). En el portal elige la membresía desde su menú. El QR del carnet de un club sirve para identificarla en el gimnasio si también es socia ahí.
- **Personal:** los roles se asignan por entidad (*Usuarios*, dentro de cada entidad). Quien administra varias cambia con el selector de la barra superior. El super administrador (marca en el usuario) accede a todas.
- **Procesos automáticos:** recorren cada entidad con su propia configuración (día de generación, recargo, recordatorios).
- En producción con subdominios, configurar `SESSION_DOMAIN=.tudominio.com` si se quiere compartir la sesión entre ellos.

## Tipo de entidad: club, gimnasio o club con gimnasio

Se define al crear la entidad (*Administración > Entidades*):

| Modo | Uso típico | Ingreso |
|---|---|---|
| **Club** | Cuota social por categoría + actividades con inscripción mensual | Socio activo y sin deuda vencida |
| **Gimnasio** | Planes / membresías | Exige un plan vigente que permita entrar en ese momento |
| **Club con gimnasio** | Ambos | Configurable: con o sin plan obligatorio |

**Planes** (*Gimnasio > Planes y precios*): precio y duración (días o meses); acceso *libre* (sala/aparatos), *solo clases incluidas* o *libre + clases*; límite de visitas por semana, mes o plan (un ingreso por día); franjas horarias (ej. «Pase mañana» L a V de 6 a 14 h).
Las clases de gimnasio son actividades con «Admite inscripción mensual» desmarcado: solo se accede con un plan que las incluya, desde N minutos antes del inicio hasta el final.

**Ciclo del plan:** al contratarlo (desde la administración o el portal) se genera el cargo; se activa al pagarse (configurable). Un plan nuevo pagado tarde cuenta su vigencia desde la activación. Las renovaciones automáticas se generan N días antes del vencimiento; las altas impagas se cancelan a los N días.

## Módulos

| Área | Funcionalidad |
|---|---|
| Sitio institucional | Hero con diapositivas, secciones ordenables (club, beneficios, cifras, actividades, instalaciones, noticias, asociate, contacto, texto libre), noticias, páginas, formulario de contacto y solicitud de asociación. Nombre, logo, colores, contacto, redes, SEO y PWA se editan en *Sitio web > Identidad y contacto*. |
| Socios | Padrón, categorías por edad, grupo familiar, aprobación de solicitudes, suspensión/baja/reactivación, acceso al portal, ficha con cuenta corriente, actividades, reservas, accesos e historial. |
| Tesorería | Cuotas sociales y de actividades por período, cargos manuales, vencimientos con recargo, pagos con imputación automática (más antiguo primero), anulación con reversión, recibos imprimibles, exportación CSV. |
| Actividades | Disciplinas con horarios, cupos, rango de edad, profesor e inscripciones. |
| Instalaciones | Turnos configurables, agenda diaria, reservas sin superposición con cargo automático. |
| Control de acceso | Lectura de QR del carnet (cámara o lector), N° de socio o documento; bloqueo por estado o deuda. |
| Administración | Usuarios, roles y permisos editables, auditoría completa (cambios, ingresos, intentos fallidos), configuración de reglas del club. |
| Portal del socio (PWA) | Estado de cuenta, recibos, inscripción a actividades, reservas, carnet digital con QR, datos personales. Instalable en el celular. |

## Reglas de negocio principales

Están en `app/Services` y son configurables en *Administración > Configuración del club*:

- Generación mensual idempotente (índice único parcial en PostgreSQL): no se duplican cuotas.
- Vencimiento diario con recargo porcentual aplicado una sola vez.
- Inscripción: socio activo, edad dentro del rango, cupo disponible (con bloqueo de fila), sin deuda vencida (configurable).
- Reservas: dentro del horario y la grilla de turnos, sin superposición (bloqueo por instalación), anticipación máxima, cancelación por el socio con horas mínimas.
- Pagos: no pueden superar el saldo seleccionado; anular un pago revierte la imputación; un cargo con pagos no se puede anular.
- Acceso: se deniega a socios no activos o con N cuotas vencidas (configurable).

## Procesos automáticos (`routes/console.php`)

| Comando | Cuándo |
|---|---|
| `club:generar-cuotas [--periodo=AAAA-MM] [--sync]` | Diario 01:00, actúa el día configurado del mes (encola un job por socio) |
| `club:marcar-vencidas` | Diario 00:30 |
| `club:procesar-planes` | Diario 00:45 (vence planes, genera renovaciones, cancela altas impagas) |
| `club:recordatorios` | Diario 09:00 (próximos a vencer; vencidas los lunes) |
| `activitylog:clean`, `queue:prune-failed`, `sanctum:prune-expired` | Mantenimiento |

En producción: cron `* * * * * php artisan schedule:run` y un worker `php artisan queue:work`.
Los correos (bienvenida, recibos, recordatorios, contacto) se envían en cola; en desarrollo `MAIL_MAILER=log`.

**Redis (opcional):** con Redis instalado, usar `CACHE_STORE=redis` y `QUEUE_CONNECTION=redis` (cliente `predis` ya incluido).

## API (`/api/v1`, Sanctum)

`POST /auth/token` (email, password, device_name) → token Bearer.
Públicos: `GET /site`, `GET /activities`, `GET /plans`.
Socio: `GET /me`, `/me/fees[?open=1]`, `/me/payments`, `/me/subscriptions`, `/me/enrollments`, `POST /me/enrollments`, `DELETE /me/enrollments/{activityId}`,
`GET|POST /me/reservations`, `DELETE /me/reservations/{id}`, `GET /facilities/{id}/availability?date=AAAA-MM-DD`.
Personal: `POST /access/check` (code) para molinetes/lectores.
Las reglas de negocio violadas devuelven `422` con `message`.

## Tests

```bash
php artisan test
```

Usan la base PostgreSQL `club_db_test` (ver `phpunit.xml`).

## Logo

Subirlo en *Sitio web > Identidad y contacto*: logo, logo para fondos oscuros, favicon e ícono de la app (512×512).
Los colores de marca se aplican en todo el sistema al guardar.
