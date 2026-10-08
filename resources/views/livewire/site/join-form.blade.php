<div>
    @include('site.partials.page-hero', ['kicker' => setting('site.name'), 'heading' => 'Asociate al club', 'lead' => 'Completá tus datos. La secretaría revisará la solicitud y te enviará por correo el acceso al portal de socios.'])

    <section class="py-16">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <aside class="space-y-4 lg:order-last">
                <h2 class="font-display text-lg font-semibold text-slate-900">Categorías</h2>
                @foreach ($categories as $category)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-900">{{ $category->name }}</p>
                            <p class="font-display font-bold text-brand-700">{{ (float) $category->monthly_fee > 0 ? money($category->monthly_fee) : 'Sin cargo' }}</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $category->description }} · {{ $category->ageRangeLabel() }}</p>
                        @if ((float) $category->admission_fee > 0)
                            <p class="mt-1 text-xs text-slate-500">Derecho de ingreso: {{ money($category->admission_fee) }}</p>
                        @endif
                    </div>
                @endforeach
            </aside>

            <div class="lg:col-span-2">
                @if ($sent)
                    <div class="card p-10 text-center">
                        <div class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-100 text-emerald-600"><x-icon name="check-circle" class="size-9" /></div>
                        <h3 class="mt-4 font-display text-2xl font-semibold text-slate-900">¡Recibimos tu solicitud!</h3>
                        <p class="mt-2 text-slate-600">La secretaría del club la revisará y te contactaremos por correo electrónico.</p>
                        <a href="{{ route('home') }}" class="btn-primary mt-6">Volver al inicio</a>
                    </div>
                @else
                    <form wire:submit="submit" class="card grid gap-5 p-6 sm:grid-cols-2 sm:p-8">
                        <x-field label="Nombre" for="j-first" error="first_name" required>
                            <input id="j-first" wire:model="first_name" class="form-input" autocomplete="given-name">
                        </x-field>
                        <x-field label="Apellido" for="j-last" error="last_name" required>
                            <input id="j-last" wire:model="last_name" class="form-input" autocomplete="family-name">
                        </x-field>
                        <div class="grid grid-cols-3 gap-3">
                            <x-field label="Tipo" for="j-dt" error="document_type" required>
                                <select id="j-dt" wire:model="document_type" class="form-input">
                                    @foreach (\App\Models\Member::DOCUMENT_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field label="Documento" for="j-doc" error="document_number" required class="col-span-2">
                                <input id="j-doc" wire:model="document_number" class="form-input">
                            </x-field>
                        </div>
                        <x-field label="Fecha de nacimiento" for="j-birth" error="birth_date" required>
                            <input id="j-birth" type="date" wire:model="birth_date" class="form-input" max="{{ now()->toDateString() }}">
                        </x-field>
                        <x-field label="Correo electrónico" for="j-email" error="email" required>
                            <input id="j-email" type="email" wire:model="email" class="form-input" autocomplete="email">
                        </x-field>
                        <x-field label="Teléfono" for="j-phone" error="phone" required>
                            <input id="j-phone" type="tel" wire:model="phone" class="form-input" autocomplete="tel">
                        </x-field>
                        <x-field label="Dirección" for="j-address" error="address">
                            <input id="j-address" wire:model="address" class="form-input" autocomplete="street-address">
                        </x-field>
                        <x-field label="Localidad" for="j-city" error="city">
                            <input id="j-city" wire:model="city" class="form-input">
                        </x-field>
                        <x-field label="Género" for="j-gender" error="gender">
                            <select id="j-gender" wire:model="gender" class="form-input">
                                <option value="">Prefiero no decirlo</option>
                                @foreach (\App\Models\Member::GENDERS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Categoría" for="j-cat" error="member_category_id" required>
                            <select id="j-cat" wire:model="member_category_id" class="form-input">
                                <option value="">Seleccioná…</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->ageRangeLabel() }})</option>
                                @endforeach
                            </select>
                        </x-field>
                        <div class="hidden" aria-hidden="true"><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></div>
                        <div class="sm:col-span-2">
                            <label class="flex items-start gap-3 text-sm text-slate-600">
                                <input type="checkbox" wire:model="accept" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span>Declaro que los datos son correctos y acepto el
                                    <a href="{{ route('site.page', 'estatuto') }}" target="_blank" class="font-medium text-brand-700 underline">estatuto</a> y el
                                    <a href="{{ route('site.page', 'reglamento') }}" target="_blank" class="font-medium text-brand-700 underline">reglamento</a> del club.</span>
                            </label>
                            @error('accept') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn-primary w-full px-6 py-3 text-base sm:w-auto" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">Enviar solicitud</span>
                                <span wire:loading wire:target="submit">Enviando…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>
</div>
