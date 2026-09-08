<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_from_the_account_page(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_setting_a_first_password_needs_no_current_password(): void
    {
        $user = User::factory()->passwordless()->create();

        $this->actingAs($user)->put('/account/password', [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-pass')]);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'next-pass-please',
            'password_confirmation' => 'next-pass-please',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-pass',
            'password' => 'next-pass-please',
            'password_confirmation' => 'next-pass-please',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('next-pass-please', $user->fresh()->password));
    }

    public function test_a_manager_adds_itself_as_an_employee(): void
    {
        $user = User::factory()->create(['name' => 'Ivy Lane', 'email' => 'ivy@example.com']);

        $this->actingAs($user)->post('/account/employee')->assertRedirect();

        $employee = Employee::sole();
        $this->assertSame('Ivy Lane', $employee->name);
        $this->assertSame('ivy@example.com', $employee->email);
        $this->assertSame($employee->id, $user->fresh()->employee_id);
    }

    public function test_linking_reuses_an_existing_employee_with_the_same_email(): void
    {
        $employee = Employee::factory()->create(['email' => 'ivy@example.com']);
        $user = User::factory()->create(['email' => 'ivy@example.com']);

        $this->actingAs($user)->post('/account/employee')->assertRedirect();

        $this->assertSame(1, Employee::count());
        $this->assertSame($employee->id, $user->fresh()->employee_id);
    }

    public function test_an_admin_cannot_add_itself_as_an_employee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/account/employee')->assertForbidden();
        $this->assertSame(0, Employee::count());
    }

    public function test_a_manager_already_linked_cannot_link_again(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($user)->post('/account/employee')->assertForbidden();
    }
}
