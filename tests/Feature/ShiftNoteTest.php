<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftNoteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_write_the_note(): void
    {
        $this->put('/settings/shifts/note', ['note' => 'Hello'])->assertRedirect('/login');
        $this->assertNull(PlanningSettings::current()->shift_note);
    }

    public function test_manager_cannot_write_the_note(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/shifts/note', ['note' => 'Hello'])->assertForbidden();
        $this->assertNull(PlanningSettings::current()->shift_note);
    }

    public function test_admin_saves_the_note(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/shifts/note', ['note' => "# Allowances\n\nSome text."])
            ->assertRedirect();

        $this->assertSame("# Allowances\n\nSome text.", PlanningSettings::current()->fresh()->shift_note);
    }

    public function test_a_blank_note_is_stored_as_null(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['shift_note' => 'old']);

        $this->put('/settings/shifts/note', ['note' => "   \n  "])->assertRedirect();

        $this->assertNull(PlanningSettings::current()->fresh()->shift_note);
    }

    public function test_settings_index_carries_the_raw_note(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['shift_note' => 'Raw **markdown**']);

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->where('shiftNote', 'Raw **markdown**')
            );
    }

    public function test_settings_index_note_is_empty_string_when_unset(): void
    {
        $this->actingAsAdmin();

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->where('shiftNote', ''));
    }

    public function test_note_html_renders_a_github_flavoured_table(): void
    {
        PlanningSettings::current()->update([
            'shift_note' => "| Shift | Allowance |\n| --- | --- |\n| Early | 10% |",
        ]);

        $html = PlanningSettings::current()->fresh()->shiftNoteHtml();

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<td>Early</td>', $html);
    }

    public function test_note_html_renders_a_button_with_custom_text_and_target(): void
    {
        PlanningSettings::current()->update([
            'shift_note' => ':button[Open the schedule](https://example.test/schedule)',
        ]);

        $html = PlanningSettings::current()->fresh()->shiftNoteHtml();

        $this->assertStringContainsString('data-shift-note-button', $html);
        $this->assertStringContainsString('href="https://example.test/schedule"', $html);
        $this->assertStringContainsString('Open the schedule', $html);
        $this->assertStringNotContainsString(':button', $html);
    }

    public function test_note_html_renders_a_spacer_marker(): void
    {
        PlanningSettings::current()->update(['shift_note' => "First paragraph.\n\n:---\n\nSecond paragraph."]);

        $html = PlanningSettings::current()->fresh()->shiftNoteHtml();

        $this->assertStringContainsString('data-note-spacer', $html);
        $this->assertStringNotContainsString(':---', $html);
    }

    public function test_note_html_stacks_repeated_spacer_markers(): void
    {
        PlanningSettings::current()->update(['shift_note' => "First paragraph.\n\n:---\n\n:---\n\nSecond paragraph."]);

        $html = PlanningSettings::current()->fresh()->shiftNoteHtml();

        $this->assertSame(2, substr_count($html, 'data-note-spacer'));
    }

    public function test_note_html_keeps_raw_html(): void
    {
        PlanningSettings::current()->update(['shift_note' => 'Call <span class="x">Bob</span> first.']);

        $this->assertStringContainsString('<span class="x">Bob</span>', PlanningSettings::current()->fresh()->shiftNoteHtml());
    }

    public function test_note_html_is_null_when_blank(): void
    {
        $this->assertNull(PlanningSettings::current()->shiftNoteHtml());
    }

    public function test_note_html_replaces_the_name_placeholder(): void
    {
        PlanningSettings::current()->update(['shift_note' => 'Hello :name, please read this.']);

        $html = PlanningSettings::current()->fresh()->shiftNoteHtml('Jordan Lee');

        $this->assertStringContainsString('Hello Jordan Lee, please read this.', $html);
        $this->assertStringNotContainsString(':name', $html);
    }

    public function test_note_html_keeps_the_name_placeholder_when_no_name_is_given(): void
    {
        PlanningSettings::current()->update(['shift_note' => 'Hello :name.']);

        $this->assertStringContainsString(':name', PlanningSettings::current()->fresh()->shiftNoteHtml());
    }

    public function test_employee_edit_payload_carries_the_rendered_note(): void
    {
        $this->actingAs(User::factory()->create());
        PlanningSettings::current()->update(['shift_note' => '**Bold** note']);
        $employee = Employee::factory()->create();

        $this->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, '<strong>Bold</strong>'))
            );
    }

    public function test_employee_edit_payload_carries_a_shift_note_button(): void
    {
        $this->actingAs(User::factory()->create());
        PlanningSettings::current()->update([
            'shift_note' => ':button[Open the schedule](https://example.test/schedule)',
        ]);
        $employee = Employee::factory()->create();

        $this->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, 'data-shift-note-button'))
            );
    }

    public function test_personal_show_payload_carries_the_rendered_note(): void
    {
        PlanningSettings::current()->update(['shift_note' => '**Bold** note']);
        $employee = Employee::factory()->create();
        $token = $employee->personalLink()->create(['token' => 'tok-note'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, '<strong>Bold</strong>'))
            );
    }

    public function test_personal_show_payload_carries_a_shift_note_button(): void
    {
        PlanningSettings::current()->update([
            'shift_note' => ':button[Open the schedule](https://example.test/schedule)',
        ]);
        $employee = Employee::factory()->create();
        $token = $employee->personalLink()->create(['token' => 'tok-button'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, 'data-shift-note-button'))
            );
    }

    public function test_personal_show_payload_resolves_the_name_placeholder_to_the_first_name(): void
    {
        PlanningSettings::current()->update(['shift_note' => 'Hi :name']);
        $employee = Employee::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        $token = $employee->personalLink()->create(['token' => 'tok-name'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, 'Hi Jordan')
                    && ! str_contains((string) $html, 'Jordan Lee'))
            );
    }

    public function test_employee_edit_payload_resolves_the_name_placeholder_to_the_first_name(): void
    {
        $this->actingAs(User::factory()->create());
        PlanningSettings::current()->update(['shift_note' => 'Hi :name']);
        $employee = Employee::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);

        $this->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shiftNoteHtml', fn ($html) => str_contains((string) $html, 'Hi Jordan')
                    && ! str_contains((string) $html, 'Jordan Lee'))
            );
    }

    public function test_personal_show_note_is_null_when_unset(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->personalLink()->create(['token' => 'tok-none'])->token;

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('shiftNoteHtml', null));
    }
}
