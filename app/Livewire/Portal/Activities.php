<?php

namespace App\Livewire\Portal;

use App\Enums\EnrollmentStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Activity;
use App\Services\EnrollmentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Actividades')]
class Activities extends Component
{
    use ForCurrentMember, InteractsWithUi;

    public function enroll(int $activityId, EnrollmentService $service): void
    {
        $activity = Activity::visible()->findOrFail($activityId);
        $this->attempt(fn () => $service->enroll($this->member(), $activity), "¡Te inscribiste en {$activity->name}!");
    }

    public function unenroll(int $enrollmentId, EnrollmentService $service): void
    {
        $enrollment = $this->member()->enrollments()->findOrFail($enrollmentId);
        $this->attempt(fn () => $service->unenroll($enrollment, 'Baja solicitada desde el portal'), 'Te diste de baja de la actividad.');
    }

    public function render()
    {
        $member = $this->member();
        $mine = $member->enrollments()->where('status', EnrollmentStatus::Active)->pluck('id', 'activity_id');

        return view('livewire.portal.activities', [
            'member' => $member,
            'activities' => Activity::visible()->with(['schedules', 'plans'])->withCount('activeEnrollments')->get(),
            'mine' => $mine,
        ]);
    }
}
