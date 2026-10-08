<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsForEachOrganization;
use App\Models\Organization;
use App\Services\LevelLessonService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('club:generar-clases')]
#[Description('Genera por adelantado las clases de las actividades con horario (en cada entidad)')]
class GenerateLevelLessonsCommand extends Command
{
    use RunsForEachOrganization;

    public function handle(LevelLessonService $lessons): int
    {
        $this->forEachOrganization(function (Organization $org) use ($lessons) {
            $created = $lessons->generate();
            $this->info("{$org->name}: {$created} clases generadas");
        });

        return self::SUCCESS;
    }
}
