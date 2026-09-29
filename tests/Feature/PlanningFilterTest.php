<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_hidden_ids_for_the_current_user(): void
    {
        $user = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($user)
            ->putJson('/planning/filter', [
                'hidden_workcenter_ids' => [3, 7],
                'hidden_shift_ids' => [],
            ])
            ->assertNoContent();

        $this->assertSame(
            ['hidden_workcenter_ids' => [3, 7], 'hidden_shift_ids' => []],
            $user->fresh()->planning_filter,
        );
        $this->assertNull($other->fresh()->planning_filter);
    }

    public function test_it_rejects_ids_that_are_not_integers(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->putJson('/planning/filter', [
                'hidden_workcenter_ids' => ['abc'],
                'hidden_shift_ids' => 'x',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hidden_workcenter_ids.0', 'hidden_shift_ids']);

        $this->assertNull($user->fresh()->planning_filter);
    }

    public function test_guests_cannot_store_a_filter(): void
    {
        $this->putJson('/planning/filter', [
            'hidden_workcenter_ids' => [],
            'hidden_shift_ids' => [],
        ])->assertUnauthorized();
    }

    public function test_the_planning_page_sends_the_hidden_ids(): void
    {
        $user = User::factory()->admin()->create(['planning_filter' => [
            'hidden_workcenter_ids' => [3],
            'hidden_shift_ids' => [5, 6],
        ]]);

        $this->actingAs($user)->get('/planning')
            ->assertInertia(fn ($page) => $page
                ->where('hiddenWorkcenterIds', [3])
                ->where('hiddenShiftIds', [5, 6]));
    }

    public function test_the_planning_page_hides_nothing_without_a_stored_filter(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/planning')
            ->assertInertia(fn ($page) => $page
                ->where('hiddenWorkcenterIds', [])
                ->where('hiddenShiftIds', []));
    }
}
