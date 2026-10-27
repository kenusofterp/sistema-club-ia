<?php

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Events\StationAdvanced;
use App\Exceptions\BusinessRuleException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionStation;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\StationJudge;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

/**
 * Puestos de evaluación, sus 3 jueces y la cola de pasada de cada puesto. Una participante pasa por un
 * solo puesto por ejercicio (lo garantiza también la base: unique entry × ejercicio).
 */
class StationService
{
    /** @param array{name: string, competition_exercise_id: int, competition_level_id?: ?int, competition_group_id?: ?int} $data */
    public function save(Competition $competition, ?CompetitionStation $station, array $data): CompetitionStation
    {
        $this->assertEditable($competition);

        $exercise = $competition->exercises()->find($data['competition_exercise_id']);
        if (! $exercise) {
            throw new BusinessRuleException('Elegí un ejercicio de la competencia.');
        }

        $levelId = $data['competition_level_id'] ?? null;
        $groupId = $data['competition_group_id'] ?? null;
        $level = $levelId ? $competition->levels()->find($levelId) : null;
        if ($levelId && ! $level) {
            throw new BusinessRuleException('El nivel no es de esta competencia.');
        }
        if ($groupId && (! $level || ! $level->groups()->whereKey($groupId)->exists())) {
            throw new BusinessRuleException('El grupo tiene que pertenecer al nivel elegido.');
        }

        $name = trim($data['name']);
        if ($competition->stations()->where('name', $name)->when($station, fn ($q) => $q->whereKeyNot($station->id))->exists()) {
            throw new BusinessRuleException("Ya hay un puesto llamado «{$name}».");
        }

        if ($station && $station->competition_exercise_id !== $exercise->id && $station->evaluations()->exists()) {
            throw new BusinessRuleException('El puesto ya tiene participantes asignadas: no se puede cambiar el ejercicio.');
        }

        if ($station && ($levelId || $groupId)) {
            $outside = $station->evaluations()->whereHas('entry', fn ($q) => $q
                ->when($levelId, fn ($q) => $q->where('competition_level_id', '!=', $levelId))
                ->when($groupId, fn ($q) => $q->where(fn ($q) => $q->where('competition_group_id', '!=', $groupId)->orWhereNull('competition_group_id'))))->exists();
            if ($outside) {
                throw new BusinessRuleException('El puesto tiene participantes de otro nivel o grupo: reasignalas antes de limitarlo.');
            }
        }

        $attributes = [
            'name' => $name,
            'competition_exercise_id' => $exercise->id,
            'competition_level_id' => $levelId,
            'competition_group_id' => $groupId,
        ];

        if ($station) {
            $station->update($attributes);

            return $station;
        }

        return $competition->stations()->create([...$attributes, 'sort_order' => (int) $competition->stations()->max('sort_order') + 1]);
    }

    public function delete(CompetitionStation $station): void
    {
        $this->assertEditable($station->competition);

        if ($station->evaluations()->exists()) {
            throw new BusinessRuleException('El puesto tiene participantes asignadas: reasignalas o quitalas antes de borrarlo.');
        }

        $station->delete();
    }

    /**
     * Arma el tribunal del puesto: asiento => usuario (hasta 3). Se puede reemplazar a un juez durante la
     * competencia; las notas ya cargadas quedan a nombre de quien las puso.
     *
     * @param  array<int, int|null>  $judges  asiento (1..3) => user_id
     */
    public function setJudges(CompetitionStation $station, array $judges): CompetitionStation
    {
        $competition = $station->competition;
        $this->assertEditable($competition);

        $judges = array_filter($judges);
        foreach (array_keys($judges) as $seat) {
            if ($seat < 1 || $seat > CompetitionStation::JUDGES) {
                throw new BusinessRuleException('Los asientos del tribunal van del 1 al '.CompetitionStation::JUDGES.'.');
            }
        }

        if (count($judges) !== count(array_unique($judges))) {
            throw new BusinessRuleException('Un mismo juez no puede ocupar dos asientos.');
        }

        if ($competition->isInProgress() && count($judges) < CompetitionStation::JUDGES) {
            throw new BusinessRuleException('Con la competencia en curso el puesto tiene que mantener sus '.CompetitionStation::JUDGES.' jueces.');
        }

        $organizationId = Organization::currentId();
        $users = User::whereIn('id', $judges)->get()->keyBy('id');
        foreach ($judges as $userId) {
            $user = $users->get($userId);
            if (! $user || ! $user->is_active || ! ($user->isSuperAdmin() || $user->hasPermissionIn('notas.cargar', $organizationId))) {
                throw new BusinessRuleException('Los jueces tienen que ser usuarios activos con el permiso para cargar notas (rol Juez).');
            }
        }

        $elsewhere = StationJudge::where('competition_id', $competition->id)
            ->where('competition_station_id', '!=', $station->id)
            ->whereIn('user_id', $judges)
            ->with(['user', 'station'])
            ->first();
        if ($elsewhere) {
            throw new BusinessRuleException("{$elsewhere->user->name} ya es juez del puesto «{$elsewhere->station->name}».");
        }

        DB::transaction(function () use ($station, $competition, $judges) {
            $station->judges()->delete();
            foreach ($judges as $seat => $userId) {
                $station->judges()->create(['competition_id' => $competition->id, 'user_id' => $userId, 'seat' => $seat]);
            }
        });

        return $station->load('judges.user');
    }

    /** Asigna la participante al final de la cola del puesto. */
    public function assign(CompetitionStation $station, CompetitionEntry $entry): Evaluation
    {
        $competition = $station->competition;
        $this->assertEditable($competition);

        if ($entry->competition_id !== $competition->id) {
            throw new BusinessRuleException('La participante no está inscripta en esta competencia.');
        }

        $this->assertFits($station, $entry);

        return DB::transaction(function () use ($station, $entry, $competition) {
            CompetitionStation::query()->lockForUpdate()->findOrFail($station->id);

            $existing = Evaluation::where('competition_entry_id', $entry->id)
                ->where('competition_exercise_id', $station->competition_exercise_id)
                ->with('station')
                ->first();
            if ($existing) {
                throw new BusinessRuleException("{$entry->competitor->fullName()} ya está asignada a «{$existing->station->name}» para este ejercicio.");
            }

            $evaluation = Evaluation::create([
                'competition_id' => $competition->id,
                'competition_entry_id' => $entry->id,
                'competition_exercise_id' => $station->competition_exercise_id,
                'competition_station_id' => $station->id,
                'position' => (int) $station->evaluations()->max('position') + 1,
                'status' => EvaluationStatus::Pending,
            ]);

            Realtime::send(new StationAdvanced($station));

            return $evaluation;
        });
    }

    /**
     * Asigna automáticamente a las inscriptas que todavía no tienen puesto en cada ejercicio, repartiéndolas
     * entre los puestos compatibles (por nivel/grupo) que tengan menos gente.
     *
     * @return int asignaciones hechas
     */
    public function assignPending(Competition $competition): int
    {
        $this->assertEditable($competition);
        $assigned = 0;

        $stations = $competition->stations()->withCount('evaluations')->get();
        $entries = $competition->entries()->with(['competitor', 'evaluations'])->get()
            ->sortBy(fn (CompetitionEntry $e) => [$e->competition_level_id, $e->competition_group_id, (int) $e->bib_number]);

        foreach ($competition->exercises as $exercise) {
            $candidates = $stations->where('competition_exercise_id', $exercise->id);

            foreach ($entries as $entry) {
                if ($entry->evaluations->contains('competition_exercise_id', $exercise->id)) {
                    continue;
                }

                $station = $candidates
                    ->filter(fn (CompetitionStation $s) => $this->fits($s, $entry))
                    ->sortBy('evaluations_count')
                    ->first();

                if ($station) {
                    $this->assign($station, $entry);
                    $station->evaluations_count++;
                    $assigned++;
                }
            }
        }

        return $assigned;
    }

    /** Pasa una pasada pendiente a otro puesto del mismo ejercicio (al final de su cola). */
    public function move(Evaluation $evaluation, CompetitionStation $target): Evaluation
    {
        $this->assertEditable($evaluation->competition);
        $this->assertUntouched($evaluation);

        if ($target->competition_exercise_id !== $evaluation->competition_exercise_id) {
            throw new BusinessRuleException('Solo se puede pasar a otro puesto del mismo ejercicio.');
        }

        $this->assertFits($target, $evaluation->entry);
        $source = $evaluation->station;

        DB::transaction(function () use ($evaluation, $target) {
            $evaluation->update([
                'competition_station_id' => $target->id,
                'position' => (int) $target->evaluations()->max('position') + 1,
            ]);
        });

        Realtime::send(new StationAdvanced($source));
        Realtime::send(new StationAdvanced($target));

        return $evaluation;
    }

    public function unassign(Evaluation $evaluation): void
    {
        $this->assertEditable($evaluation->competition);
        $this->assertUntouched($evaluation);
        $station = $evaluation->station;

        $evaluation->delete();
        Realtime::send(new StationAdvanced($station));
    }

    /**
     * Reordena las pasadas pendientes que todavía no empezaron (ids en el orden recibido). Quedan fijas
     * adelante las publicadas y la que ya está siendo evaluada (algún juez confirmó); las ausentes, al final.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(CompetitionStation $station, array $orderedIds): void
    {
        $this->assertEditable($station->competition);

        DB::transaction(function () use ($station, $orderedIds) {
            $evaluations = $station->evaluations()->withCount('sheets')->lockForUpdate()->get();
            $published = $evaluations->where('status', EvaluationStatus::Published);
            $absent = $evaluations->where('status', EvaluationStatus::Absent);
            $pending = $evaluations->where('status', EvaluationStatus::Pending);
            $started = $pending->where('sheets_count', '>', 0);
            $sortable = $pending->where('sheets_count', 0)->keyBy('id');

            $ordered = collect($orderedIds)->map(fn ($id) => $sortable->get((int) $id))->filter()->unique('id');
            $ordered = $ordered->concat($sortable->except($ordered->pluck('id')->all()));

            $position = 1;
            foreach ($published->concat($started)->concat($ordered)->concat($absent) as $evaluation) {
                if ($evaluation->position !== $position) {
                    $evaluation->update(['position' => $position]);
                }
                $position++;
            }
        });

        Realtime::send(new StationAdvanced($station));
    }

    /** Manda la pasada al final de la cola (p. ej. la participante no está lista). */
    public function sendToEnd(Evaluation $evaluation): void
    {
        $this->assertEditable($evaluation->competition);
        $this->assertUntouched($evaluation);

        $evaluation->update(['position' => (int) $evaluation->station->evaluations()->max('position') + 1]);
        Realtime::send(new StationAdvanced($evaluation->station));
    }

    /** No se presentó: no rankea en ese ejercicio y el puesto pasa a la siguiente. */
    public function markAbsent(Evaluation $evaluation): void
    {
        $this->assertEditable($evaluation->competition);
        $this->assertUntouched($evaluation);

        $evaluation->update(['status' => EvaluationStatus::Absent]);
        Realtime::send(new StationAdvanced($evaluation->station));
    }

    /** Vuelve a la cola (al final) una participante marcada como ausente. */
    public function restore(Evaluation $evaluation): void
    {
        $this->assertEditable($evaluation->competition);

        if ($evaluation->status !== EvaluationStatus::Absent) {
            throw new BusinessRuleException('Solo se puede volver a la cola a una participante marcada como ausente.');
        }

        $evaluation->update([
            'status' => EvaluationStatus::Pending,
            'position' => (int) $evaluation->station->evaluations()->max('position') + 1,
        ]);
        Realtime::send(new StationAdvanced($evaluation->station));
    }

    public function fits(CompetitionStation $station, CompetitionEntry $entry): bool
    {
        return (! $station->competition_level_id || $station->competition_level_id === $entry->competition_level_id)
            && (! $station->competition_group_id || $station->competition_group_id === $entry->competition_group_id);
    }

    private function assertFits(CompetitionStation $station, CompetitionEntry $entry): void
    {
        if (! $this->fits($station, $entry)) {
            throw new BusinessRuleException("El puesto «{$station->name}» es de otro nivel o grupo.");
        }
    }

    /** Pendiente y sin notas de ningún juez. */
    private function assertUntouched(Evaluation $evaluation): void
    {
        if ($evaluation->status !== EvaluationStatus::Pending) {
            throw new BusinessRuleException('Solo se pueden mover pasadas pendientes.');
        }

        if ($evaluation->sheets()->exists()) {
            throw new BusinessRuleException('Algún juez ya confirmó su nota para esta participante: hay que terminar la evaluación.');
        }
    }

    private function assertEditable(Competition $competition): void
    {
        if ($competition->isFinished()) {
            throw new BusinessRuleException('La competencia está finalizada.');
        }
    }
}
