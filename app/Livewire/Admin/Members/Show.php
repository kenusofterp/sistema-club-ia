<?php

namespace App\Livewire\Admin\Members;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\MemberService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class Show extends Component
{
    use InteractsWithUi;

    public Member $member;

    #[Url]
    public string $tab = 'cuenta';

    // Modal de cambio de estado (suspender / baja / rechazar)
    public bool $showStatusModal = false;

    public string $statusAction = '';

    public string $statusReason = '';

    // Modal de cargo manual
    public bool $showChargeModal = false;

    public string $chargeType = 'otro';

    public string $chargeConcept = '';

    public string $chargeAmount = '';

    public string $chargeDueDate = '';

    // Inscripción a actividad
    public ?int $activityId = null;

    public function mount(Member $member): void
    {
        $this->member = $member;
        $this->chargeDueDate = today()->addDays(10)->toDateString();
    }

    public function approve(MemberService $service): void
    {
        $this->authorize('socios.editar');
        $this->attempt(fn () => $service->approve($this->member), 'Socio aprobado. Se le envió el acceso al portal si tiene correo.');
        $this->member->refresh();
    }

    public function reactivate(MemberService $service): void
    {
        $this->authorize('socios.editar');
        $this->attempt(fn () => $service->reactivate($this->member), 'Socio reactivado.');
        $this->member->refresh();
    }

    public function openStatus(string $action): void
    {
        $this->authorize('socios.editar');
        $this->statusAction = $action;
        $this->statusReason = '';
        $this->resetValidation();
        $this->showStatusModal = true;
    }

    public function confirmStatus(MemberService $service): void
    {
        $this->authorize('socios.editar');
        $this->validate(['statusReason' => 'required|string|min:3|max:250'], [], ['statusReason' => 'motivo']);

        $done = $this->attempt(fn () => match ($this->statusAction) {
            'suspend' => $service->suspend($this->member, $this->statusReason),
            'deactivate' => $service->deactivate($this->member, $this->statusReason),
            'reject' => $service->reject($this->member, $this->statusReason),
        }, 'Estado del socio actualizado.');

        if ($done) {
            $this->showStatusModal = false;
            $this->member->refresh();
        }
    }

    public function enablePortal(MemberService $service): void
    {
        $this->authorize('socios.editar');
        $this->attempt(fn () => $service->enablePortalAccess($this->member), 'Acceso al portal habilitado. Se envió un correo para crear la contraseña.');
        $this->member->refresh();
    }

    public function disablePortal(): void
    {
        $this->authorize('socios.editar');
        $this->member->user?->update(['is_active' => false]);
        $this->member->user?->tokens()->delete();
        $this->notify('Acceso al portal deshabilitado.');
        $this->member->refresh();
    }

    public function addCharge(FeeService $fees): void
    {
        $this->authorize('cuotas.gestionar');
        $this->validate([
            'chargeType' => 'required|in:'.implode(',', [FeeType::Other->value, FeeType::Admission->value, FeeType::Membership->value, FeeType::Activity->value]),
            'chargeConcept' => 'required|string|max:200',
            'chargeAmount' => 'required|numeric|min:0.01|max:99999999',
            'chargeDueDate' => 'required|date',
        ], [], ['chargeConcept' => 'concepto', 'chargeAmount' => 'importe', 'chargeDueDate' => 'vencimiento']);

        $done = $this->attempt(fn () => $fees->createCharge(
            $this->member,
            FeeType::from($this->chargeType),
            $this->chargeConcept,
            number_format((float) $this->chargeAmount, 2, '.', ''),
            Carbon::parse($this->chargeDueDate),
        ), 'Cargo agregado a la cuenta del socio.');

        if ($done) {
            $this->reset(['showChargeModal', 'chargeConcept', 'chargeAmount']);
        }
    }

    public function enroll(EnrollmentService $service): void
    {
        $this->authorize('inscripciones.gestionar');
        $this->validate(['activityId' => 'required|exists:activities,id'], [], ['activityId' => 'actividad']);

        if ($this->attempt(fn () => $service->enroll($this->member, Activity::findOrFail($this->activityId)), 'Inscripción registrada.')) {
            $this->activityId = null;
        }
    }

    public function unenroll(int $enrollmentId, EnrollmentService $service): void
    {
        $this->authorize('inscripciones.gestionar');
        $enrollment = $this->member->enrollments()->findOrFail($enrollmentId);
        $this->attempt(fn () => $service->unenroll($enrollment), 'Baja de la actividad registrada.');
    }

    public function delete()
    {
        $this->authorize('socios.eliminar');

        if ($this->member->payments()->exists()) {
            $this->notify('No se puede eliminar un socio con pagos registrados. Usá "Dar de baja".', 'error');

            return null;
        }

        $this->member->delete();
        session()->flash('success', 'Socio eliminado.');

        return $this->redirectRoute('admin.members.index', navigate: true);
    }

    public function render()
    {
        $member = $this->member->load(['category', 'holder', 'dependents', 'user']);

        $data = ['balance' => $member->balance()];

        $data += match ($this->tab) {
            'cuenta' => [
                'fees' => $member->fees()->with('activity')->orderByDesc('due_date')->orderByDesc('id')->limit(60)->get(),
                'payments' => $member->payments()->latest('payment_date')->latest('id')->limit(30)->get(),
            ],
            'actividades' => [
                'enrollments' => $member->enrollments()->with('activity')->orderByRaw("status = 'activa' desc")->latest('start_date')->get(),
                'activities' => Activity::active()->whereNotIn('id', $member->enrollments()->where('status', EnrollmentStatus::Active)->pluck('activity_id'))->get(),
            ],
            'reservas' => ['reservations' => $member->reservations()->with('facility')->latest('date')->limit(30)->get()],
            'planes' => ['subscriptions' => $member->subscriptions()->with('plan')->latest('start_date')->limit(30)->get()],
            'accesos' => ['accessLogs' => $member->accessLogs()->with('checker')->latest('checked_at')->limit(50)->get()],
            'historial' => ['audits' => AuditLog::with('causer')->where('subject_type', $member->getMorphClass())->where('subject_id', $member->id)->latest('id')->limit(50)->get()],
            default => [],
        };

        return view('livewire.admin.members.show', $data)->title($member->fullName());
    }
}
