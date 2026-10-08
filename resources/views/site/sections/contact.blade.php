<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div class="grid gap-10 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-2">
                @foreach ([
                    ['map-pin', 'Dirección', setting('contact.address')],
                    ['phone', 'Teléfono', setting('contact.phone')],
                    ['mail', 'Correo', setting('contact.email')],
                    ['clock', 'Horarios', setting('contact.hours')],
                ] as [$icon, $label, $value])
                    @if ($value)
                        <div class="flex gap-4">
                            <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5" /></div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $label }}</p>
                                <p class="text-sm whitespace-pre-line text-slate-600">{{ $value }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
                @if (setting('contact.map_embed_url'))
                    <iframe src="{{ setting('contact.map_embed_url') }}" class="h-64 w-full rounded-2xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa"></iframe>
                @endif
            </div>
            <div class="lg:col-span-3">
                <livewire:site.contact-form />
            </div>
        </div>
    </div>
</section>
