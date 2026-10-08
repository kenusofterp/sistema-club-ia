<?php

namespace App\Livewire\Portal;

use App\Enums\LessonStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Lesson;
use App\Services\LevelLessonService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Clases del socio: próximas (con aviso "no voy"), suspendidas, historial y packs vigentes. */
#[Layout('layouts.portal')]
#[Title('Mis clases')]
class Lessons extends Component
{
    use ForCurrentMember, InteractsWithUi;

    public ?int $noticeLessonId = null;

    public string $reason = '';

    public function askAbsence(int $lessonId): void
    {
        $this->findLesson($lessonId);
        $this->noticeLessonId = $lessonId;
        $this->reason = '';
    }

    public function confirmAbsence(LevelLessonService $service): void
    {
        $this->validate(['reason' => 'nullable|string|max:200'], [], ['reason' => 'motivo']);
        $lesson = $this->findLesson($this->noticeLessonId);

        if ($this->attempt(fn () => $service->notifyAbsence($lesson, $this->member(), $this->reason), 'Listo, le avisamos a tu profesor.')) {
            $this->noticeLessonId = null;
        }
    }

    public function withdrawAbsence(int $lessonId, LevelLessonService $service): void
    {
        $this->attempt(fn () => $service->withdrawAbsence($this->findLesson($lessonId), $this->member()), '¡Te esperamos en clase!');
    }

    private function findLesson(?int $id): Lesson
    {
        return $this->member()->lessons()->findOrFail($id);
    }

    public function render()
    {
        $member = $this->member();
        $lessons = fn () => $member->lessons()->with(['facility', 'instructor', 'activity.schedules']);

        return view('livewire.portal.lessons', [
            'upcoming' => $lessons()
                ->whereIn('lessons.status', [LessonStatus::Scheduled, LessonStatus::Cancelled])
                ->where(fn ($q) => $q->whereDate('date', '>', today())
                    ->orWhere(fn ($t) => $t->whereDate('date', today())->where('end_time', '>', now()->format('H:i:s'))))
                ->orderBy('date')->orderBy('start_time')
                ->limit(20)->get(),
            'history' => $lessons()
                ->where('lessons.status', LessonStatus::Given)
                ->orderByDesc('date')->orderByDesc('start_time')
                ->limit(30)->get(),
            'packs' => $member->subscriptions()->current()
                ->whereHas('plan', fn ($q) => $q->whereNotNull('instructor_id'))
                ->with('plan.instructor')->get(),
            'canNotify' => (bool) setting('lessons.absence_notice_enabled', true),
        ]);
    }
}
