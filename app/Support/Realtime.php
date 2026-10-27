<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Envía eventos de tiempo real (Reverb) después de confirmar la transacción. Si el servidor de WebSockets
 * no responde, se registra el error pero la operación (p. ej. confirmar una nota) no falla: las pantallas
 * igual se ponen al día con su refresco periódico.
 */
final class Realtime
{
    public static function send(object $event): void
    {
        DB::afterCommit(fn () => rescue(fn () => event($event)));
    }
}
