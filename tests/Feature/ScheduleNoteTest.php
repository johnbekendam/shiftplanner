<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleNoteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_write_the_schedule_note(): void
    {
        $this->put('/settings/shifts/schedule-note', ['note' => 'Hello'])->assertRedirect('/login');
        $this->assertNull(PlanningSettings::current()->shift_schedule_note);
    }

    public function test_manager_cannot_write_the_schedule_note(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/shifts/schedule-note', ['note' => 'Hello'])->assertForbidden();
        $this->assertNull(PlanningSettings::current()->shift_schedule_note);
    }

    public function test_admin_saves_the_schedule_note(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/shifts/schedule-note', ['note' => 'Friday: the evening shift ends at 20:00.'])
            ->assertRedirect();

        $this->assertSame(
            'Friday: the evening shift ends at 20:00.',
            PlanningSettings::current()->fresh()->shift_schedule_note,
        );
    }

    public function test_a_blank_schedule_note_is_stored_as_null(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['shift_schedule_note' => 'old']);

        $this->put('/settings/shifts/schedule-note', ['note' => "   \n  "])->assertRedirect();

        $this->assertNull(PlanningSettings::current()->fresh()->shift_schedule_note);
    }

    public function test_an_over_long_schedule_note_fails_validation(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/shifts/schedule-note', ['note' => str_repeat('a', 20001)])
            ->assertSessionHasErrors('note');
    }

    public function test_settings_index_carries_the_raw_schedule_note(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['shift_schedule_note' => 'Raw **markdown**']);

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->where('scheduleNote', 'Raw **markdown**')
            );
    }

    public function test_settings_index_schedule_note_is_empty_string_when_unset(): void
    {
        $this->actingAsAdmin();

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->where('scheduleNote', ''));
    }

    public function test_schedule_note_html_is_null_when_blank(): void
    {
        $this->assertNull(PlanningSettings::current()->scheduleNoteHtml());
    }

    public function test_schedule_note_html_renders_markdown_and_resolves_the_name(): void
    {
        PlanningSettings::current()->update(['shift_schedule_note' => 'Hi :name, **note**']);

        $html = PlanningSettings::current()->fresh()->scheduleNoteHtml('Jordan');

        $this->assertStringContainsString('<strong>note</strong>', $html);
        $this->assertStringContainsString('Hi Jordan', $html);
        $this->assertStringNotContainsString(':name', $html);
    }

    public function test_employee_edit_payload_carries_the_rendered_schedule_note(): void
    {
        $this->actingAs(User::factory()->create());
        PlanningSettings::current()->update(['shift_schedule_note' => '**Bold** note']);
        $employee = Employee::factory()->create();

        $this->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->where('scheduleNoteHtml', fn ($html) => str_contains((string) $html, '<strong>Bold</strong>'))
            );
    }

    public function test_personal_show_payload_carries_the_rendered_schedule_note(): void
    {
        PlanningSettings::current()->update(['shift_schedule_note' => 'Hi :name']);
        $employee = Employee::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        $token = $employee->personalLink()->create(['token' => 'tok-sched'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->where('scheduleNoteHtml', fn ($html) => str_contains((string) $html, 'Hi Jordan')
                    && ! str_contains((string) $html, 'Jordan Lee'))
            );
    }

    public function test_personal_show_schedule_note_is_null_when_unset(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->personalLink()->create(['token' => 'tok-sched-none'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('scheduleNoteHtml', null));
    }
}
