# Availability Grid Note — Spec

Roadmap phase 4. A follow-up to `features/shift-definitions/` and
`features/shift-info-note/`. One global Markdown note shown directly below
the weekly availability grid, for shift-timing facts the grid structure
cannot carry.

## Problem

The availability grid has one row per shift, with a single `start – end`
per row that applies to all five weekdays. A shift can deviate on one
weekday — the Friday evening shift ends earlier, for example. There is no
place to tell the employee this where they will see it while filling the
grid in.

The existing shift information note (`shift_note`) no longer fits. It has
become a general "Information" page: its own Settings tab and its own tab
on the availability page, above and separate from the grid. Shift-timing
detail put there is a tab away from the cells it describes and mixed with
unrelated onboarding text.

A structured per-cell time override was considered and rejected for now
(see Key decisions). This feature is the light option: free text next to
the grid.

## Solution

### Storage

`planning_settings` gains a nullable `shift_schedule_note` text column.
The row is the global settings singleton. `PlanningSettings`:

- `$fillable` gains `shift_schedule_note`.
- `scheduleNoteHtml(?string $name = null): ?string` returns `null` when
  the note is blank, otherwise the rendered HTML. It mirrors the existing
  `shiftNoteHtml()` exactly — same `MarkdownRenderer`, same `:name`
  substitution when a name is given, same button-link callback and
  `allowUnsafeLinks: true`. One code path, one behaviour to learn.

### Edit — Shifts settings tab

The Shifts tab currently holds the shift table only (the older
information-note editor moved to the Information tab). Below the table and
a `CardSeparator`, add a note editor: a labelled `MultilineInput` bound to
the raw Markdown and one `ButtonPrimary` that saves. A new
`ScheduleNoteForm.vue`, built like the existing `ShiftNoteForm.vue`, takes
a `note` prop and `PUT`s to its own endpoint. No preview.

- `PUT /settings/shifts/schedule-note` → `ShiftController@updateScheduleNote`,
  behind `admin`. Declared before `PUT /settings/shifts/{shift}` (which
  matches `[0-9]+` only, so there is no real conflict), next to the
  existing `PUT /settings/shifts/note` route.
- Validation: `note` is `nullable|string|max:20000`. A whitespace-only
  value is stored as `null`.
- `SettingsController@index` adds `scheduleNote` — the raw string, `''`
  when unset.

### Display — below the availability grid

`EmployeeController@edit` and `PersonalPageController@show` payloads gain
`scheduleNoteHtml` (rendered HTML, or `null`), alongside the existing
`shiftNoteHtml`, passing the employee's first name for `:name`.

Reuse the existing `ShiftNote.vue` component (a generic styled `v-html`
wrapper — no new display component). Render it directly below
`<AvailabilityGrid>`, inside the same availability `<section>`, on both
`Personal/Show.vue` and `Employees/Form.vue`. It renders nothing when the
prop is `null`.

### i18n

`shifts.schedule_note_label` — "Shift schedule notes".
`shifts.schedule_note_hint` — "Markdown. Shown directly below the weekly
availability grid. Use it for timing exceptions, such as a shift that ends
earlier on one weekday."
`shifts.schedule_note_save` — "Save".
`shifts.flash.schedule_note_saved` — "Shift schedule notes saved."

## Key decisions

- **A second note, separate from `shift_note`.** The information note is
  now general-purpose and lives on its own tab. This note is shift-timing
  context and must sit with the grid. Two columns on the settings
  singleton, two editors, no shared free-text field — each has one clear
  job and one clear place.
- **Free text, not a structured per-cell override.** A structured
  alternate start/end per `(weekday, shift)` was the first choice in the
  design session. It is the right model eventually — unambiguous, and the
  phase-5 planner can read it — but it needs a migration, an admin UI, and
  grid and hours-math changes. Deferred. A note ships now and covers the
  immediate need: telling the employee.
- **Editor on the Shifts tab, below the table.** The note is about the
  shifts. The structured shift list stays primary; the free-text block
  follows a separator, with its own Save button and endpoint, independent
  of the inline shift-row writes. Same shape as the old note editor.
- **Reuse `ShiftNote.vue` for display; mirror `shiftNoteHtml()` for
  render.** The component and the render helper are already generic. No
  new styling, no second Markdown path.
- **Shown only when set.** `v-if` on the rendered HTML, matching the
  information-note pattern. Nothing renders for the common empty case.
- **Below the grid, not above.** Above is the information note's place.
  This note annotates cells the reader has just seen.

## Non-goals

- A structured per-weekday time override, and any grid rendering of
  alternate times (kept for a later phase).
- Any change to `calculateAvailabilityHours` or the hours warning. The
  note does not move the numbers.
- Per-shift notes (one note per `Shift` row).
- A live or rendered preview in the editor.
- Client-side Markdown or an HTML sanitizer.
- Note history or versioning.
- A per-Business-Line note.
- Translating the note content; only the UI labels are in the language
  file.
- Any planner or `/solve` use of the note.
