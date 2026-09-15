<?php

namespace App\Models;

use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;

class PlanningSettings extends Model
{
    /** One row only. */
    public const ID = 1;

    protected $fillable = [
        'fte_hours',
        'weekly_hours_minimum',
        'period_start',
        'period_end',
        'shift_note',
        'shift_schedule_note',
        'allow_employee_changes',
        'max_hours_per_week_mode',
        'max_hours_per_week_severity',
        'max_shifts_per_day',
        'max_shifts_per_day_mode',
        'max_shifts_per_day_severity',
        'not_preferred_shift_mode',
        'not_preferred_shift_severity',
    ];

    protected function casts(): array
    {
        return [
            'fte_hours' => 'integer',
            'weekly_hours_minimum' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'allow_employee_changes' => 'boolean',
            'max_hours_per_week_severity' => 'integer',
            'max_shifts_per_day' => 'integer',
            'max_shifts_per_day_severity' => 'integer',
            'not_preferred_shift_severity' => 'integer',
        ];
    }

    /** The single settings row, created with defaults on first read. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => self::ID], [
            'fte_hours' => 40,
            'weekly_hours_minimum' => 20,
            'allow_employee_changes' => true,
            'max_hours_per_week_mode' => 'hard',
            'max_shifts_per_day' => 1,
            'max_shifts_per_day_mode' => 'hard',
            'not_preferred_shift_mode' => 'soft',
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

        return (new MarkdownRenderer)->render(
            $note,
            fn (string $label, string $url): string => '<a data-shift-note-button href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($label).'</a>',
            allowUnsafeLinks: true,
        );
    }

    /**
     * The shift-schedule note rendered to HTML, or null when blank. Shown
     * directly below the weekly availability grid. Same rendering as
     * {@see shiftNoteHtml()} — `:name` is replaced with $name when given.
     */
    public function scheduleNoteHtml(?string $name = null): ?string
    {
        $note = (string) $this->shift_schedule_note;

        if (trim($note) === '') {
            return null;
        }

        if ($name !== null) {
            $note = strtr($note, [':name' => $name]);
        }

        return (new MarkdownRenderer)->render(
            $note,
            fn (string $label, string $url): string => '<a data-shift-note-button href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($label).'</a>',
            allowUnsafeLinks: true,
        );
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'fte_hours' => $this->fte_hours,
            'weekly_hours_minimum' => $this->weekly_hours_minimum,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'allow_employee_changes' => $this->allow_employee_changes,
        ];
    }

    /** The global planning-rule fields, shared with the Planning Rules settings tab. */
    public function rulesPayload(): array
    {
        return [
            'max_hours_per_week_mode' => $this->max_hours_per_week_mode,
            'max_hours_per_week_severity' => $this->max_hours_per_week_severity,
            'max_shifts_per_day' => $this->max_shifts_per_day,
            'max_shifts_per_day_mode' => $this->max_shifts_per_day_mode,
            'max_shifts_per_day_severity' => $this->max_shifts_per_day_severity,
            'not_preferred_shift_mode' => $this->not_preferred_shift_mode,
            'not_preferred_shift_severity' => $this->not_preferred_shift_severity,
        ];
    }
}
