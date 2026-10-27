// Tiempo real (Laravel Reverb). Solo se conecta en las páginas que lo piden con <meta name="realtime" content="1">
// (pantallas de competencia y del juez) y si hay clave configurada; el resto del sistema no abre WebSockets.
// Si no hay conexión, esas pantallas se refrescan solas cada pocos segundos (ver x-data="realtimeFallback").
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const wantsRealtime = document.querySelector('meta[name="realtime"]')?.content === '1';

if (wantsRealtime && import.meta.env.VITE_REVERB_APP_KEY) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
