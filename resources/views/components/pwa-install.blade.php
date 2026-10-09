{{-- Aviso "Instalar app" (PWA). Android/PC: abre el diálogo del navegador; iPhone/iPad: muestra los pasos en Safari. --}}
<div x-data="{
        canPrompt: !! window.pwaInstall?.prompt,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
        standalone: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
        dismissed: (() => { try { return Number(localStorage.getItem('pwa-install-dismissed') || 0) > Date.now() } catch (e) { return false } })(),
        iosHelp: false,
        get visible() { return ! this.standalone && ! this.dismissed && (this.canPrompt || this.ios) },
        async install() {
            if (! this.canPrompt) { this.iosHelp = true; return }
            const event = window.pwaInstall.prompt;
            event.prompt();
            await event.userChoice;
            window.pwaInstall.prompt = null;
            this.canPrompt = false;
        },
        dismiss() {
            this.dismissed = true;
            try { localStorage.setItem('pwa-install-dismissed', Date.now() + 30 * 864e5) } catch (e) {}
        },
     }"
     x-on:pwa-installable.window="canPrompt = true"
     x-on:pwa-installed.window="canPrompt = false; standalone = true"
     x-show="visible" x-cloak
     class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
    <div class="flex items-center gap-3">
        <img src="{{ route('pwa.icon', 192) }}" alt="" class="size-11 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900">Instalá la app del club</p>
            <p class="text-xs text-slate-500">Tené tu carnet, cuotas y reservas a un toque desde la pantalla de inicio.</p>
        </div>
        <button type="button" x-on:click="install()"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-sm font-medium text-white hover:bg-brand-800">
            <x-icon name="download" class="size-4" /> Instalar
        </button>
        <button type="button" x-on:click="dismiss()" class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Ahora no">
            <x-icon name="x" class="size-4" />
        </button>
    </div>
    <div x-show="iosHelp" x-cloak x-transition class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
        <p class="mb-1 font-medium text-slate-800">Para instalarla en iPhone o iPad:</p>
        <ol class="list-decimal space-y-0.5 pl-5">
            <li>Abrí esta página en <strong>Safari</strong>.</li>
            <li>Tocá el botón <strong>Compartir</strong> (el cuadrado con la flecha hacia arriba).</li>
            <li>Elegí <strong>Agregar a inicio</strong> y confirmá.</li>
        </ol>
    </div>
</div>
