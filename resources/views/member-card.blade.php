@php
    $clubName = setting('site.name', config('app.name'));
    $logoLight = \App\Models\Setting::imageUrl('site.logo_light');
    $logo = $logoLight ?? \App\Models\Setting::imageUrl('site.logo');
    $short = setting('site.short_name') ?: collect(explode(' ', $clubName))->map(fn ($w) => mb_substr($w, 0, 1))->take(3)->implode('');
    $photo = $member->photoUrl();
    $web = preg_replace('#^https?://#', '', rtrim(url('/'), '/'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['title' => 'Carnet de '.$member->fullName()])
    {{-- Estilos propios (no dependen de compilar los assets): tamaño real de tarjeta, 85,6 × 54 mm. --}}
    <style>
        :root { --dark: color-mix(in srgb, var(--brand) 62%, #000); --soft: color-mix(in srgb, var(--brand) 12%, #fff); }
        body { margin: 0; background: #e2e8f0; font-family: inherit; color: #0f172a; }
        .toolbar { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; align-items: center; padding: 1.25rem 1rem .5rem; }
        .toolbar p { width: 100%; margin: 0 0 .25rem; text-align: center; font-size: .85rem; color: #475569; }
        .sheet { display: flex; justify-content: center; padding: 1.5rem 1rem 3rem; }
        .pair { display: flex; outline: 1px dashed #94a3b8; outline-offset: 0; }
        .card { position: relative; width: 85.6mm; height: 54mm; overflow: hidden; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card + .card { border-left: 1px dotted #94a3b8; }
        @media screen { .pair { box-shadow: 0 20px 40px -12px rgba(15, 23, 42, .35); border-radius: 3.2mm; overflow: hidden; outline: 0; } }

        /* ---- Frente ---- */
        .front { color: #fff; background: radial-gradient(120% 140% at 100% 0%, var(--brand) 0%, var(--dark) 70%); }
        .front .deco { position: absolute; border-radius: 9999px; background: var(--accent); opacity: .18; }
        .front .deco.a { width: 46mm; height: 46mm; right: -16mm; bottom: -22mm; }
        .front .deco.b { width: 20mm; height: 20mm; right: 18mm; top: -9mm; opacity: .12; }
        .front .stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 1.6mm; background: var(--accent); }
        .head { position: absolute; top: 3.6mm; left: 4mm; right: 4mm; display: flex; align-items: center; gap: 2.2mm; }
        .logo { width: 8.5mm; height: 8.5mm; flex: none; display: grid; place-items: center; border-radius: 2mm; overflow: hidden; }
        .logo.tile { background: #fff; padding: .6mm; box-sizing: border-box; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .logo.initials { background: rgba(255,255,255,.18); font-weight: 800; font-size: 3mm; letter-spacing: .02em; }
        .club { flex: 1; min-width: 0; font-weight: 800; font-size: 3.1mm; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tag { flex: none; font-size: 1.9mm; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; padding: .7mm 1.8mm; border-radius: 9999px; background: rgba(255,255,255,.16); }
        .body { position: absolute; top: 15mm; left: 4mm; right: 4mm; display: flex; gap: 3.4mm; }
        .photo { width: 21mm; height: 26mm; flex: none; border-radius: 2.4mm; overflow: hidden; background: rgba(255,255,255,.14); border: .5mm solid rgba(255,255,255,.85); display: grid; place-items: center; font-weight: 800; font-size: 7mm; }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .data { min-width: 0; flex: 1; }
        .name { font-weight: 800; font-size: 4mm; line-height: 1.08; margin: .3mm 0 1.6mm; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .row { font-size: 2.35mm; line-height: 1.45; color: rgba(255,255,255,.82); }
        .row b { color: #fff; font-weight: 600; }
        .number { position: absolute; left: 28.4mm; bottom: 4.2mm; }
        .label { font-size: 1.75mm; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.62); }
        .number .value { font-weight: 800; font-size: 5.2mm; letter-spacing: .06em; line-height: 1; margin-top: .4mm; }
        .since { position: absolute; right: 4mm; bottom: 4.2mm; text-align: right; }
        .since .value { font-weight: 700; font-size: 2.8mm; margin-top: .4mm; }

        /* ---- Dorso ---- */
        .back { background: #fff; }
        .back .band { position: absolute; left: 0; right: 0; top: 0; height: 7.5mm; background: var(--dark); color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 4mm; box-sizing: border-box; }
        .back .band span { font-size: 2.1mm; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .back .band small { font-size: 2mm; opacity: .8; }
        .qr { position: absolute; left: 4mm; top: 10.5mm; width: 33mm; height: 33mm; padding: 1.4mm; box-sizing: border-box; border-radius: 2.4mm; background: var(--soft); }
        .qr svg { width: 100%; height: 100%; display: block; color: #0f172a; }
        .info { position: absolute; left: 41mm; right: 4mm; top: 10.5mm; }
        .info h3 { margin: 0 0 .8mm; font-size: 2.9mm; font-weight: 800; color: var(--dark); }
        .info p { margin: 0 0 1.6mm; font-size: 2.15mm; line-height: 1.4; color: #475569; }
        .info .contact { font-size: 2.05mm; line-height: 1.5; color: #334155; }
        .sign { position: absolute; left: 41mm; right: 4mm; bottom: 5mm; border-top: .25mm solid #cbd5e1; padding-top: .8mm; font-size: 1.8mm; color: #94a3b8; text-align: center; letter-spacing: .08em; text-transform: uppercase; }
        .back .stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 1.6mm; background: var(--accent); }

        @page { size: A4; margin: 15mm; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { padding: 0; justify-content: flex-start; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <p>Frente y dorso juntos: imprimí, recortá por la línea punteada exterior y doblá por la del medio (ideal para plastificar). En la impresión activá «Gráficos de fondo».</p>
    <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / Guardar PDF</button>
</div>

<div class="sheet">
    <div class="pair">
        {{-- Frente --}}
        <div class="card front">
            <span class="deco a"></span><span class="deco b"></span>
            <div class="head">
                @if ($logo)
                    <span @class(['logo', 'tile' => ! $logoLight])><img src="{{ $logo }}" alt=""></span>
                @else
                    <span class="logo initials">{{ mb_strtoupper(mb_substr($short, 0, 3)) }}</span>
                @endif
                <span class="club">{{ $clubName }}</span>
                <span class="tag">Socio</span>
            </div>
            <div class="body">
                <div class="photo">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="">
                    @else
                        {{ $member->initials() }}
                    @endif
                </div>
                <div class="data">
                    <p class="name">{{ $member->first_name }} {{ $member->last_name }}</p>
                    <p class="row" style="margin:0">{{ $member->document_type }} <b>{{ $member->document_number }}</b></p>
                    <p class="row" style="margin:0">Categoría <b>{{ $member->category?->name }}</b></p>
                    @if ($member->birth_date)
                        <p class="row" style="margin:0">Nacimiento <b>{{ $member->birth_date->format('d/m/Y') }}</b></p>
                    @endif
                </div>
            </div>
            <div class="number">
                <div class="label">N° de socio</div>
                <div class="value">{{ $member->member_number ?? '—' }}</div>
            </div>
            <div class="since">
                <div class="label">Socio desde</div>
                <div class="value">{{ $member->admission_date?->format('m/Y') ?? '—' }}</div>
            </div>
            <span class="stripe"></span>
        </div>

        {{-- Dorso --}}
        <div class="card back">
            <div class="band"><span>Carnet de socio</span><small>N° {{ $member->member_number ?? '—' }}</small></div>
            <div class="qr">{!! $qr !!}</div>
            <div class="info">
                <h3>Presentá este carnet</h3>
                <p>Escaneá el código para verificar que el carnet es válido y que el socio está al día.</p>
                <div class="contact">
                    @if (setting('contact.address'))<div>{{ setting('contact.address') }}</div>@endif
                    @if (setting('contact.phone'))<div>Tel. {{ setting('contact.phone') }}</div>@endif
                    @if ($web)<div>{{ $web }}</div>@endif
                </div>
            </div>
            <div class="sign">Firma del socio</div>
            <span class="stripe"></span>
        </div>
    </div>
</div>
</body>
</html>
