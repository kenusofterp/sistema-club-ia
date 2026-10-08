<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Facility;
use App\Models\Fee;
use Database\Seeders\SiteContentSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    public function test_public_endpoints(): void
    {
        $this->seed(SiteContentSeeder::class);
        Activity::factory()->create(['name' => 'Hockey']);

        $this->getJson('/api/v1/site')->assertOk()->assertJsonStructure(['name', 'hero', 'news', 'contact']);
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonPath('data.0.name', 'Hockey');
    }

    public function test_token_login_and_me(): void
    {
        $user = $this->memberUser();
        Fee::factory()->create(['member_id' => $user->member->id, 'amount' => 2500]);

        $token = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])
            ->assertCreated()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.member.member_number', $user->member->member_number)
            ->assertJsonPath('data.balance', '2500.00');

        $this->withToken($token)->getJson('/api/v1/me/fees?open=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_bad_credentials_and_unauthenticated(): void
    {
        $user = $this->memberUser();

        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'mal', 'device_name' => 'x'])->assertUnprocessable();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_business_rules_return_422(): void
    {
        $user = $this->memberUser();
        $activity = Activity::factory()->create(['capacity' => 1]);
        $this->activeMember()->enrollments()->create(['activity_id' => $activity->id, 'status' => 'activa', 'start_date' => today()]);
        Sanctum::actingAs($user, ['member']);

        $this->postJson('/api/v1/me/enrollments', ['activity_id' => $activity->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La actividad no tiene cupos disponibles.');
    }

    public function test_booking_via_api(): void
    {
        $user = $this->memberUser();
        $facility = Facility::factory()->create();
        Sanctum::actingAs($user, ['member']);

        $date = today()->addDays(3)->toDateString();
        $this->getJson("/api/v1/facilities/{$facility->id}/availability?date={$date}")->assertOk()->assertJsonPath('data.2.available', true);
        $id = $this->postJson('/api/v1/me/reservations', ['facility_id' => $facility->id, 'date' => $date, 'start_time' => '10:00'])
            ->assertCreated()
            ->json('data.id');
        $this->deleteJson("/api/v1/me/reservations/{$id}")->assertOk();
    }

    public function test_access_check_requires_staff_token(): void
    {
        $member = $this->activeMember();

        Sanctum::actingAs($this->memberUser(), ['member']);
        $this->postJson('/api/v1/access/check', ['code' => $member->member_number])->assertForbidden();

        Sanctum::actingAs($this->staff('recepcion'), ['*']);
        $this->postJson('/api/v1/access/check', ['code' => $member->member_number])->assertOk()->assertJsonPath('result', 'permitido');
    }
}
