<div>
    <x-page-header title="Auditoría" subtitle="Registro de todas las operaciones realizadas en el sistema" />

    <div class="card">
        <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar descripción…" class="form-input lg:col-span-2">
            <select wire:model.live="log" class="form-input">
                <option value="">Todos los módulos</option>
                @foreach ($logs as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="event" class="form-input">
                <option value="">Todos los eventos</option>
                @foreach ($events as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="user" class="form-input">
                <option value="">Todos los usuarios</option>
                @foreach ($users as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input type="date" wire:model.live="from" class="form-input" title="Desde">
                <input type="date" wire:model.live="to" class="form-input" title="Hasta">
            </div>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($audits as $audit)
                @include('livewire.admin.audit.partials.entry', ['audit' => $audit])
            @empty
                <x-empty-state icon="shield" title="Sin registros" />
            @endforelse
        </div>
        @if ($audits->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $audits->links() }}</div>
        @endif
    </div>
</div>
