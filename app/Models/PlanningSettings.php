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
        'allow_employee_changes',
    ];

    protected function casts(): array
    {
        return [
            'fte_hours' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'allow_employee_changes' => 'boolean',
        ];
    }

    /** The single settings row, created with defaults on first read. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => self::ID], [
            'fte_hours' => 40,
            'allow_employee_changes' => true,
        ]);
    }

    /**
     * The shift information note rendered to HTML, or null when blank.
     *
     * `:name` in the note is replaced with $name when one is given — the
     * personal page and the employee editor pass the employee's name. On
     * the settings editor, where no employee is in context, it is left
     * as written.
     */
    public function shiftNoteHtml(?string $name = null): ?string
    {
        $note = (string) $this->shift_note;

        if (trim($note) === '') {
            return null;
        }

        if ($name !== null) {
            $note = strtr($note, [':name' => $name]);
        }

        return Str::markdown($note, [
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
            'allow_employee_changes' => $this->allow_employee_changes,
        ];
    }
}
