<?php

namespace App\Livewire\Admin\Competitions;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Competitor;
use App\Models\Member;
use App\Services\CompetitorService;
use Livewire\Component;

/** Participantes inscriptas en la competencia: alta (o desde un socio), nivel, grupo y dorsal. */
class Entries extends Component
{
    use InteractsWithUi;

    public Competition $competition;

    public string $search = '';

    public string $filterLevel = '';

    // ---- Inscripción ----
    public bool $showForm = false;

    public ?int $editingEntryId = null;

    public ?int $competitorId = null;

    public ?int $memberId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $document_number = '';

    public string $institution = '';

    public ?int $level_id = null;

    public ?int $group_id = null;

    public string $bib = '';

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
    }

    /** Desde el buscador: participante ya cargada, socio del club o nueva. */
    public function pick(string $type, int $id = 0): void
    {
        $this->resetForm();
        $this->level_id = $this->competition->levels()->value('id');

        if ($type === 'competidora') {
            $competitor = Competitor::findOrFail($id);
            $this->competitorId = $competitor->id;
            $this->fill($competitor->only(['first_name', 'last_name']) + ['document_number' => (string) $competitor->document_number, 'institution' => (string) $competitor->institution]);
        } elseif ($type === 'socio') {
            $member = Member::findOrFail($id);
            $this->memberId = $member->id;
            $this->fill(['first_name' => $member->first_name, 'last_name' => $member->last_name, 'document_number' => (string) $member->document_number, 'institution' => (string) setting('site.name')]);
        } else {
            $this->last_name = $this->search;
        }

        $this->search = '';
        $this->showForm = true;
    }

    public function edit(int $entryId): void
    {
        $this->resetForm();
        $entry = $this->competition->entries()->with('competitor')->findOrFail($entryId);
        $this->editingEntryId = $entry->id;
        $this->competitorId = $entry->competitor_id;
        $this->fill([
            'first_name' => $entry->competitor->first_name,
            'last_name' => $entry->competitor->last_name,
            'document_number' => (string) $entry->competitor->document_number,
            'institution' => (string) $entry->competitor->institution,
            'level_id' => $entry->competition_level_id,
            'group_id' => $entry->competition_group_id,
            'bib' => $entry->bib_number,
        ]);
        $this->showForm = true;
    }

    public function updatedLevelId(): void
    {
        $this->group_id = null;
    }

    public function save(CompetitorService $service): void
    {
        $this->authorize('competencias.gestionar');
        $this->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'document_number' => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:120',
            'level_id' => 'required|integer',
            'group_id' => 'nullable|integer',
            'bib' => ($this->editingEntryId ? 'required' : 'nullable').'|string|max:10',
        ], [], ['first_name' => 'nombre', 'last_name' => 'apellido', 'document_number' => 'documento', 'institution' => 'club / institución', 'level_id' => 'nivel', 'bib' => 'dorsal']);

        $entry = $this->attempt(function () use ($service) {
            $data = $this->only(['first_name', 'last_name', 'document_number', 'institution']);

            if ($this->memberId) {
                $competitor = $service->fromMember(Member::findOrFail($this->memberId));
                $competitor = $service->save($competitor, $data);
            } else {
                $competitor = $service->save($this->competitorId ? Competitor::findOrFail($this->competitorId) : null, $data);
            }

            return $this->editingEntryId
                ? $service->updateEntry($this->competition->entries()->findOrFail($this->editingEntryId), (int) $this->level_id, $this->group_id ?: null, $this->bib)
                : $service->enroll($this->competition, $competitor, (int) $this->level_id, $this->group_id ?: null, $this->bib ?: null);
        });

        if ($entry) {
            $this->showForm = false;
            $this->notify($this->editingEntryId ? 'Inscripción actualizada.' : "Inscripta con el dorsal {$entry->bib_number}.");
            $this->dispatch('competition-changed');
        }
    }

    public function withdraw(int $entryId, CompetitorService $service): void
    {
        $this->authorize('competencias.gestionar');

        if ($this->attempt(fn () => $service->withdraw($this->competition->entries()->findOrFail($entryId)), 'Inscripción dada de baja.')) {
            $this->dispatch('competition-changed');
        }
    }

    private function resetForm(): void
    {
        $this->reset(['editingEntryId', 'competitorId', 'memberId', 'first_name', 'last_name', 'document_number', 'institution', 'level_id', 'group_id', 'bib']);
        $this->resetValidation();
    }

    public function render()
    {
        $levels = $this->competition->levels()->with('groups')->get();
        $enrolled = $this->competition->entries()->pluck('competitor_id');
        $term = trim($this->search);

        return view('livewire.admin.competitions.entries', [
            'entries' => $this->competition->entries()
                ->with(['competitor', 'level', 'group'])
                ->withCount(['evaluations'])
                ->when($this->filterLevel, fn ($q) => $q->where('competition_level_id', $this->filterLevel))
                ->get()
                ->sortBy(fn (CompetitionEntry $e) => [$e->level?->sort_order, $e->group?->sort_order, mb_strtolower($e->competitor->last_name)])
                ->values(),
            'levels' => $levels,
            'groups' => $this->level_id ? $levels->firstWhere('id', $this->level_id)?->groups ?? collect() : collect(),
            'competitorResults' => mb_strlen($term) >= 2 ? Competitor::search($term)->whereNotIn('id', $enrolled)->limit(6)->get() : collect(),
            'memberResults' => mb_strlen($term) >= 2
                ? Member::active()->search($term)->whereNotIn('id', Competitor::whereIn('id', $enrolled)->whereNotNull('member_id')->select('member_id'))->limit(4)->get()
                : collect(),
            'editable' => ! $this->competition->isFinished(),
        ]);
    }
}
