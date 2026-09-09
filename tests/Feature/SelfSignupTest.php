<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfSignupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Step 1: nullable messages.user_id ────────────────────────────────

    public function test_a_message_can_be_stored_without_a_composing_user(): void
    {
        $message = Message::factory()->create(['user_id' => null]);

        $this->assertNull($message->fresh()->user_id);
    }

    public function test_mailbox_list_labels_a_userless_message_as_self_signup(): void
    {
        $this->admin();
        Message::factory()->sent()->create(['user_id' => null]);

        $this->get('/mailbox?tab=sent')->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('messages.data.0.composed_by', 'Self-signup')
        );
    }
}
