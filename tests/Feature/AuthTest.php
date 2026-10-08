<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use Database\Seeders\SuperAdminSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_configured_super_admin_can_log_in_and_lands_on_dashboard(): void
    {
        config(['club.superadmin.email' => 'admin.club@sidkenu.com', 'club.superadmin.password' => '1Monitorfeo$77']);
        $this->seed(SuperAdminSeeder::class);

        Livewire::test(Login::class)
            ->set('email', 'admin.club@sidkenu.com')
            ->set('password', '1Monitorfeo$77')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Tablero');
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = $this->staff();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'failed']);
    }

    public function test_member_lands_on_portal(): void
    {
        $user = $this->memberUser();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->staff();
        $user->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    }

    public function test_member_cannot_enter_admin_panel(): void
    {
        $this->actingAs($this->memberUser())->get(route('admin.members.index'))->assertRedirect(route('portal.dashboard'));
    }

    public function test_staff_without_member_cannot_enter_portal(): void
    {
        $this->actingAs($this->staff('tesorero'))->get(route('portal.dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = $this->staff();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->staff())->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_is_audited(): void
    {
        $user = $this->staff();

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login', 'causer_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
