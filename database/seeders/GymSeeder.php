<?php

namespace Database\Seeders;

use App\Enums\PlanAccessType;
use App\Models\Activity;
use App\Models\MemberCategory;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Clases de gimnasio (solo con plan) y planes de ejemplo. Todo editable desde Gimnasio > Planes y precios. */
class GymSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['Funcional', 'Entrenamiento funcional en grupo, todos los niveles.', 20, [[1, '19:00', '20:00'], [3, '19:00', '20:00'], [5, '19:00', '20:00']]],
            ['Spinning', 'Ciclismo indoor con música.', 18, [[2, '08:00', '09:00'], [4, '08:00', '09:00'], [2, '20:00', '21:00'], [4, '20:00', '21:00']]],
            ['Yoga', 'Hatha yoga para flexibilidad y respiración.', 15, [[2, '18:00', '19:15'], [4, '18:00', '19:15']]],
        ];

        foreach ($classes as [$name, $summary, $capacity, $schedules]) {
            $activity = Activity::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'summary' => $summary,
                'description' => $summary,
                'monthly_fee' => 0,
                'capacity' => $capacity,
                'allows_enrollment' => false,
            ]);

            if ($activity->wasRecentlyCreated) {
                foreach ($schedules as [$day, $start, $end]) {
                    $activity->schedules()->create(['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'location' => 'Salón de clases']);
                }
            }
        }

        // Un gimnasio sin cuota social igual necesita una categoría para dar de alta socios.
        if (MemberCategory::query()->doesntExist()) {
            MemberCategory::create(['name' => 'Socio', 'description' => 'Socio del gimnasio', 'monthly_fee' => 0, 'admission_fee' => 0]);
        }

        if (Plan::query()->exists()) {
            return;
        }

        $weekdays = [1, 2, 3, 4, 5];
        $plans = [
            ['Musculación libre', 'Sala de aparatos y pesas sin límite.', 25000, 'meses', 1, PlanAccessType::Free, null, 'mes', null, [], false],
            ['Pase mañana', 'Sala de aparatos de lunes a viernes hasta las 14 h.', 18000, 'meses', 1, PlanAccessType::Free, null, 'mes', [['days' => $weekdays, 'from' => '06:00', 'to' => '14:00']], [], false],
            ['Full', 'Sala libre + todas las clases.', 38000, 'meses', 1, PlanAccessType::FreeAndClasses, null, 'mes', null, ['funcional', 'spinning', 'yoga'], true],
            ['Clases 2 veces por semana', 'Funcional, spinning o yoga, 2 clases semanales.', 20000, 'meses', 1, PlanAccessType::Classes, 2, 'semana', null, ['funcional', 'spinning', 'yoga'], false],
            ['Musculación trimestral', 'Sala libre por 3 meses con descuento.', 66000, 'meses', 3, PlanAccessType::Free, null, 'mes', null, [], false],
            ['Pase diario', 'Un día de acceso a la sala.', 4000, 'dias', 1, PlanAccessType::Free, 1, 'plan', null, [], false],
        ];

        foreach ($plans as $i => [$name, $description, $price, $unit, $value, $type, $limit, $period, $windows, $activities, $featured]) {
            $plan = Plan::create([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'duration_unit' => $unit,
                'duration_value' => $value,
                'access_type' => $type,
                'visit_limit' => $limit,
                'visit_period' => $period,
                'access_windows' => $windows,
                'is_featured' => $featured,
                'sort_order' => $i,
            ]);
            $plan->activities()->sync(Activity::whereIn('slug', $activities)->pluck('id'));
        }
    }
}
