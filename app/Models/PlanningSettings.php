<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlanningSettings extends Model
{
    /** One row only. */
    public const ID = 1;

    protected $fillable = [
        'fte_hours',
        'period_start',
        'period_end',
        'shift_note',
    ];

    protected function casts(): array
    {
        return [
            'fte_hours' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
        ];
    }

    /** The single settings row, created with defaults on first read. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => self::ID], ['fte_hours' => 40]);
    }

    /** The shift information note rendered to HTML, or null when blank. */
    public function shiftNoteHtml(): ?string
    {
        if (trim((string) $this->shift_note) === '') {
            return null;
        }

        return Str::markdown($this->shift_note, [
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
        ]);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'fte_hours' => $this->fte_hours,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
        ];
    }
}
