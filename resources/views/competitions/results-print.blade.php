<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['title' => 'Resultados '.$competition->name])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .card { box-shadow: none !important; }
            .category { break-inside: avoid-page; }
        }
    </style>
</head>
<body class="bg-slate-100 py-8 font-sans text-slate-800 antialiased">
<div class="no-print mx-auto mb-4 flex max-w-6xl justify-end gap-2 px-4">
    <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / Guardar PDF</button>
</div>
<div class="mx-auto max-w-6xl space-y-8 px-4">
    <div class="flex items-start justify-between gap-6 border-b border-slate-300 pb-4">
        <x-logo />
        <div class="text-right">
            <p class="text-xs tracking-wider text-slate-500 uppercase">Resultados</p>
            <p class="font-display text-2xl font-bold text-slate-900">{{ $competition->name }}</p>
            <p class="text-sm text-slate-500">{{ $competition->date->format('d/m/Y') }}{{ $competition->location ? ' · '.$competition->location : '' }}</p>
        </div>
    </div>

    @foreach ($tables as $table)
        <section class="category">
            <h2 class="mb-2 font-display text-lg font-bold text-slate-900">{{ $table['label'] }}</h2>
            @include('competitions.partials.results-table', ['rows' => $table['rows'], 'exercises' => $competition->exercises])
        </section>
    @endforeach

    <p class="text-xs text-slate-500">Puntajes con 3 decimales. Las participantes con igual puntaje comparten el puesto. Emitido el {{ now()->format('d/m/Y H:i') }}.</p>
</div>
</body>
</html>
