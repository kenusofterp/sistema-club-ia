<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['title' => 'Recibo '.$payment->receipt_number])
    <style>@media print { .no-print { display: none !important; } body { background: white !important; } .sheet { box-shadow: none !important; border: 0 !important; } }</style>
</head>
<body class="bg-slate-100 py-8 font-sans text-slate-800 antialiased">
<div class="no-print mx-auto mb-4 flex max-w-3xl justify-end gap-2 px-4">
    <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / Guardar PDF</button>
</div>
<div class="sheet mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-10 shadow-sm">
    <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-6">
        <div>
            <x-logo />
            <p class="mt-3 text-xs text-slate-500">{{ setting('contact.address') }}<br>{{ setting('contact.phone') }} · {{ setting('contact.email') }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs tracking-wider text-slate-500 uppercase">Recibo de pago</p>
            <p class="font-display text-2xl font-bold text-slate-900">{{ $payment->receipt_number }}</p>
            <p class="text-sm text-slate-500">{{ $payment->payment_date->format('d/m/Y') }}</p>
            @if ($payment->isCancelled())
                <p class="mt-2 inline-block rounded border-2 border-red-600 px-3 py-1 text-sm font-bold text-red-600">ANULADO</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 py-6 text-sm">
        <div>
            <p class="text-xs text-slate-500">Recibimos de</p>
            <p class="font-semibold text-slate-900">{{ $payment->member->fullName() }}</p>
            <p class="text-slate-600">Socio N° {{ $payment->member->member_number }} · {{ $payment->member->category->name }}</p>
            <p class="text-slate-600">{{ $payment->member->document_type }} {{ $payment->member->document_number }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Medio de pago</p>
            <p class="font-semibold text-slate-900">{{ $payment->method->label() }}</p>
            @if ($payment->reference)<p class="text-slate-600">Ref.: {{ $payment->reference }}</p>@endif
        </div>
    </div>

    <table class="w-full text-sm">
        <thead><tr class="border-b border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase"><th class="py-2">Concepto</th><th class="py-2 text-right">Importe</th></tr></thead>
        <tbody>
            @foreach ($payment->fees as $fee)
                <tr class="border-b border-slate-100"><td class="py-2">{{ $fee->concept }}</td><td class="py-2 text-right tabular-nums">{{ money($fee->pivot->amount) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td class="pt-4 text-right font-semibold">Total</td><td class="pt-4 text-right font-display text-xl font-bold tabular-nums">{{ money($payment->amount) }}</td></tr>
        </tfoot>
    </table>

    @if ($payment->notes)
        <p class="mt-6 text-sm text-slate-600">Observaciones: {{ $payment->notes }}</p>
    @endif

    <div class="mt-10 flex items-end justify-between border-t border-slate-200 pt-6 text-xs text-slate-500">
        <p>Recibió: {{ $payment->receiver?->name ?? '—' }}<br>Emitido el {{ $payment->created_at->format('d/m/Y H:i') }}</p>
        <p class="text-right">Documento no válido como factura.</p>
    </div>
</div>
</body>
</html>
