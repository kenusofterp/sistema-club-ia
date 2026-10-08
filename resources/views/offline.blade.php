<x-layouts::auth title="Sin conexión">
    <div class="text-center">
        <div class="mx-auto grid size-16 place-items-center rounded-full bg-slate-100 text-slate-500"><x-icon name="globe" class="size-9" /></div>
        <h1 class="mt-6 font-display text-2xl font-bold text-slate-900">Sin conexión</h1>
        <p class="mt-2 text-slate-600">No hay conexión a internet. Cuando vuelvas a estar en línea, la página se actualizará.</p>
        <button type="button" onclick="location.reload()" class="btn-primary mt-6">Reintentar</button>
    </div>
</x-layouts::auth>
