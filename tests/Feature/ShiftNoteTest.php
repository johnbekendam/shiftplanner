<?php

namespace Tests\Feature;

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

    public function test_note_html_keeps_raw_html(): void
    {
        PlanningSettings::current()->update(['shift_note' => 'Call <span class="x">Bob</span> first.']);

        $this->assertStringContainsString('<span class="x">Bob</span>', PlanningSettings::current()->fresh()->shiftNoteHtml());
    }

    public function test_note_html_is_null_when_blank(): void
    {
        $this->assertNull(PlanningSettings::current()->shiftNoteHtml());
    }
}
