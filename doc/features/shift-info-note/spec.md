# Shift Information Note — Spec

Roadmap phase 3. A follow-up to `features/shift-definitions/`. One global
Markdown note about the shifts, edited on the Shifts settings tab and
shown at the top of every employee's availability page.

## Problem

Shifts carry a name and a clock range only. A manager also needs to tell
employees things that do not fit that structure: an allowance table per
shift, break rules, who to call. There is nowhere to put that text, and
the employee availability page has no room for context.

## Solution

### Storage

`planning_settings` gains a nullable `shift_note` text column. The row is
already the global settings singleton. `PlanningSettings`:

- `$fillable` gains `shift_note`.
- `shiftNoteHtml(): ?string` returns `null` when the note is blank,
  otherwise the `Str::markdown()` render of it. The converter is
  GitHub-flavoured (tables, autolinks) and passes raw HTML through
  (`html_input` = `allow`).

### Edit — Shifts settings tab

The Shifts tab keeps the shift table at the top. Below a `CardSeparator`
sits a note editor: a labelled `MultilineInput` bound to the raw
Markdown and one `ButtonPrimary` that saves. A new `ShiftNoteForm.vue`
component, built like `PeriodSettingsForm.vue`, takes a `note` prop and
`PUT`s to its own endpoint. There is no preview.

- `PUT /settings/shifts/note` → `ShiftController@updateNote`, behind
  `admin`. Declared before `PUT /settings/shifts/{shift}` (which matches
  `[0-9]+` only, so there is no real conflict).
- Validation: `note` is `nullable|string|max:20000`. A whitespace-only
  value is stored as `null`.
- `SettingsController@index` adds `shiftNote` — the raw string, `''`
  when unset.

### Display — top of the Availability tab

`EmployeeController@edit` and `PersonalPageController@show` payloads gain
`shiftNoteHtml` (the rendered HTML, or `null`).

A new `ShiftNote.vue` takes an `html` prop. It renders a `div` with
`v-html` above the "Weekly pattern" heading on both the manager editor
(`Employees/Form.vue`) and the personal page (`Personal/Show.vue`). It
renders nothing when `html` is `null`. It shows even when no shift is
defined yet, above the empty-state message.

The rendered HTML is styled with Tailwind arbitrary-variant utilities on
the wrapper (`[&_table]:…`, `[&_th]:…`, token border and text colours).
No custom CSS, no typography plugin.

### i18n

`shifts.note_label`, `shifts.note_hint`, `shifts.note_save`,
`shifts.flash.note_saved`.

## Key decisions

- **One global note, on `planning_settings`.** The content is general
  shift information, not per shift, so a single nullable column on the
  existing settings singleton is enough. No new table or model.
- **Server-side render, raw HTML allowed.** `league/commonmark` is
  already a dependency; the front end gets ready HTML and needs no
  Markdown library. The note is authored only by an admin, who already
  controls the theme, every setting, and all data — HTML they write
  carries no privilege they lack — so the converter allows raw HTML and
  no sanitizer is added, even though the personal page is token-only.
- **Editor on the Shifts tab, below the table.** The note is about the
  shifts, so it belongs on that tab. The structured shift list stays
  primary; the free-text block follows a separator. Its own Save button
  and endpoint keep it independent of the inline shift-row writes.
- **Shown on both surfaces, above the grid.** A manager sees the same
  block the employee sees without opening a preview link. It sits above
  the weekly pattern because it is context for filling the grid in.
- **Always shown when set.** The note renders regardless of whether
  shifts exist, because it is useful before setup too.
- **No preview.** A preview needs client-side rendering or a debounced
  round-trip. The admin checks the result on an availability page.

## Non-goals

- Per-shift notes.
- A live or rendered preview in the editor.
- Client-side Markdown rendering or an HTML sanitizer.
- Note history or versioning.
- A per-Business-Line note.
- Translating the note content; only the UI labels are in the language
  file.
- Any planner or `/solve` use of the note.
