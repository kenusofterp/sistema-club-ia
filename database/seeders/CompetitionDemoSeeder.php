<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\Exercise;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\CompetitorService;
use App\Services\ExerciseService;
use App\Services\JudgingService;
use App\Services\StationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Competencia de ejemplo en curso: ejercicios con criterios, 2 niveles, 3 puestos con 9 jueces,
 * 16 participantes de varios clubes y algunas notas ya publicadas (con empates).
 * Jueces: juez1@demo.com … juez9@demo.com / Demo1234 (juez1 es juez principal).
 * Uso: php artisan db:seed --class=CompetitionDemoSeeder (entidad principal) o con SITE_ORG=slug.
 */
class CompetitionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $org = ($slug = env('SITE_ORG'))
            ? Organization::where('slug', $slug)->firstOrFail()
            : (Organization::where('is_default', true)->first() ?? Organization::query()->orderBy('id')->firstOrFail());

        Organization::runFor($org, function (Organization $org) {
            $organizer = User::where('is_super_admin', true)->firstOrFail();

            $judges = collect(range(1, 9))->map(function (int $n) {
                $user = User::firstOrCreate(['email' => "juez{$n}@demo.com"], ['name' => "Juez {$n}", 'password' => 'Demo1234', 'is_active' => true]);
                $user->assignRole($n === 1 ? 'juez_principal' : 'juez');

                return $user;
            });

            $exercises = $this->exercises();

            $competition = app(CompetitionService::class)->save(null, [
                'name' => 'Torneo Apertura (demo)',
                'date' => today()->toDateString(),
                'location' => 'Gimnasio principal',
            ], [
                ['name' => 'Nivel 1'],
                ['name' => 'Nivel 2', 'groups' => [['name' => 'Grupo A'], ['name' => 'Grupo B']]],
            ], $exercises->pluck('id')->all(), $organizer);

            $stations = app(StationService::class);
            $salto = $competition->exercises->firstWhere('exercise_id', $exercises[0]->id);
            $suelo = $competition->exercises->firstWhere('exercise_id', $exercises[1]->id);
            $panels = [
                $stations->save($competition, null, ['name' => 'Salto 1', 'competition_exercise_id' => $salto->id]),
                $stations->save($competition, null, ['name' => 'Salto 2', 'competition_exercise_id' => $salto->id]),
                $stations->save($competition, null, ['name' => 'Suelo 1', 'competition_exercise_id' => $suelo->id]),
            ];
            foreach ($panels as $i => $station) {
                $stations->setJudges($station, [1 => $judges[$i * 3]->id, 2 => $judges[$i * 3 + 1]->id, 3 => $judges[$i * 3 + 2]->id]);
            }

            $this->participants($competition);
            $stations->assignPending($competition);
            app(CompetitionService::class)->start($competition);

            // Algunas notas publicadas en Salto 1 (las dos primeras empatan).
            $scores = [['8.4', '8.5', '8.5'], ['8.5', '8.4', '8.5'], ['7.9', '8.2', '8.0']];
            $first = $competition->stations()->where('name', 'Salto 1')->first();
            foreach ($scores as $bySeat) {
                $evaluation = $first->currentEvaluation();
                if (! $evaluation) {
                    break;
                }
                $criteria = $evaluation->competitionExercise->criteria;
                foreach ($first->judges as $judge) {
                    app(JudgingService::class)->confirm($evaluation, $judge->user, [
                        $criteria[0]->id => $bySeat[$judge->seat - 1],
                        $criteria[1]->id => '0',
                        $criteria[2]->id => true,
                    ]);
                }
            }

            $this->command?->info("Competencia demo en «{$org->name}». Jueces: juez1@demo.com … juez9@demo.com / Demo1234.");
        });
    }

    /** @return Collection<int, Exercise> */
    private function exercises()
    {
        $service = app(ExerciseService::class);
        $definitions = [
            'Salto' => [
                ['name' => 'Ejecución', 'type' => 'puntaje', 'min' => '0', 'max' => '10', 'decimals' => 1, 'weight' => '1'],
                ['name' => 'Faltas de aterrizaje', 'type' => 'deduccion', 'min' => '0', 'max' => '2', 'decimals' => 1, 'weight' => '1'],
                ['name' => 'Carrera completa', 'type' => 'si_no', 'min' => '0', 'max' => '1', 'decimals' => 0, 'weight' => '0.5'],
            ],
            'Suelo' => [
                ['name' => 'Ejecución', 'type' => 'puntaje', 'min' => '0', 'max' => '10', 'decimals' => 1, 'weight' => '1'],
                ['name' => 'Penalización', 'type' => 'deduccion', 'min' => '0', 'max' => '3', 'decimals' => 1, 'weight' => '1'],
                ['name' => 'Elemento obligatorio', 'type' => 'si_no', 'min' => '0', 'max' => '1', 'decimals' => 0, 'weight' => '0.5'],
            ],
        ];

        return collect($definitions)->map(fn ($criteria, $name) => Exercise::where('name', $name)->first()
            ?? $service->save(null, ['name' => $name], $criteria))->values();
    }

    private function participants(Competition $competition): void
    {
        $service = app(CompetitorService::class);
        $clubs = ['Club Rival', 'Gimnasia del Sur', 'Atlético Norte', setting('site.name')];
        $names = ['Ana', 'Bea', 'Caro', 'Dani', 'Eli', 'Flor', 'Guada', 'Helena', 'Iara', 'Juli', 'Kiara', 'Lola', 'Mora', 'Nina', 'Olivia', 'Pili'];
        $surnames = ['Gómez', 'Pérez', 'Díaz', 'López', 'Romero', 'Sosa', 'Torres', 'Ruiz'];
        $levels = $competition->levels()->with('groups')->get();

        foreach ($names as $i => $name) {
            $competitor = $service->save(null, ['first_name' => $name, 'last_name' => $surnames[$i % count($surnames)], 'institution' => $clubs[$i % count($clubs)]]);
            $level = $levels[$i % 2];
            $service->enroll($competition, $competitor, $level->id, $level->groups->isNotEmpty() ? $level->groups[$i % 4 < 2 ? 0 : 1]->id : null);
        }
    }
}
