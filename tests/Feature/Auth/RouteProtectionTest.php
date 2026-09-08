<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_protected_page(): void
    {
        $this->get('/mailbox')->assertRedirect('/login');
    }

    public function test_theme_builder_is_now_behind_auth(): void
    {
        $this->get('/theme-builder')->assertRedirect('/login');
    }

    public function test_employees_is_behind_auth(): void
    {
        $this->get('/employees')->assertRedirect('/login');
    }
}
