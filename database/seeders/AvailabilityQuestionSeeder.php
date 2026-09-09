<?php

namespace Database\Seeders;

use App\Models\AvailabilityQuestion;
use Illuminate\Database\Seeder;

class AvailabilityQuestionSeeder extends Seeder
{
    private const QUESTIONS = [
        'You can contact me to work on Saturday',
        'You can contact me to work on Sunday',
        'You can contact me to work in week 53.',
    ];

    public function run(): void
    {
        foreach (self::QUESTIONS as $position => $text) {
            AvailabilityQuestion::firstOrCreate(
                ['text' => $text],
                ['position' => $position + 1],
            );
        }
    }
}
