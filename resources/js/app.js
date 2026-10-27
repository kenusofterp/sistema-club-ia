// Alpine.js se incluye con Livewire 4 (no se importa por separado para evitar doble instancia).
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.css';

// PWA: registro del service worker.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => console.warn('No se pudo registrar el service worker', error));
    });
}

// ---- Notificaciones push (PWA) ----
// <div x-data="pushToggle"> en resources/views/components/push-toggle.blade.php.
const urlBase64ToUint8Array = (base64) => {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

document.addEventListener('alpine:init', () => {
    // ---- Selector de fecha dd/mm/aaaa ----
    // <x-date-input> en resources/views/components/date-input.blade.php. Muestra d/m/Y y entrega Y-m-d (o Y-m-d\TH:i) a Livewire.
    window.Alpine.data('dateInput', ({ time }) => ({
        value: '',
        picker: null,

        init() {
            const isoFormat = time ? 'Y-m-d\\TH:i' : 'Y-m-d';
            const input = this.$refs.input;
            const toIso = (date) => (date ? flatpickr.formatDate(date, isoFormat) : '');

            this.picker = flatpickr(input, {
                locale: Spanish,
                dateFormat: time ? 'd/m/Y H:i' : 'd/m/Y',
                enableTime: time,
                time_24hr: true,
                allowInput: !input.readOnly,
                clickOpens: !input.readOnly,
                disableMobile: true,
                minDate: this.$el.dataset.min || null,
                maxDate: this.$el.dataset.max || null,
                onChange: (dates) => {
                    const iso = toIso(dates[0]);
                    if (iso !== this.value) this.value = iso;
                },
            });

            const sync = (value) => {
                if ((value || '') !== toIso(this.picker.selectedDates[0])) {
                    this.picker.setDate(value || null, false, isoFormat);
                }
            };
            this.$nextTick(() => sync(this.value));
            this.$watch('value', sync);

            // Al tipear, completa las barras: 15 → 15/, 15/03 → 15/03/.
            input.addEventListener('input', (e) => {
                if (e.inputType === 'insertText' && /^(\d{2}|\d{2}\/\d{2})$/.test(input.value)) input.value += '/';
            });

            // min/max pueden cambiar en un re-render de Livewire (el input está bajo wire:ignore).
            new MutationObserver(() => {
                this.picker.set('minDate', this.$el.dataset.min || null);
                this.picker.set('maxDate', this.$el.dataset.max || null);
            }).observe(this.$el, { attributes: true, attributeFilter: ['data-min', 'data-max'] });
        },

        destroy() {
            this.picker?.destroy();
        },
    }));

    window.Alpine.data('pushToggle', () => ({
        // unsupported | ios-install | default | subscribed | denied | working
        state: 'default',

        async init() {
            if (isIos() && !isStandalone()) {
                this.state = 'ios-install';
                return;
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) {
                this.state = 'unsupported';
                return;
            }
            if (Notification.permission === 'denied') {
                this.state = 'denied';
                return;
            }
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            this.state = subscription ? 'subscribed' : 'default';
            // Si ya estaba suscripto, se reenvía por si cambió de cuenta en este teléfono.
            if (subscription) {
                this.send('/push/suscribir', subscription);
            }
        },

        async enable() {
            this.state = 'working';
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.state = permission === 'denied' ? 'denied' : 'default';
                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                const key = document.querySelector('meta[name="vapid-public-key"]')?.content;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });
                await this.send('/push/suscribir', subscription);
                this.state = 'subscribed';
            } catch (error) {
                console.warn('No se pudieron activar las notificaciones', error);
                this.state = 'default';
            }
        },

        async disable() {
            this.state = 'working';
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await this.send('/push/desuscribir', subscription);
                await subscription.unsubscribe();
            }
            this.state = 'default';
        },

        send(url, subscription) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
        },
    }));
});

// Tiempo real (Reverb) para las pantallas de competencia y del juez.
import './echo';
