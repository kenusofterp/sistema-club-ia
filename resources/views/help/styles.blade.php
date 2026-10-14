{{-- Estilos del manual (pantalla e impresión). Van acá para no depender de compilar los assets. --}}
<style>
    .manual { color: #334155; font-size: 15px; line-height: 1.7; }
    .manual section { scroll-margin-top: 5.5rem; }
    .manual section + section { margin-top: 2.75rem; padding-top: 2.25rem; border-top: 1px solid #e2e8f0; }
    .manual h2 { display: flex; align-items: center; gap: .65rem; margin: 0 0 .9rem; font-size: 1.4rem; line-height: 1.25; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
    .manual h2 .num { flex: none; display: inline-grid; place-items: center; min-width: 2.1rem; height: 2.1rem; padding: 0 .45rem; border-radius: .7rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); font-size: .95rem; font-weight: 800; }
    .manual h3 { margin: 1.6rem 0 .5rem; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .manual p { margin: 0 0 .85rem; }
    .manual .lead { font-size: 1.02rem; color: #475569; }
    .manual a:not([class]) { color: var(--brand); font-weight: 600; text-decoration: underline; text-decoration-color: color-mix(in srgb, var(--brand) 35%, #fff); text-underline-offset: 2px; }
    .manual a:not([class]):hover { text-decoration-color: var(--brand); }
    .manual ul { margin: 0 0 .9rem; padding-left: 1.35rem; list-style: disc; }
    .manual ul > li { margin: .3rem 0; }
    .manual ul > li::marker { color: color-mix(in srgb, var(--brand) 55%, #94a3b8); }
    .manual strong { color: #0f172a; }
    .manual code { padding: .1rem .4rem; border-radius: .35rem; background: #f1f5f9; color: #0f172a; font-size: .85em; }
    .manual kbd { padding: .05rem .4rem; border: 1px solid #cbd5e1; border-bottom-width: 2px; border-radius: .35rem; background: #fff; font-size: .82em; font-family: inherit; }
    .manual ol.steps { margin: 0 0 1rem; padding: 0; list-style: none; counter-reset: step; }
    .manual ol.steps > li { position: relative; margin: 0 0 .7rem; padding-left: 2.6rem; min-height: 1.75rem; counter-increment: step; }
    .manual ol.steps > li::before { content: counter(step); position: absolute; left: 0; top: .05rem; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 9999px; background: var(--brand); color: #fff; font-size: .8rem; font-weight: 700; }
    .manual ol.steps > li:not(:last-child)::after { content: ''; position: absolute; left: .85rem; top: 1.95rem; bottom: -.6rem; width: 2px; background: color-mix(in srgb, var(--brand) 18%, #fff); }
    .manual .glossary { margin: 0; }
    .manual .glossary dt { margin-top: .9rem; font-weight: 700; color: #0f172a; }
    .manual .glossary dd { margin: .15rem 0 0; color: #475569; }
    .manual .faq dt { margin-top: 1rem; padding-left: 1.6rem; position: relative; }
    .manual .faq dt::before { content: '?'; position: absolute; left: 0; top: .1rem; display: grid; place-items: center; width: 1.15rem; height: 1.15rem; border-radius: 9999px; background: var(--brand); color: #fff; font-size: .7rem; }
    .manual .faq dd { padding-left: 1.6rem; }
    .manual-table { width: 100%; margin: .75rem 0 1rem; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: .6rem; overflow: hidden; font-size: .9rem; }
    .manual-table th { background: #f8fafc; padding: .55rem .8rem; text-align: left; font-weight: 700; color: #334155; border-bottom: 1px solid #e2e8f0; }
    .manual-table td { padding: .55rem .8rem; vertical-align: top; border-top: 1px solid #f1f5f9; }
    .manual figure { margin: 1.25rem 0; }
    .manual figure img { display: block; }

    /* Guías de inicio (tarjetas) */
    .manual .guide-cards { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); margin: 1rem 0 0; }
    .manual .guide-card { display: block; padding: 1rem 1.1rem; border-radius: 1rem; border: 1px solid #e2e8f0; background: #fff; text-decoration: none; color: inherit; transition: box-shadow .15s, border-color .15s; }
    .manual .guide-card:hover { border-color: color-mix(in srgb, var(--brand) 45%, #fff); box-shadow: 0 8px 24px -12px rgba(15, 23, 42, .25); }
    .manual .guide-card b { display: block; margin-bottom: .2rem; color: #0f172a; font-size: 1rem; }
    .manual .guide-card span { font-size: .88rem; color: #64748b; line-height: 1.5; }
    .manual .guide-card i { font-style: normal; display: inline-grid; place-items: center; width: 2.2rem; height: 2.2rem; margin-bottom: .55rem; border-radius: .7rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); font-size: 1.1rem; }

    /* Recuadro de "lo que vas a necesitar" / resumen */
    .manual .summary { margin: .5rem 0 1.25rem; padding: 1rem 1.15rem; border-radius: .9rem; background: #f8fafc; border: 1px solid #e2e8f0; }
    .manual .summary p { margin: 0 0 .35rem; font-weight: 700; color: #0f172a; font-size: .92rem; }
    .manual .summary ul { margin: 0; font-size: .92rem; }
    .manual .badge-new { display: inline-block; margin-left: .35rem; padding: .05rem .45rem; border-radius: 9999px; background: color-mix(in srgb, var(--accent) 22%, #fff); color: #92400e; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; vertical-align: middle; }

    /* ---- Impresión / PDF ---- */
    @media print {
        .manual { font-size: 10.5pt; line-height: 1.55; color: #1e293b; }
        .manual section + section { margin-top: 0; padding-top: 0; border-top: 0; }
        .manual section.chapter { break-before: page; }
        .manual h2 { font-size: 16pt; break-after: avoid; }
        .manual h3 { font-size: 12pt; break-after: avoid; }
        .manual ol.steps > li, .manual .glossary dt, .manual .glossary dd, .manual-table tr { break-inside: avoid; }
        .manual figure, .manual .summary, .manual .tip-box { break-inside: avoid; }
        .manual figure img { max-height: 95mm; width: auto !important; max-width: 100%; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 6px; }
        .manual figure button { border: 0 !important; box-shadow: none !important; background: none !important; }
        .manual figcaption { font-size: 8.5pt; }
        .manual a:not([class]) { color: inherit; text-decoration: none; }
        .manual .guide-card { break-inside: avoid; border-color: #cbd5e1; }
        .manual *, .manual *::before { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
