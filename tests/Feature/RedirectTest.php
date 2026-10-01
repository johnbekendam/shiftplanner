<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_schedule_redirects_to_demand(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/schedule')->assertRedirect('/demand');
    }
}
