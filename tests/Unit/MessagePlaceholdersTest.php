<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Services\MessagePlaceholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_name_and_link_for_an_employee(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Alice']);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $employee);

        $this->assertSame('Hi Alice', $result['subject']);
        $this->assertStringContainsString('/personal/', $result['body']);
        $this->assertSame([], $result['unresolved']);
    }

    public function test_resolves_name_and_link_for_a_user_with_a_linked_employee(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->admin()->create(['name' => 'Bob', 'employee_id' => $employee->id]);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $user);

        $this->assertSame('Hi Bob', $result['subject']);
        $this->assertStringContainsString('/personal/', $result['body']);
        $this->assertSame([], $result['unresolved']);
    }

    public function test_reports_link_unresolved_for_a_user_without_a_linked_employee(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Bob', 'employee_id' => null]);

        $result = app(MessagePlaceholders::class)->resolve('Hi :name', 'Open :link', $user);

        $this->assertSame('Hi Bob', $result['subject']);
        $this->assertStringContainsString(':link', $result['body']);
        $this->assertSame([':link'], $result['unresolved']);
    }

    public function test_leaves_non_registry_tokens_untouched(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Alice']);

        $result = app(MessagePlaceholders::class)->resolve(':not_a_token', 'Hi :name', $employee);

        $this->assertSame(':not_a_token', $result['subject']);
        $this->assertSame([], $result['unresolved']);
    }
}
