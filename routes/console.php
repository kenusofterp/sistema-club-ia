<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Procesos automáticos del club
|--------------------------------------------------------------------------
| Requiere en el servidor: `php artisan schedule:work` (desarrollo) o un cron
| `* * * * * php artisan schedule:run`, y un worker `php artisan queue:work`.
*/

// Generación mensual de cuotas: corre a diario y cada entidad genera el día configurado en su "Configuración del club".
Schedule::command('club:generar-cuotas')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

// Vencimiento de cuotas impagas y aplicación de recargo.
Schedule::command('club:marcar-vencidas')->dailyAt('00:30')->withoutOverlapping()->onOneServer();

// Planes de gimnasio: vencimientos, renovaciones automáticas y altas impagas.
Schedule::command('club:procesar-planes')->dailyAt('00:45')->withoutOverlapping()->onOneServer();

// Recordatorios por correo.
Schedule::command('club:recordatorios')->dailyAt('09:00')->withoutOverlapping()->onOneServer();

// Mantenimiento.
Schedule::command('activitylog:clean')->weekly();
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
