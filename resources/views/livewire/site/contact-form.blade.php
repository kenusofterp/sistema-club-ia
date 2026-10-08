<div class="card p-6 sm:p-8">
    @if ($sent)
        <div class="py-10 text-center">
            <div class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-100 text-emerald-600"><x-icon name="check-circle" class="size-9" /></div>
            <h3 class="mt-4 font-display text-xl font-semibold text-slate-900">¡Mensaje enviado!</h3>
            <p class="mt-2 text-slate-600">Gracias por escribirnos. Te responderemos a la brevedad.</p>
            <button type="button" wire:click="$set('sent', false)" class="btn-secondary mt-6">Enviar otro mensaje</button>
        </div>
    @else
        <form wire:submit="send" class="grid gap-5 sm:grid-cols-2">
            <x-field label="Nombre y apellido" for="c-name" error="name" required>
                <input id="c-name" type="text" wire:model="name" class="form-input" autocomplete="name">
            </x-field>
            <x-field label="Correo electrónico" for="c-email" error="email" required>
                <input id="c-email" type="email" wire:model="email" class="form-input" autocomplete="email">
            </x-field>
            <x-field label="Teléfono" for="c-phone" error="phone">
                <input id="c-phone" type="tel" wire:model="phone" class="form-input" autocomplete="tel">
            </x-field>
            <x-field label="Asunto" for="c-subject" error="subject">
                <input id="c-subject" type="text" wire:model="subject" class="form-input">
            </x-field>
            <x-field label="Mensaje" for="c-message" error="message" required class="sm:col-span-2">
                <textarea id="c-message" wire:model="message" rows="5" class="form-input"></textarea>
            </x-field>
            <div class="hidden" aria-hidden="true">
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary w-full px-6 py-3 sm:w-auto" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="send">Enviar mensaje</span>
                    <span wire:loading wire:target="send">Enviando…</span>
                </button>
            </div>
        </form>
    @endif
</div>
