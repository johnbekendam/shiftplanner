<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => null,
            'recipient_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'status' => 'draft',
            'sent_at' => null,
        ];
    }

    public function outbox(): static
    {
        return $this->state(fn () => ['status' => 'outbox']);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent', 'sent_at' => now()]);
    }
}
