<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Notifications\PaymentReceivedNotification;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PaymentService::class);
    }

    public function test_payment_is_allocated_oldest_first_and_updates_statuses(): void
    {
        $member = $this->activeMember();
        $older = Fee::factory()->create(['member_id' => $member->id, 'amount' => 1000, 'due_date' => today()->subDays(20), 'status' => FeeStatus::Overdue]);
        $newer = Fee::factory()->create(['member_id' => $member->id, 'amount' => 1000, 'due_date' => today()->addDays(5)]);

        $payment = $this->payments->register($member, '1500', PaymentMethod::Cash, [$newer->id, $older->id], receivedBy: $this->staff());

        $this->assertSame(PaymentStatus::Confirmed, $payment->status);
        $this->assertMatchesRegularExpression('/^R-\d{8}$/', $payment->receipt_number);
        $this->assertSame(FeeStatus::Paid, $older->fresh()->status);
        $this->assertSame(FeeStatus::Partial, $newer->fresh()->status);
        $this->assertSame('500.00', (string) $newer->fresh()->paid_amount);
        $this->assertSame('500.00', $member->balance());
        Notification::assertSentTo($member, PaymentReceivedNotification::class);
    }

    public function test_amount_cannot_exceed_balance_of_selected_fees(): void
    {
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id, 'amount' => 1000]);

        $this->expectException(BusinessRuleException::class);
        $this->payments->register($member, '1000.01', PaymentMethod::Cash, [$fee->id]);
    }

    public function test_cannot_pay_fees_of_another_member(): void
    {
        $member = $this->activeMember();
        $other = Fee::factory()->create(['member_id' => $this->activeMember()->id]);

        $this->expectException(BusinessRuleException::class);
        $this->payments->register($member, '100', PaymentMethod::Cash, [$other->id]);
    }

    public function test_cannot_pay_already_paid_fee(): void
    {
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id, 'amount' => 100]);
        $this->payments->register($member, '100', PaymentMethod::Cash, [$fee->id]);

        $this->expectException(BusinessRuleException::class);
        $this->payments->register($member, '100', PaymentMethod::Cash, [$fee->id]);
    }

    public function test_future_dates_and_zero_amounts_are_rejected(): void
    {
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id]);

        try {
            $this->payments->register($member, '0', PaymentMethod::Cash, [$fee->id]);
            $this->fail('Se esperaba una excepción por importe cero');
        } catch (BusinessRuleException) {
        }

        $this->expectException(BusinessRuleException::class);
        $this->payments->register($member, '10', PaymentMethod::Cash, [$fee->id], today()->addDay());
    }

    public function test_cancelling_payment_reverts_allocations(): void
    {
        $member = $this->activeMember();
        $overdue = Fee::factory()->create(['member_id' => $member->id, 'amount' => 1000, 'due_date' => today()->subDays(3), 'status' => FeeStatus::Overdue]);
        $current = Fee::factory()->create(['member_id' => $member->id, 'amount' => 500]);

        $payment = $this->payments->register($member, '1500', PaymentMethod::Transfer, [$overdue->id, $current->id]);
        $this->payments->cancel($payment, 'Transferencia rechazada', $this->staff());

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(FeeStatus::Overdue, $overdue->fresh()->status);
        $this->assertSame(FeeStatus::Pending, $current->fresh()->status);
        $this->assertSame('1500.00', $member->balance());

        $this->expectException(BusinessRuleException::class);
        $this->payments->cancel($payment->fresh(), 'de nuevo');
    }

    public function test_payments_are_audited(): void
    {
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id, 'amount' => 100]);

        $payment = $this->payments->register($member, '100', PaymentMethod::Cash, [$fee->id]);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'payments', 'subject_id' => $payment->id, 'event' => 'created']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'fees', 'subject_id' => $fee->id, 'event' => 'updated']);
    }
}
