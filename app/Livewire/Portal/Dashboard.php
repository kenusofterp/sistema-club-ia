<?php

namespace App\Livewire\Portal;

use App\Enums\LessonStatus;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Inicio')]
class Dashboard extends Component
{
    use ForCurrentMember;

    public function render()
    {
        $member = $this->member()->load('category');

        return view('livewire.portal.dashboard', [
            'member' => $member,
            'balance' => $member->balance(),
            'overdue' => $member->overdueFeesCount(),
            'nextFee' => $member->openFees()->orderBy('due_date')->first(),
            'activities' => $member->activities()->with('schedules')->get(),
            'reservations' => $member->reservations()->confirmed()->upcoming()->with('facility')->limit(3)->get(),
            'announcements' => Announcement::current()->limit(5)->get(),
            'subscription' => uses_gym() ? $member->subscriptions()->current()->with('plan')->orderBy('end_date')->first() : null,
            'pendingPlan' => uses_gym() && $member->subscriptions()->where('status', 'pendiente')->exists(),
            'nextLesson' => $member->lessons()->with(['activity', 'facility', 'instructor'])
                ->where('lessons.status', LessonStatus::Scheduled)
                ->where(fn ($q) => $q->whereDate('date', '>', today())
                    ->orWhere(fn ($t) => $t->whereDate('date', today())->where('end_time', '>', now()->format('H:i:s'))))
                ->orderBy('date')->orderBy('start_time')->first(),
            'suspended' => $member->lessons()->with('activity')
                ->where('lessons.status', LessonStatus::Cancelled)
                ->whereBetween('date', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->orderBy('date')->get(),
        ]);
    }
}
