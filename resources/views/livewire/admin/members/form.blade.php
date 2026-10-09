<div>
    <x-page-header :title="$member ? 'Editar socio' : 'Nuevo socio'" :subtitle="$member?->fullName()">
        <x-slot:breadcrumb><a href="{{ route('admin.members.index') }}" wire:navigate class="hover:text-brand-700">Socios</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <div class="card p-6">
            <h2 class="mb-5 font-semibold text-slate-900">Datos personales</h2>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <x-field label="Nombre" for="first_name" error="first_name" required>
                    <input id="first_name" wire:model="first_name" class="form-input">
                </x-field>
                <x-field label="Apellido" for="last_name" error="last_name" required>
                    <input id="last_name" wire:model="last_name" class="form-input">
                </x-field>
                <div class="grid grid-cols-3 gap-3">
                    <x-field label="Tipo" for="document_type" error="document_type">
                        <select id="document_type" wire:model.live="document_type" class="form-input">
                            @foreach (\App\Models\Member::DOCUMENT_TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Documento" for="document_number" error="document_number" required class="col-span-2">
                        <input id="document_number" wire:model.blur="document_number" class="form-input">
                    </x-field>
                </div>
                @if ($existingPerson)
                    <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800 sm:col-span-2">{{ $existingPerson }} ya está registrada en el sistema: se completaron sus datos personales. Los cambios que hagas se actualizan en todas sus entidades.</p>
                @endif
                <x-field label="Fecha de nacimiento" for="birth_date" error="birth_date" required>
                    <input id="birth_date" type="date" wire:model="birth_date" class="form-input">
                </x-field>
                <x-field label="Género" for="gender" error="gender">
                    <select id="gender" wire:model="gender" class="form-input">
                        <option value="">Sin especificar</option>
                        @foreach (\App\Models\Member::GENDERS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Categoría" for="member_category_id" error="member_category_id" required>
                    <div class="flex gap-2">
                        <select id="member_category_id" wire:model="member_category_id" class="form-input min-w-0 flex-1">
                            <option value="">Seleccionar…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} — {{ money($category->monthly_fee) }} ({{ $category->ageRangeLabel() }})</option>
                            @endforeach
                        </select>
                        @can('categorias.gestionar')
                            <button type="button" wire:click="openCategoryModal" class="btn-secondary shrink-0 px-3" title="Nueva categoría" aria-label="Nueva categoría"><x-icon name="plus" class="size-4" /></button>
                        @endcan
                    </div>
                </x-field>
                <x-field label="Fecha de ingreso" for="admission_date" error="admission_date">
                    <input id="admission_date" type="date" wire:model="admission_date" class="form-input">
                </x-field>
                <div class="md:col-span-2">
                    <x-image-upload model="photo" :file="$photo" :current="$member?->photoUrl()" label="Foto (para el carnet)" aspect="aspect-square" help="JPG o PNG, máximo 3 MB." />
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="mb-5 font-semibold text-slate-900">Contacto</h2>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <x-field label="Correo electrónico" for="email" error="email" help="Necesario para el acceso al portal y recibos.">
                    <input id="email" type="email" wire:model="email" class="form-input">
                </x-field>
                <x-field label="Teléfono" for="phone" error="phone">
                    <input id="phone" wire:model="phone" class="form-input">
                </x-field>
                <x-field label="Dirección" for="address" error="address">
                    <input id="address" wire:model="address" class="form-input">
                </x-field>
                <x-field label="Localidad" for="city" error="city">
                    <input id="city" wire:model="city" class="form-input">
                </x-field>
                <x-field label="Contacto de emergencia" for="emergency_contact_name" error="emergency_contact_name">
                    <input id="emergency_contact_name" wire:model="emergency_contact_name" class="form-input">
                </x-field>
                <x-field label="Teléfono de emergencia" for="emergency_contact_phone" error="emergency_contact_phone">
                    <input id="emergency_contact_phone" wire:model="emergency_contact_phone" class="form-input">
                </x-field>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="mb-1 font-semibold text-slate-900">Grupo familiar</h2>
            <p class="mb-5 text-sm text-slate-500">Si es integrante del grupo familiar de otro socio, indicá el titular.</p>
            <div class="grid gap-5 md:grid-cols-2">
                <x-field label="Socio titular" for="holder_search" error="holder_id">
                    <div class="relative">
                        <input id="holder_search" wire:model.live.debounce.300ms="holder_search" class="form-input" placeholder="Buscar titular…" @disabled($holder_id) autocomplete="off">
                        @if ($holder_id)
                            <button type="button" wire:click="clearHolder" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-red-600"><x-icon name="x" class="size-4" /></button>
                        @endif
                        @if ($holders->isNotEmpty())
                            <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                @foreach ($holders as $holder)
                                    <li><button type="button" wire:click="selectHolder({{ $holder->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $holder->sortableName() }} <span class="text-slate-400">· N° {{ $holder->member_number }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </x-field>
                <x-field label="Parentesco" for="relationship" error="relationship">
                    <input id="relationship" wire:model="relationship" class="form-input" placeholder="Cónyuge, hijo/a…" @disabled(! $holder_id)>
                </x-field>
            </div>
        </div>

        @if ($medicalFields->isNotEmpty())
            <div class="card p-6">
                <h2 class="mb-1 font-semibold text-slate-900">Ficha médica</h2>
                <p class="mb-5 text-sm text-slate-500">{{ $member?->medicalRecord ? 'Datos médicos del socio.' : 'Podés dejarla vacía y completarla más tarde desde la ficha del socio.' }}</p>
                <x-medical-fields :fields="$medicalFields" model="medical" />
            </div>
        @endif

        <div class="card p-6">
            <h2 class="mb-5 font-semibold text-slate-900">Observaciones</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <x-field label="Información médica relevante" for="medical_notes" error="medical_notes" help="Alergias, condiciones, apto físico. Solo visible para el personal.">
                    <textarea id="medical_notes" wire:model="medical_notes" rows="3" class="form-input"></textarea>
                </x-field>
                <x-field label="Notas internas" for="notes" error="notes">
                    <textarea id="notes" wire:model="notes" rows="3" class="form-input"></textarea>
                </x-field>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $member ? route('admin.members.show', $member) : route('admin.members.index') }}" wire:navigate class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <x-icon name="check" class="size-4" /> {{ $member ? 'Guardar cambios' : 'Registrar socio' }}
            </button>
        </div>
    </form>

    @can('categorias.gestionar')
        <x-modal wire:model="showCategoryModal" title="Nueva categoría" max-width="max-w-lg">
            <form wire:submit="saveCategory" id="quick-category-form" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nombre" for="new_category_name" error="newCategory.name" required class="sm:col-span-2">
                    <input id="new_category_name" wire:model="newCategory.name" class="form-input">
                </x-field>
                <x-field label="Descripción" for="new_category_description" error="newCategory.description" class="sm:col-span-2">
                    <input id="new_category_description" wire:model="newCategory.description" class="form-input">
                </x-field>
                <x-field label="Cuota mensual" for="new_category_monthly_fee" error="newCategory.monthly_fee" required>
                    <input id="new_category_monthly_fee" type="number" step="0.01" min="0" wire:model="newCategory.monthly_fee" class="form-input">
                </x-field>
                <x-field label="Derecho de ingreso" for="new_category_admission_fee" error="newCategory.admission_fee" required>
                    <input id="new_category_admission_fee" type="number" step="0.01" min="0" wire:model="newCategory.admission_fee" class="form-input">
                </x-field>
                <x-field label="Edad mínima" for="new_category_min_age" error="newCategory.min_age">
                    <input id="new_category_min_age" type="number" min="0" wire:model="newCategory.min_age" class="form-input">
                </x-field>
                <x-field label="Edad máxima" for="new_category_max_age" error="newCategory.max_age">
                    <input id="new_category_max_age" type="number" min="0" wire:model="newCategory.max_age" class="form-input">
                </x-field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
                <button type="submit" form="quick-category-form" class="btn-primary" wire:loading.attr="disabled" wire:target="saveCategory">Crear y seleccionar</button>
            </x-slot:footer>
        </x-modal>
    @endcan
</div>
