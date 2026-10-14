<?php

namespace App\Livewire\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AccessLog;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Refund;
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

        // Dinero que entró: los pagos con saldo a favor no son plata nueva (ya se contó al cobrarse).
        $income = fn () => Payment::confirmed()->where('method', '!=', PaymentMethod::AccountCredit);

        $incomeThisMonth = (string) $income()->whereDate('payment_date', '>=', $monthStart)->sum('amount');
        $incomePrevMonth = (string) $income()
            ->whereBetween('payment_date', [$prevStart, $prevStart->copy()->day(min(today()->day, $prevStart->daysInMonth))])
            ->sum('amount');
        $expensesThisMonth = (string) Expense::whereDate('expense_date', '>=', $monthStart)->sum('amount');
        $refundsThisMonth = (string) Refund::whereDate('refund_date', '>=', $monthStart)->sum('amount');

        $overdue = Fee::where('status', FeeStatus::Overdue)
            ->selectRaw('COALESCE(SUM(amount + surcharge - paid_amount), 0) as total, COUNT(DISTINCT member_id) as members')
            ->first();

        // Recaudación y gastos de los últimos 12 meses.
        $from = $monthStart->copy()->subMonthsNoOverflow(11);
        $incomeByMonth = $income()
            ->whereDate('payment_date', '>=', $from)
            ->selectRaw("to_char(payment_date, 'YYYY-MM') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');
        $expensesByMonth = Expense::whereDate('expense_date', '>=', $from)
            ->selectRaw("to_char(expense_date, 'YYYY-MM') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $chart = collect(range(0, 11))->map(function ($i) use ($from, $incomeByMonth, $expensesByMonth) {
            $month = $from->copy()->addMonthsNoOverflow($i);

            return [
                'label' => ucfirst($month->translatedFormat('M')),
                'full' => ucfirst($month->translatedFormat('F Y')),
                'value' => (float) ($incomeByMonth[$month->format('Y-m')] ?? 0),
                'expenses' => (float) ($expensesByMonth[$month->format('Y-m')] ?? 0),
            ];
        });

        return [
            'activeMembers' => Member::active()->count(),
            'pendingMembers' => Member::where('status', MemberStatus::Pending)->count(),
            'incomeThisMonth' => $incomeThisMonth,
            'incomeDelta' => bccomp($incomePrevMonth, '0', 2) > 0
                ? round(((float) $incomeThisMonth - (float) $incomePrevMonth) / (float) $incomePrevMonth * 100)
                : null,
            'expensesThisMonth' => $expensesThisMonth,
            'refundsThisMonth' => $refundsThisMonth,
            'resultThisMonth' => bcsub(bcsub($incomeThisMonth, $expensesThisMonth, 2), $refundsThisMonth, 2),
            'expensesByCategory' => Expense::whereDate('expense_date', '>=', $monthStart)
                ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
            'overdueTotal' => (string) $overdue->total,
            // Quiénes deben (cuotas y cargos vencidos), de mayor a menor deuda.
            'debtors' => Member::query()
                ->join('fees', 'fees.member_id', '=', 'members.id')
                ->where('fees.status', FeeStatus::Overdue)
                ->select('members.*', DB::raw('SUM(fees.amount + fees.surcharge - fees.paid_amount) as debt'), DB::raw('MIN(fees.due_date) as oldest_due'))
                ->groupBy('members.id')
                ->orderByDesc('debt')
                ->limit(8)
                ->get(),
            'overdueMembers' => (int) $overdue->members,
            'activeEnrollments' => Enrollment::where('status', EnrollmentStatus::Active)->count(),
            'todayReservations' => Reservation::confirmed()->whereDate('date', today())->with(['facility', 'member'])->orderBy('start_time')->get(),
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('value'), $chart->max('expenses')),
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
