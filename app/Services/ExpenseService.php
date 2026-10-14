<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gastos de la entidad: alquiler del salón, servicios, sueldos… Con lo cobrado arman el resultado
 * (recaudado − gastado) del tablero.
 */
class ExpenseService
{
    /**
     * Registra o corrige un gasto.
     *
     * @param  array{expense_date: string, category: string, amount: string, method: string, description?: ?string, supplier?: ?string, facility_id?: ?int, period?: ?string, reference?: ?string, attachment_path?: ?string}  $data
     */
    public function save(?Expense $expense, array $data, User $by): Expense
    {
        if (bccomp((string) $data['amount'], '0', 2) <= 0) {
            throw new BusinessRuleException('El importe del gasto tiene que ser mayor a cero.');
        }

        if (Carbon::parse($data['expense_date'])->isAfter(today())) {
            throw new BusinessRuleException('La fecha del gasto no puede ser futura: registralo el día que lo pagues.');
        }

        if (PaymentMethod::tryFrom($data['method']) === null || $data['method'] === PaymentMethod::AccountCredit->value) {
            throw new BusinessRuleException('Elegí con qué medio se pagó el gasto.');
        }

        $data['category'] = trim($data['category']);
        // El mes se guarda como su primer día ("2026-10" → 2026-10-01).
        $data['period'] = ! empty($data['period']) ? Carbon::parse($data['period'])->startOfMonth()->toDateString() : null;

        if ($expense) {
            unset($data['created_by']);
            $expense->update($data);

            return $expense;
        }

        return Expense::create([...$data, 'created_by' => $by->id]);
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    /** Total gastado entre dos fechas. */
    public function total(Carbon|string $from, Carbon|string $to): string
    {
        return (string) Expense::between($from, $to)->sum('amount');
    }

    /** @return Collection<string, string> categoría => total, de mayor a menor */
    public function byCategory(Carbon|string $from, Carbon|string $to): Collection
    {
        return Expense::between($from, $to)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category');
    }

    /**
     * Situación del alquiler de cada instalación que alguna vez se pagó: último pago y si el mes
     * actual ya está pago.
     *
     * @return Collection<int, array{facility: Facility, last: Expense, paidThisMonth: bool}>
     */
    public function rentStatus(?Carbon $month = null): Collection
    {
        $month = ($month ?? today())->copy()->startOfMonth();

        return Expense::with('facility')
            ->where('category', Expense::RENT)
            ->whereNotNull('facility_id')
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->get()
            ->groupBy('facility_id')
            ->filter(fn (Collection $rows) => $rows->first()->facility !== null)
            ->map(fn (Collection $rows) => [
                'facility' => $rows->first()->facility,
                'last' => $rows->first(),
                'paidThisMonth' => $rows->contains(fn (Expense $e) => ($e->period ?? $e->expense_date->copy()->startOfMonth())->equalTo($month)),
            ])
            ->values();
    }
}
