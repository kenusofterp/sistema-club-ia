<?php

namespace App\Livewire\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AccessLog;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Tablero')]
class Dashboard extends Component
{
    public function render()
    {
        $canSee = auth()->user()->can('dashboard.ver');

        return view('livewire.admin.dashboard', $canSee ? $this->metrics() : [])->with('canSee', $canSee);
    }

    private function metrics(): array
    {
        $monthStart = today()->startOfMonth();
        $prevStart = $monthStart->copy()->subMonthNoOverflow();

        $incomeThisMonth = (string) Payment::confirmed()->whereDate('payment_date', '>=', $monthStart)->sum('amount');
        $incomePrevMonth = (string) Payment::confirmed()
            ->whereBetween('payment_date', [$prevStart, $prevStart->copy()->day(min(today()->day, $prevStart->daysInMonth))])
            ->sum('amount');

        $overdue = Fee::where('status', FeeStatus::Overdue)
            ->selectRaw('COALESCE(SUM(amount + surcharge - paid_amount), 0) as total, COUNT(DISTINCT member_id) as members')
            ->first();

        // Recaudación de los últimos 12 meses.
        $from = $monthStart->copy()->subMonthsNoOverflow(11);
        $byMonth = Payment::confirmed()
            ->whereDate('payment_date', '>=', $from)
            ->selectRaw("to_char(payment_date, 'YYYY-MM') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $chart = collect(range(0, 11))->map(function ($i) use ($from, $byMonth) {
            $month = $from->copy()->addMonthsNoOverflow($i);

            return [
                'label' => ucfirst($month->translatedFormat('M')),
                'full' => ucfirst($month->translatedFormat('F Y')),
                'value' => (float) ($byMonth[$month->format('Y-m')] ?? 0),
            ];
        });

        return [
            'activeMembers' => Member::active()->count(),
            'pendingMembers' => Member::where('status', MemberStatus::Pending)->count(),
            'incomeThisMonth' => $incomeThisMonth,
            'incomeDelta' => bccomp($incomePrevMonth, '0', 2) > 0
                ? round(((float) $incomeThisMonth - (float) $incomePrevMonth) / (float) $incomePrevMonth * 100)
                : null,
            'overdueTotal' => (string) $overdue->total,
            'overdueMembers' => (int) $overdue->members,
            'activeEnrollments' => Enrollment::where('status', EnrollmentStatus::Active)->count(),
            'todayReservations' => Reservation::confirmed()->whereDate('date', today())->with(['facility', 'member'])->orderBy('start_time')->get(),
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('value')),
            'latestPayments' => Payment::with('member')->where('status', PaymentStatus::Confirmed)->latest('id')->limit(6)->get(),
            'pendingList' => Member::with('category')->where('status', MemberStatus::Pending)->latest()->limit(5)->get(),
            'unreadMessages' => ContactMessage::unread()->count(),
            'gymStats' => uses_gym() ? [
                'active' => Subscription::current()->count(),
                'pending' => Subscription::where('status', SubscriptionStatus::Pending)->count(),
                'expiring' => Subscription::where('status', SubscriptionStatus::Active)->whereBetween('end_date', [today(), today()->addDays(7)])->count(),
                'todayVisits' => AccessLog::whereDate('checked_at', today())->where('result', 'permitido')->distinct('member_id')->count('member_id'),
            ] : null,
            'membersByCategory' => Member::active()
                ->join('member_categories', 'member_categories.id', '=', 'members.member_category_id')
                ->select('member_categories.name', DB::raw('COUNT(*) as total'))
                ->groupBy('member_categories.name')
                ->orderByDesc('total')
                ->get(),
        ];
    }
}
