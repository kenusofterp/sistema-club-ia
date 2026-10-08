<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReceiptReviewedNotification;
use App\Notifications\ReceiptSubmittedNotification;
use App\Notifications\SettlementSubmittedNotification;
use App\Services\CashCollectionService;
use App\Services\EnrollmentService;
use App\Services\PaymentReceiptService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentCollectionTest extends TestCase
{
    private function fee(int $memberId, int $amount = 20000): Fee
    {
        return Fee::factory()->create(['member_id' => $memberId, 'amount' => $amount]);
    }

    // ---- Comprobantes ----

    public function test_receipt_in_review_mode_waits_for_approval(): void
    {
        $treasurer = $this->staff('tesorero');
        $member = $this->activeMember();
        $fee = $this->fee($member->id);

        $receipt = app(PaymentReceiptService::class)->submit($member, [$fee->id], '20000', today(), 'receipts/x.jpg', 'OP-1');

        $this->assertSame(ReceiptStatus::Pending, $receipt->status);
        $this->assertSame(0, Payment::count());
        Notification::assertSentTo($treasurer, ReceiptSubmittedNotification::class);

        app(PaymentReceiptService::class)->approve($receipt, $treasurer);

        $payment = Payment::sole();
        $this->assertSame(PaymentMethod::Transfer, $payment->method);
        $this->assertSame(FeeStatus::Paid, $fee->fresh()->status);
        $this->assertSame($payment->id, $receipt->fresh()->payment_id);
        Notification::assertSentTo($member, ReceiptReviewedNotification::class);
    }

    public function test_receipt_auto_approve_mode_credits_immediately(): void
    {
        Setting::set('payments.receipts_auto_approve', true);
        $member = $this->activeMember();
        $fee = $this->fee($member->id);

        $receipt = app(PaymentReceiptService::class)->submit($member, [$fee->id], '20000', today(), 'receipts/x.jpg');

        $this->assertSame(ReceiptStatus::Approved, $receipt->status);
        $this->assertSame(FeeStatus::Paid, $fee->fresh()->status);
    }

    public function test_rejected_receipt_keeps_fee_open(): void
    {
        $member = $this->activeMember();
        $fee = $this->fee($member->id);
        $receipt = app(PaymentReceiptService::class)->submit($member, [$fee->id], '20000', today(), 'receipts/x.jpg');

        app(PaymentReceiptService::class)->reject($receipt, 'No llegó la transferencia', $this->staff('tesorero'));

        $this->assertSame(ReceiptStatus::Rejected, $receipt->fresh()->status);
        $this->assertTrue($fee->fresh()->isOpen());
        Notification::assertSentTo($member, ReceiptReviewedNotification::class);
    }

    public function test_receipt_rejects_foreign_fees_excess_amount_and_duplicates(): void
    {
        $member = $this->activeMember();
        $fee = $this->fee($member->id);
        $foreign = $this->fee($this->activeMember()->id);
        $service = app(PaymentReceiptService::class);

        foreach ([
            fn () => $service->submit($member, [$foreign->id], '1000', today(), 'r.jpg'),
            fn () => $service->submit($member, [$fee->id], '25000', today(), 'r.jpg'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Debió rechazarse.');
            } catch (BusinessRuleException) {
            }
        }

        $service->submit($member, [$fee->id], '20000', today(), 'r.jpg');
        $this->expectException(BusinessRuleException::class);
        $service->submit($member, [$fee->id], '20000', today(), 'r2.jpg');
    }

    // ---- Efectivo cobrado por profesores ----

    private function teacherWithStudent(): array
    {
        $teacher = $this->staff('profesor');
        $level = Activity::factory()->create(['instructor_id' => $teacher->id, 'monthly_fee' => 0]);
        $student = $this->activeMember();
        app(EnrollmentService::class)->enroll($student, $level);

        return [$teacher, $student];
    }

    public function test_teacher_collects_cash_only_from_own_students(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();
        $fee = $this->fee($student->id);
        $stranger = $this->activeMember();
        $strangerFee = $this->fee($stranger->id);
        $service = app(CashCollectionService::class);

        $payment = $service->collect($teacher, $student, [$fee->id], '20000');
        $this->assertSame($teacher->id, $payment->received_by);
        $this->assertSame(PaymentMethod::Cash, $payment->method);

        $this->expectException(BusinessRuleException::class);
        $service->collect($teacher, $stranger, [$strangerFee->id], '20000');
    }

    public function test_settlement_flow(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();
        $coordinator = $this->staff('coordinacion');
        $service = app(CashCollectionService::class);
        $service->collect($teacher, $student, [$this->fee($student->id, 15000)->id], '15000');
        $service->collect($teacher, $student, [$this->fee($student->id, 5000)->id], '5000');

        $this->assertEquals(20000, $service->pendingTotal($teacher));
        $settlement = $service->settle($teacher);

        $this->assertEquals(20000, $settlement->amount);
        $this->assertSame(2, $settlement->payments_count);
        $this->assertEquals(0, $service->pendingTotal($teacher));
        Notification::assertSentTo($coordinator, SettlementSubmittedNotification::class);

        // Un pago rendido no se anula.
        try {
            app(PaymentService::class)->cancel(Payment::first(), 'error');
            $this->fail('Debió impedir la anulación.');
        } catch (BusinessRuleException) {
        }

        $service->confirm($settlement, $coordinator);
        $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);
    }

    public function test_owner_mode_has_no_settlement(): void
    {
        Setting::set('payments.cash_requires_settlement', false);
        [$teacher, $student] = $this->teacherWithStudent();
        $service = app(CashCollectionService::class);
        $service->collect($teacher, $student, [$this->fee($student->id)->id], '20000');

        $this->assertFalse($service->requiresSettlement());
        $this->expectException(BusinessRuleException::class);
        $service->settle($teacher);
    }

    public function test_teacher_cannot_collect_when_disabled(): void
    {
        Setting::set('payments.instructors_collect_cash', false);
        [$teacher, $student] = $this->teacherWithStudent();

        $this->expectException(BusinessRuleException::class);
        app(CashCollectionService::class)->collect($teacher, $student, [$this->fee($student->id)->id], '20000');
    }

    public function test_users_with_permission_in_organization(): void
    {
        $treasurer = $this->staff('tesorero');
        $this->staff('profesor');

        $this->assertSame([$treasurer->id], User::withPermissionIn('comprobantes.revisar', $this->organization->id)->pluck('id')->all());
    }
}
