<?php

namespace Tests;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Notification;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->organization = $this->makeOrganization('Club de Prueba', 'principal', 'club', isDefault: true);
        Organization::setCurrent($this->organization);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    /** Crea una entidad con su configuración inicial (sin cambiar la entidad actual). */
    protected function makeOrganization(string $name, string $slug, string $type = 'club', bool $isDefault = false): Organization
    {
        $organization = Organization::create(['name' => $name, 'slug' => $slug, 'type' => $type, 'is_default' => $isDefault]);
        Organization::runFor($organization, function () use ($name) {
            app(SettingsSeeder::class)->run();
            Setting::set('site.name', $name);
        });

        return $organization;
    }

    /** Usuario del personal con un rol en la entidad actual, o super administrador. */
    protected function staff(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role === null) {
            $user->forceFill(['is_super_admin' => true])->save();
        } else {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function activeMember(array $attributes = [], ?MemberCategory $category = null): Member
    {
        $category ??= MemberCategory::factory()->create(['monthly_fee' => 10000]);

        return Member::factory()->create([
            'member_category_id' => $category->id,
            'status' => MemberStatus::Active,
            'admission_date' => today()->subYear(),
            'birth_date' => today()->subYears(30),
            'member_number' => fake()->unique()->numerify('#####'),
            ...$attributes,
        ]);
    }

    /** Socio con usuario del portal. */
    protected function memberUser(array $attributes = []): User
    {
        $member = $this->activeMember($attributes);
        $user = User::factory()->create(['email' => $member->email]);
        $member->update(['user_id' => $user->id]);

        return $user->fresh();
    }
}
