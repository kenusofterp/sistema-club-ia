<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Aumento masivo de cuotas de actividades/niveles. Rige desde la próxima generación:
 * las cuotas ya generadas no cambian. Las becas en % siguen solas porque se calculan sobre la cuota.
 */
class ActivityFeeService
{
    public const MODE_AMOUNT = 'monto';

    public const MODE_PERCENT = 'porcentaje';

    /** Nuevo importe según el criterio, redondeado a 2 decimales y nunca negativo. */
    public function apply(string $current, string $mode, string $value): string
    {
        $new = $mode === self::MODE_PERCENT
            ? bcadd($current, bcdiv(bcmul($current, $value, 6), '100', 6), 6)
            : bcadd($current, $value, 6);

        // Redondeo a 2 decimales (half up).
        $rounded = bcdiv(bcadd(bcmul($new, '100', 6), bccomp($new, '0', 6) >= 0 ? '0.5' : '-0.5', 6), '100', 2);

        return bccomp($rounded, '0', 2) < 0 ? '0.00' : $rounded;
    }

    /**
     * Vista previa: actividad => [antes, después] y cuántas cuotas individuales fijas se ajustan.
     *
     * @param  array<int, int>  $activityIds
     * @return array{activities: array<int, array{name: string, from: string, to: string}>, customFees: int}
     */
    public function preview(array $activityIds, string $mode, string $value): array
    {
        $this->validateInput($activityIds, $mode, $value);

        $activities = Activity::whereIn('id', $activityIds)->orderBy('name')->get();

        return [
            'activities' => $activities->map(fn (Activity $a) => [
                'name' => $a->name,
                'from' => (string) $a->monthly_fee,
                'to' => $this->apply((string) $a->monthly_fee, $mode, $value),
            ])->values()->all(),
            'customFees' => $this->customFeesQuery($activityIds)->count(),
        ];
    }

    /**
     * @param  array<int, int>  $activityIds
     * @return int cantidad de actividades actualizadas
     */
    public function increase(array $activityIds, string $mode, string $value): int
    {
        $this->validateInput($activityIds, $mode, $value);

        return DB::transaction(function () use ($activityIds, $mode, $value) {
            $activities = Activity::whereIn('id', $activityIds)->lockForUpdate()->get();
            $changes = [];

            foreach ($activities as $activity) {
                $to = $this->apply((string) $activity->monthly_fee, $mode, $value);
                $changes[$activity->name] = [(string) $activity->monthly_fee, $to];
                $activity->update(['monthly_fee' => $to]);
            }

            // Las cuotas individuales fijas suben con el mismo criterio.
            $this->customFeesQuery($activityIds)->lockForUpdate()->get()
                ->each(fn (Enrollment $e) => $e->update(['fee_amount' => $this->apply((string) $e->fee_amount, $mode, $value)]));

            $label = $mode === self::MODE_PERCENT ? "{$value} %" : money($value);
            activity('fees')
                ->withProperties(['mode' => $mode, 'value' => $value, 'changes' => $changes])
                ->log("Aumento masivo de cuotas: {$label} a {$activities->count()} ".mb_strtolower(activity_label(true)));

            return $activities->count();
        });
    }

    private function customFeesQuery(array $activityIds)
    {
        return Enrollment::query()
            ->whereIn('activity_id', $activityIds)
            ->where('status', EnrollmentStatus::Active)
            ->whereNotNull('fee_amount');
    }

    private function validateInput(array $activityIds, string $mode, string $value): void
    {
        if ($activityIds === []) {
            throw new BusinessRuleException('Elegí al menos un '.mb_strtolower(activity_label()).'.');
        }

        if (! in_array($mode, [self::MODE_AMOUNT, self::MODE_PERCENT], true)) {
            throw new BusinessRuleException('Tipo de aumento inválido.');
        }

        if (! is_numeric($value) || bccomp($value, '0', 2) === 0) {
            throw new BusinessRuleException('Indicá el monto o porcentaje del aumento.');
        }

        if ($mode === self::MODE_PERCENT && bccomp($value, '-100', 2) <= 0) {
            throw new BusinessRuleException('El porcentaje no puede ser -100 % o menos.');
        }
    }
}
