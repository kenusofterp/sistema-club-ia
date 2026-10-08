// Alpine.js se incluye con Livewire 4 (no se importa por separado para evitar doble instancia).

// PWA: registro del service worker.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => console.warn('No se pudo registrar el service worker', error));
    });
}
