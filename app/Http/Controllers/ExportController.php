<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportaciones CSV (compatibles con Excel: UTF-8 con BOM y separador ";"). */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        activity('exports')->causedBy($request->user())->event('export')->withProperties(['type' => $type])->log("Exportación de {$type}");

        [$headers, $query, $map] = match ($type) {
            'socios' => [
                ['N° socio', 'Apellido', 'Nombre', 'Documento', 'Nacimiento', 'Email', 'Teléfono', 'Categoría', 'Estado', 'Ingreso'],
                Member::with('category')->orderBy('last_name'),
                fn (Member $m) => [$m->member_number, $m->last_name, $m->first_name, "{$m->document_type} {$m->document_number}", $m->birth_date?->format('d/m/Y'), $m->email, $m->phone, $m->category?->name, $m->status->label(), $m->admission_date?->format('d/m/Y')],
            ],
            'pagos' => [
                ['Recibo', 'Fecha', 'Socio', 'N° socio', 'Importe', 'Medio', 'Referencia', 'Estado'],
                Payment::with('member')->orderByDesc('payment_date')
                    ->when($request->date('desde'), fn ($q, $d) => $q->whereDate('payment_date', '>=', $d))
                    ->when($request->date('hasta'), fn ($q, $d) => $q->whereDate('payment_date', '<=', $d)),
                fn (Payment $p) => [$p->receipt_number, $p->payment_date->format('d/m/Y'), $p->member?->sortableName(), $p->member?->member_number, number_format((float) $p->amount, 2, ',', ''), $p->method->label(), $p->reference, $p->status->label()],
            ],
            'cuotas' => [
                ['Socio', 'N° socio', 'Concepto', 'Tipo', 'Período', 'Vencimiento', 'Importe', 'Recargo', 'Pagado', 'Saldo', 'Estado'],
                Fee::with('member')->orderByDesc('due_date')
                    ->when($request->input('estado'), fn ($q, $s) => $q->where('status', $s)),
                fn (Fee $f) => [$f->member?->sortableName(), $f->member?->member_number, $f->concept, $f->type->label(), $f->period?->format('m/Y'), $f->due_date->format('d/m/Y'), number_format((float) $f->amount, 2, ',', ''), number_format((float) $f->surcharge, 2, ',', ''), number_format((float) $f->paid_amount, 2, ',', ''), number_format((float) $f->balance(), 2, ',', ''), $f->status->label()],
            ],
        };

        $filename = $type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $query, $map) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            $query->lazy(500)->each(fn ($row) => fputcsv($out, $map($row), ';'));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
