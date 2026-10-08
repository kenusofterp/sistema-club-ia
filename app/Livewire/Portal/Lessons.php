<?php

namespace App\Livewire\Portal;

use App\Enums\LessonStatus;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Clases del socio con los profesores: próximas, historial y packs vigentes. */
#[Layout('layouts.portal')]
#[Title('Mis clases')]
class Lessons extends Component
{
    use ForCurrentMember;

    public function render()
    {
        $member = $this->member();
        $lessons = fn () => $member->lessons()->with(['facility', 'instructor']);

        return view('livewire.portal.lessons', [
            'upcoming' => $lessons()
                ->where('lessons.status', LessonStatus::Scheduled)
                ->whereDate('date', '>=', today())
                ->orderBy('date')->orderBy('start_time')
                ->limit(30)->get(),
            'history' => $lessons()
                ->where(fn ($q) => $q->whereDate('date', '<', today())->orWhere('lessons.status', '!=', LessonStatus::Scheduled))
                ->orderByDesc('date')->orderByDesc('start_time')
                ->limit(30)->get(),
            'packs' => $member->subscriptions()->current()
                ->whereHas('plan', fn ($q) => $q->whereNotNull('instructor_id'))
                ->with('plan.instructor')->get(),
        ]);
    }
}
