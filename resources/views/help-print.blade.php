@php
    $groups = \App\Support\Manual::groups();
    $clubName = setting('site.name', config('app.name'));
    $logo = \App\Models\Setting::imageUrl('site.logo');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['title' => 'Manual de uso'])
    @include('help.styles')
    {{-- Versión para imprimir / guardar en PDF: portada, índice y contenido, sin el menú del panel. --}}
    <style>
        body { margin: 0; background: #e2e8f0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: center; padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .toolbar p { margin: 0; font-size: .85rem; color: #475569; }
        .page { max-width: 190mm; margin: 1.5rem auto; padding: 18mm 16mm; background: #fff; box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .25); box-sizing: border-box; }
        .cover { display: flex; flex-direction: column; justify-content: space-between; min-height: 245mm; }
        .cover .top { display: flex; align-items: center; gap: 1rem; }
        .cover .top img { width: 18mm; height: 18mm; object-fit: contain; }
        .cover .club { font-weight: 800; font-size: 1.3rem; color: #0f172a; }
        .cover .title { margin-top: 45mm; }
        .cover .title small { display: block; font-size: .85rem; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: var(--brand); }
        .cover .title h1 { margin: .4rem 0 .6rem; font-size: 2.8rem; line-height: 1.05; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
        .cover .title p { margin: 0; max-width: 120mm; font-size: 1.05rem; color: #475569; }
        .cover .bar { height: 6px; border-radius: 3px; background: linear-gradient(90deg, var(--brand), var(--accent)); }
        .cover .foot { font-size: .85rem; color: #64748b; }
        .toc h2 { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 800; color: #0f172a; }
        .toc .group { margin: 0 0 .9rem; break-inside: avoid; }
        .toc .group p { margin: 0 0 .25rem; font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
        .toc ol { margin: 0; padding: 0; list-style: none; columns: 2; column-gap: 10mm; }
        .toc li { padding: .18rem 0; border-bottom: 1px dotted #cbd5e1; font-size: .92rem; break-inside: avoid; }
        .toc a { color: #0f172a; text-decoration: none; }
        @page { size: A4; margin: 16mm 15mm 18mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { max-width: none; margin: 0; padding: 0; box-shadow: none; }
            .cover { min-height: 255mm; break-after: page; }
            .toc { break-after: page; }
            .cover, .cover *, .toc * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .manual .guide-cards { display: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <p>Para que salgan los colores, en la ventana de impresión activá <strong>Gráficos de fondo</strong>. Elegí «Guardar como PDF» para tenerlo en un archivo.</p>
    <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / Guardar PDF</button>
</div>

<div class="page cover">
    <div class="top">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <span class="club">{{ $clubName }}</span>
    </div>
    <div class="title">
        <small>Sistema de gestión</small>
        <h1>Manual de uso</h1>
        <p>Guía paso a paso: socios, cuotas y cobros, gastos, {{ mb_strtolower(activity_label(true)) }}, agenda de clases, torneos, control de acceso y portal del socio.</p>
    </div>
    <div>
        <div class="bar"></div>
        <p class="foot">Actualizado al {{ now()->translatedFormat('j \d\e F \d\e Y') }}</p>
    </div>
</div>

<div class="page toc">
    <h2>Contenido</h2>
    @foreach ($groups as $group)
        <div class="group">
            <p>{{ $group['title'] }}</p>
            <ol>
                @foreach ($group['items'] as $id => $label)
                    <li><a href="#{{ $id }}">{{ $label }}</a></li>
                @endforeach
            </ol>
        </div>
    @endforeach
</div>

<div class="page">
    <article class="manual">
        @include('help.content')
    </article>
</div>
</body>
</html>
