{{-- Notificaciones flotantes: $this->dispatch('notify', message: '...', type: 'success|error|info') o session('success'/'error') --}}
<div
    x-data="{
        toasts: [],
        add(e) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: e.message, type: e.type ?? 'success' });
            setTimeout(() => this.remove(id), e.type === 'error' ? 7000 : 4000);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
    }"
    x-init="
        @if (session('success')) add({ message: @js(session('success')), type: 'success' }); @endif
        @if (session('error')) add({ message: @js(session('error')), type: 'error' }); @endif
    "
    x-on:notify.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10"
        >
            <span x-show="toast.type === 'success'" class="text-emerald-600"><x-icon name="check-circle" class="size-6" /></span>
            <span x-show="toast.type === 'error'" class="text-red-600"><x-icon name="x-circle" class="size-6" /></span>
            <span x-show="toast.type === 'info'" class="text-sky-600"><x-icon name="info" class="size-6" /></span>
            <p class="flex-1 pt-0.5 text-sm text-slate-700" x-text="toast.message"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="remove(toast.id)"><x-icon name="x" class="size-4" /></button>
        </div>
    </template>
</div>
