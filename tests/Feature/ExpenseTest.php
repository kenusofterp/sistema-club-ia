<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Expenses\Index as ExpensesIndex;
use App\Models\Expense;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use Livewire\Livewire;
use Tests\TestCase;

/** Gastos (alquiler del salón y otros) y el resultado del mes en el tablero. */
class ExpenseTest extends TestCase
{
    private User $admin;

    private Facility $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->staff('administrador');
        $this->room = Facility::factory()->create(['name' => 'Salón de gimnasia']);
    }

    private function rent(string $amount = '150000.00', ?string $date = null, ?string $period = null): Expense
    {
        return app(ExpenseService::class)->save(null, [
            'expense_date' => $date ?? today()->toDateString(),
            'category' => Expense::RENT,
            'facility_id' => $this->room->id,
            'period' => $period ?? today()->format('Y-m'),
            'amount' => $amount,
            'method' => PaymentMethod::Transfer->value,
        ], $this->admin);
    }

    public function test_registers_the_room_rent_from_the_screen(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ExpensesIndex::class)
            ->call('create')
            ->set('category', Expense::RENT)
            ->set('facility_id', $this->room->id)
            ->set('period', today()->format('Y-m'))
            ->set('amount', '150000')
            ->set('method', 'transferencia')
            ->set('supplier', 'Dueño del salón')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Salón de gimnasia');

        $expense = Expense::sole();
        $this->assertSame('150000.00', (string) $expense->amount);
        $this->assertTrue($expense->expense_date->isToday());
        $this->assertSame(today()->startOfMonth()->toDateString(), $expense->period->toDateString());
        $this->assertSame($this->admin->id, $expense->created_by);
    }

    public function test_rent_status_shows_last_payment_and_whether_this_month_is_paid(): void
    {
        $this->rent('140000.00', today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->format('Y-m'));

        $status = app(ExpenseService::class)->rentStatus()->sole();
        $this->assertFalse($status['paidThisMonth']);
        $this->assertSame('140000.00', (string) $status['last']->amount);

        $this->rent('150000.00');
        $status = app(ExpenseService::class)->rentStatus()->sole();
        $this->assertTrue($status['paidThisMonth']);
        $this->assertSame('150000.00', (string) $status['last']->amount);
    }

    public function test_repeat_prefills_next_month_with_the_same_amount(): void
    {
        $previous = $this->rent('140000.00', today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->format('Y-m'));
        $this->actingAs($this->admin);

        Livewire::test(ExpensesIndex::class)
            ->call('repeat', $previous->id)
            ->assertSet('amount', '140000.00')
            ->assertSet('facility_id', $this->room->id)
            ->assertSet('period', today()->format('Y-m'))
            ->assertSet('expense_date', today()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Expense::count());
    }

    public function test_rejects_future_dates_and_zero_amounts(): void
    {
        try {
            $this->rent('150000.00', today()->addDay()->toDateString());
            $this->fail('Se esperaba un error por fecha futura.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('futura', $e->getMessage());
        }

        $this->expectException(BusinessRuleException::class);
        $this->rent('0');
    }

    public function test_permissions(): void
    {
        $this->actingAs($this->staff('tesorero'))->get(route('admin.expenses'))->assertOk();
        $this->actingAs($this->staff('profesor'))->get(route('admin.expenses'))->assertForbidden();
    }

    public function test_dashboard_shows_collected_spent_result_and_debtors(): void
    {
        $payer = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $payer->id, 'amount' => 200000]);
        app(PaymentService::class)->register($payer, '200000.00', PaymentMethod::Cash, [$fee->id], receivedBy: $this->admin);
        $this->rent('150000.00');

        $debtor = $this->activeMember(['first_name' => 'Rocío', 'last_name' => 'Debe']);
        Fee::factory()->overdue()->create(['member_id' => $debtor->id, 'amount' => 30000]);

        // Un gasto de otra entidad no cuenta.
        $other = $this->makeOrganization('Club Norte', 'norte');
        Organization::runFor($other, fn () => Expense::create([
            'expense_date' => today(), 'category' => 'Otros', 'amount' => 999999, 'method' => 'efectivo',
        ]));

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Gastado este mes')
            ->assertSee(money('150000'))
            ->assertSee(money('50000'))
            ->assertSee('Deudores')
            ->assertSee($debtor->fullName())
            ->assertSee(money('30000'))
            ->assertDontSee(money('999999'));
    }
}
