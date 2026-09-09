<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_accessor_joins_first_and_last(): void
    {
        $employee = Employee::factory()->create([
            'first_name' => 'Anna',
            'last_name' => 'de Vries',
        ]);

        $this->assertSame('Anna de Vries', $employee->name);
    }

    public function test_search_scope_matches_first_name_last_name_or_email(): void
    {
        $anna = Employee::factory()->create([
            'first_name' => 'Anna', 'last_name' => 'Jansen', 'email' => 'aj@example.com',
        ]);
        $bram = Employee::factory()->create([
            'first_name' => 'Bram', 'last_name' => 'de Vries', 'email' => 'bram@example.com',
        ]);
        $cato = Employee::factory()->create([
            'first_name' => 'Cato', 'last_name' => 'Smit', 'email' => 'find-me@example.com',
        ]);

        $this->assertSame([$anna->id], Employee::search('Anna')->pluck('id')->all());
        $this->assertSame([$bram->id], Employee::search('Vries')->pluck('id')->all());
        $this->assertSame([$cato->id], Employee::search('find-me')->pluck('id')->all());
    }
}
