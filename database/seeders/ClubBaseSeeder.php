<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Facility;
use App\Models\MemberCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Categorías, actividades e instalaciones iniciales (editables desde el sistema). */
class ClubBaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Infantil', 'description' => 'Menores de 12 años', 'monthly_fee' => 8000, 'admission_fee' => 0, 'min_age' => 0, 'max_age' => 11],
            ['name' => 'Cadete', 'description' => 'De 12 a 17 años', 'monthly_fee' => 11000, 'admission_fee' => 5000, 'min_age' => 12, 'max_age' => 17],
            ['name' => 'Activo', 'description' => 'Mayores de 18 años', 'monthly_fee' => 18000, 'admission_fee' => 15000, 'min_age' => 18, 'max_age' => null],
            ['name' => 'Familiar', 'description' => 'Integrante del grupo familiar de un socio activo', 'monthly_fee' => 12000, 'admission_fee' => 0, 'min_age' => null, 'max_age' => null],
            ['name' => 'Vitalicio', 'description' => 'Socios con 40 o más años de antigüedad', 'monthly_fee' => 0, 'admission_fee' => 0, 'min_age' => null, 'max_age' => null],
        ];

        foreach ($categories as $i => $category) {
            MemberCategory::firstOrCreate(['name' => $category['name']], [...$category, 'sort_order' => $i]);
        }

        $activities = [
            ['Fútbol infantil', 'Escuela de fútbol formativo para chicos y chicas.', 9000, 40, 5, 13, [[2, '17:00', '18:30'], [4, '17:00', '18:30']], 'Cancha 1'],
            ['Natación', 'Clases por niveles en pileta climatizada.', 12000, 30, 4, null, [[1, '18:00', '19:00'], [3, '18:00', '19:00'], [5, '18:00', '19:00']], 'Pileta'],
            ['Tenis', 'Clases grupales para principiantes e intermedios.', 14000, 16, 8, null, [[2, '19:00', '20:30'], [4, '19:00', '20:30']], 'Canchas de tenis'],
            ['Básquet', 'Formativo y competitivo en liga regional.', 10000, 30, 10, null, [[1, '19:00', '21:00'], [3, '19:00', '21:00']], 'Gimnasio'],
            ['Gimnasio y musculación', 'Sala de musculación con profesor.', 9500, null, 16, null, [[1, '07:00', '22:00'], [2, '07:00', '22:00'], [3, '07:00', '22:00'], [4, '07:00', '22:00'], [5, '07:00', '22:00']], 'Sala de musculación'],
            ['Patín artístico', 'Iniciación y competencia.', 9000, 25, 5, null, [[6, '10:00', '12:00']], 'Gimnasio'],
        ];

        foreach ($activities as [$name, $summary, $fee, $capacity, $minAge, $maxAge, $schedules, $location]) {
            $activity = Activity::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'summary' => $summary,
                'description' => $summary,
                'monthly_fee' => $fee,
                'capacity' => $capacity,
                'min_age' => $minAge,
                'max_age' => $maxAge,
            ]);

            if ($activity->wasRecentlyCreated) {
                foreach ($schedules as [$day, $start, $end]) {
                    $activity->schedules()->create(['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'location' => $location]);
                }
            }
        }

        $facilities = [
            ['Cancha de fútbol 5', 'Césped sintético con iluminación.', 15000, 10, '09:00', '23:00', 60, 2],
            ['Cancha de tenis', 'Polvo de ladrillo.', 8000, 4, '08:00', '21:00', 60, 2],
            ['Quincho', 'Quincho con parrilla para eventos familiares (hasta 40 personas).', 25000, 40, '10:00', '23:00', 180, 2],
            ['Pileta', 'Pileta semiolímpica climatizada.', 0, 60, '07:00', '21:00', 60, 1],
        ];

        foreach ($facilities as [$name, $description, $rate, $capacity, $opens, $closes, $slot, $maxSlots]) {
            Facility::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'description' => $description,
                'hourly_rate' => $rate,
                'capacity' => $capacity,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'slot_minutes' => $slot,
                'max_slots_per_booking' => $maxSlots,
                'is_bookable' => $rate > 0,
            ]);
        }
    }
}
