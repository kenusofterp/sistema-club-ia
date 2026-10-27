<?php

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionGroup;
use App\Models\Competitor;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/** Participantes de competencias (de cualquier institución) e inscripción con nivel, grupo y dorsal. */
class CompetitorService
{
    /** @param array{first_name: string, last_name: string, document_number?: ?string, institution?: ?string, member_id?: ?int} $data */
    public function save(?Competitor $competitor, array $data): Competitor
    {
        $document = trim((string) ($data['document_number'] ?? '')) ?: null;

        if ($document && Competitor::where('document_number', $document)->when($competitor, fn ($q) => $q->whereKeyNot($competitor->id))->exists()) {
            throw new BusinessRuleException("Ya hay una participante con el documento {$document}.");
        }

        $attributes = [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'document_number' => $document,
            'institution' => trim((string) ($data['institution'] ?? '')) ?: null,
            'member_id' => $data['member_id'] ?? $competitor?->member_id,
        ];

        $competitor ? $competitor->update($attributes) : $competitor = Competitor::create($attributes);

        return $competitor;
    }

    /** Participante a partir de un socio del club (si ya existe por socio o documento, la reutiliza). */
    public function fromMember(Member $member): Competitor
    {
        $existing = Competitor::where('member_id', $member->id)
            ->when($member->document_number, fn ($q) => $q->orWhere('document_number', $member->document_number))
            ->first();

        if ($existing) {
            $existing->member_id ??= $member->id;
            $existing->save();

            return $existing;
        }

        return Competitor::create([
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'document_number' => $member->document_number,
            'institution' => setting('site.name'),
            'member_id' => $member->id,
        ]);
    }

    /** Inscribe a la participante. Sin dorsal, toma el siguiente número libre. */
    public function enroll(Competition $competition, Competitor $competitor, int $levelId, ?int $groupId, ?string $bib = null): CompetitionEntry
    {
        $this->assertEditable($competition);
        [$levelId, $groupId] = $this->validCategory($competition, $levelId, $groupId);

        return DB::transaction(function () use ($competition, $competitor, $levelId, $groupId, $bib) {
            Competition::query()->lockForUpdate()->findOrFail($competition->id);

            if ($competition->entries()->where('competitor_id', $competitor->id)->exists()) {
                throw new BusinessRuleException("{$competitor->fullName()} ya está inscripta.");
            }

            $bib = $this->bib($competition, $bib);

            return $competition->entries()->create([
                'competitor_id' => $competitor->id,
                'competition_level_id' => $levelId,
                'competition_group_id' => $groupId,
                'bib_number' => $bib,
            ]);
        });
    }

    public function updateEntry(CompetitionEntry $entry, int $levelId, ?int $groupId, string $bib): CompetitionEntry
    {
        $competition = $entry->competition;
        $this->assertEditable($competition);
        [$levelId, $groupId] = $this->validCategory($competition, $levelId, $groupId);

        $changesCategory = $levelId !== $entry->competition_level_id || $groupId !== $entry->competition_group_id;
        if ($changesCategory && $entry->evaluations()->where('status', EvaluationStatus::Published)->exists()) {
            throw new BusinessRuleException('No se puede cambiar el nivel o grupo de una participante que ya tiene notas publicadas.');
        }

        // Si quedó asignada a puestos de otro nivel/grupo, hay que reasignarla.
        if ($changesCategory) {
            $conflict = $entry->evaluations()->with('station')->get()->first(fn ($e) => ($e->station->competition_level_id && $e->station->competition_level_id !== $levelId)
                || ($e->station->competition_group_id && $e->station->competition_group_id !== $groupId));
            if ($conflict) {
                throw new BusinessRuleException("Está asignada al puesto «{$conflict->station->name}», que es de otro nivel o grupo. Reasignala primero.");
            }
        }

        $bib = trim($bib);
        if ($bib === '' || $competition->entries()->where('bib_number', $bib)->whereKeyNot($entry->id)->exists()) {
            throw new BusinessRuleException("El dorsal «{$bib}» ya está usado o está vacío.");
        }

        $entry->update(['competition_level_id' => $levelId, 'competition_group_id' => $groupId, 'bib_number' => $bib]);

        return $entry;
    }

    /** Da de baja la inscripción (y sus pasadas en las colas) si todavía no tiene notas. */
    public function withdraw(CompetitionEntry $entry): void
    {
        $this->assertEditable($entry->competition);

        if ($entry->evaluations()->where(fn ($q) => $q->where('status', EvaluationStatus::Published)->orWhereHas('sheets'))->exists()) {
            throw new BusinessRuleException('La participante ya tiene notas cargadas: no se puede dar de baja. Marcala como ausente en los puestos que falten.');
        }

        $entry->delete();
    }

    private function assertEditable(Competition $competition): void
    {
        if ($competition->isFinished()) {
            throw new BusinessRuleException('La competencia está finalizada.');
        }
    }

    /** @return array{0: int, 1: ?int} */
    private function validCategory(Competition $competition, int $levelId, ?int $groupId): array
    {
        $level = $competition->levels()->with('groups')->find($levelId);
        if (! $level) {
            throw new BusinessRuleException('Elegí un nivel de la competencia.');
        }

        if ($level->groups->isNotEmpty() && ! $groupId) {
            throw new BusinessRuleException("Elegí el grupo dentro de «{$level->name}».");
        }

        if ($groupId && ! $level->groups->contains(fn (CompetitionGroup $g) => $g->id === $groupId)) {
            throw new BusinessRuleException('El grupo no pertenece a ese nivel.');
        }

        return [$level->id, $groupId];
    }

    private function bib(Competition $competition, ?string $bib): string
    {
        $bib = trim((string) $bib);

        if ($bib === '') {
            $used = $competition->entries()->pluck('bib_number')->filter(fn ($b) => ctype_digit($b))->map(fn ($b) => (int) $b);

            return (string) (($used->max() ?? 0) + 1);
        }

        if ($competition->entries()->where('bib_number', $bib)->exists()) {
            throw new BusinessRuleException("El dorsal {$bib} ya está usado.");
        }

        return $bib;
    }
}
