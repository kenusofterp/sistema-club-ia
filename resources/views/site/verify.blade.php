<x-layouts::auth title="Verificación de carnet">
    @php($valid = $member->isActive())
    <div class="text-center">
        <div class="mx-auto grid size-20 place-items-center rounded-full {{ $valid ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' }}">
            <x-icon :name="$valid ? 'check-circle' : 'x-circle'" class="size-12" />
        </div>
        <h1 class="mt-6 font-display text-2xl font-bold text-slate-900">{{ $valid ? 'Carnet válido' : 'Carnet no válido' }}</h1>
        <p class="mt-2 text-sm text-slate-500">Verificado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="card mt-8 p-6">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Socio</dt><dd class="font-semibold text-slate-900">{{ $member->fullName() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">N° de socio</dt><dd class="font-medium">{{ $member->member_number ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Categoría</dt><dd class="font-medium">{{ $member->category->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Estado</dt><dd><x-badge :status="$member->status" /></dd></div>
        </dl>
    </div>
    <a href="{{ route('home') }}" class="btn-secondary mt-6 w-full">Ir al sitio del club</a>
</x-layouts::auth>
