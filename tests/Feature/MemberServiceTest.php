<?php

namespace Tests\Feature;

use App\Enums\AccessResult;
use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Enums\MemberStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Setting;
use App\Notifications\WelcomeMemberNotification;
use App\Services\AccessService;
use App\Services\EnrollmentService;
use App\Services\MemberService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemberServiceTest extends TestCase
{
    public function test_approving_assigns_number_charges_admission_and_invites_to_portal(): void
    {
        $category = MemberCategory::factory()->create(['admission_fee' => 15000]);
        $member = Member::factory()->pending()->create(['member_category_id' => $category->id, 'email' => 'nuevo@example.com']);

        app(MemberService::class)->approve($member);
        $member->refresh();

        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertNotNull($member->member_number);
        $this->assertTrue($member->admission_date->isToday());
        $this->assertDatabaseHas('fees', ['member_id' => $member->id, 'type' => FeeType::Admission->value, 'amount' => 15000]);
        $this->assertNotNull($member->user);
        Notification::assertSentTo($member->user, WelcomeMemberNotification::class);
    }

    public function test_member_numbers_are_sequential_with_prefix(): void
    {
        Setting::set('club.member_number_prefix', 'S-');
        $service = app(MemberService::class);
        $category = MemberCategory::factory()->create();

        $a = Member::factory()->pending()->create(['member_category_id' => $category->id, 'email' => null]);
        $b = Member::factory()->pending()->create(['member_category_id' => $category->id, 'email' => null]);
        $service->approve($a);
        $service->approve($b);

        $this->assertSame('S-00001', $a->fresh()->member_number);
        $this->assertSame('S-00002', $b->fresh()->member_number);
    }

    public function test_cannot_approve_an_active_member(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(MemberService::class)->approve($this->activeMember());
    }

    public function test_deactivation_ends_enrollments_and_blocks_portal(): void
    {
        $user = $this->memberUser();
        $member = $user->member;
        app(EnrollmentService::class)->enroll($member, Activity::factory()->create());

        app(MemberService::class)->deactivate($member, 'Renuncia');

        $this->assertSame(MemberStatus::Inactive, $member->fresh()->status);
        $this->assertSame(0, $member->enrollments()->where('status', EnrollmentStatus::Active)->count());
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_category_age_rules_on_create(): void
    {
        $category = MemberCategory::factory()->create(['min_age' => 18]);

        $this->expectException(BusinessRuleException::class);
        app(MemberService::class)->create([
            'first_name' => 'Niño', 'last_name' => 'Test', 'document_type' => 'DNI', 'document_number' => '55000111',
            'birth_date' => today()->subYears(10)->toDateString(), 'member_category_id' => $category->id,
        ]);
    }

    public function test_access_control_rules(): void
    {
        Setting::set('club.max_overdue_for_access', 2);
        $access = app(AccessService::class);
        $member = $this->activeMember();

        $this->assertSame(AccessResult::Granted, $access->register($member)->result);
        $this->assertSame($member->id, $access->findMember($member->verificationUrl())->id);
        $this->assertSame($member->id, $access->findMember($member->member_number)->id);

        Fee::factory()->overdue()->count(2)->create(['member_id' => $member->id]);
        $this->assertSame(AccessResult::Denied, $access->register($member)->result);

        $member->update(['status' => MemberStatus::Suspended]);
        $this->assertSame(AccessResult::Denied, $access->evaluate($member)['result']);
        $this->assertSame(2, $member->accessLogs()->count()); // evaluate() no registra, register() sí
    }

    public function test_member_changes_are_audited_with_old_and_new_values(): void
    {
        $member = $this->activeMember(['phone' => '111']);
        $member->update(['phone' => '222']);

        $log = \Spatie\Activitylog\Models\Activity::where('subject_type', $member->getMorphClass())->where('subject_id', $member->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('111', $log->attribute_changes['old']['phone']);
        $this->assertSame('222', $log->attribute_changes['attributes']['phone']);
    }
}
