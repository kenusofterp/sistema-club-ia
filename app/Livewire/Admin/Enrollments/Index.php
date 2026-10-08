<?php

namespace App\Livewire\Admin\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Member;
use App\Services\EnrollmentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Inscripciones')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    #[Url(as: 'actividad')]
    public string $activity = '';

    #[Url]
    public string $status = 'activa';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;

    public string $memberSearch = '';

    public ?int $memberId = null;

    public ?int $activityId = null;

    public function updated($property): void
    {
        if (in_array($property, ['activity', 'status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function updatedMemberSearch(): void
    {
        $this->memberId = null;
    }

    public function create(): void
    {
        $this->reset(['memberSearch', 'memberId']);
        $this->activityId = $this->activity ? (int) $this->activity : null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function selectMember(int $id): void
    {
        $member = Member::findOrFail($id);
        $this->memberId = $member->id;
        $this->memberSearch = $member->fullName();
    }

    public function enroll(EnrollmentService $service): void
    {
        $this->authorize('inscripciones.gestionar');
        $this->validate([
            'memberId' => 'required|exists:members,id',
            'activityId' => 'required|exists:activities,id',
        ], [], ['memberId' => 'socio', 'activityId' => 'actividad']);

        $done = $this->attempt(
            fn () => $service->enroll(Member::findOrFail($this->memberId), Activity::findOrFail($this->activityId)),
            'Inscripción registrada.'
        );

        if ($done) {
            $this->showForm = false;
        }
    }

    // ---- Cuota individual (beca o descuento) ----
    public ?int $editingFeeId = null;

    public string $feeAmount = '';

    public function editFee(int $id): void
    {
        $this->authorize('inscripciones.gestionar');
        $enrollment = Enrollment::findOrFail($id);
        $this->editingFeeId = $enrollment->id;
        $this->feeAmount = $enrollment->fee_amount !== null ? (string) $enrollment->fee_amount : '';
    }

    /** Vacío = vuelve a la cuota de la actividad. Se aplica desde la próxima cuota que se genere. */
    public function saveFee(): void
    {
        $this->authorize('inscripciones.gestionar');
        $this->validate(['feeAmount' => 'nullable|numeric|min:0|max:99999999'], [], ['feeAmount' => 'cuota']);

        Enrollment::findOrFail($this->editingFeeId)->update([
            'fee_amount' => $this->feeAmount === '' ? null : number_format((float) $this->feeAmount, 2, '.', ''),
        ]);
        $this->editingFeeId = null;
        $this->notify('Cuota individual guardada. Se aplica desde la próxima cuota que se genere.');
    }

    public function unenroll(int $id, EnrollmentService $service): void
    {
        $this->authorize('inscripciones.gestionar');
        $this->attempt(fn () => $service->unenroll(Enrollment::findOrFail($id)), 'Inscripción dada de baja.');
    }

    public function render()
    {
        $enrollments = Enrollment::query()
            ->with(['member', 'activity'])
            ->when($this->activity, fn ($q) => $q->where('activity_id', $this->activity))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->whereHas('member', fn ($m) => $m->search($this->search)))
            ->latest('start_date')
            ->latest('id')
            ->paginate(25);

        $memberResults = strlen($this->memberSearch) >= 2 && ! $this->memberId
            ? Member::active()->search($this->memberSearch)->limit(6)->get()
            : collect();

        return view('livewire.admin.enrollments.index', [
            'enrollments' => $enrollments,
            'activities' => Activity::orderBy('name')->get(['id', 'name', 'is_active']),
            'memberResults' => $memberResults,
            'statuses' => EnrollmentStatus::options(),
        ]);
    }
}
