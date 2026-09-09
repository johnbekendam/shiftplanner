# Shift Information Note — Plan

Status: in progress — 1/4

Spec: `spec.md`. Roadmap phase 3, after `features/shift-definitions/`.
One global Markdown note on `planning_settings`, edited on the Shifts
settings tab, rendered at the top of the availability page.

- [x] 1. **Backend: store and render the note.** Migration adds a
  nullable `shift_note` text column to `planning_settings`.
  `PlanningSettings`: add `shift_note` to `$fillable`; add
  `shiftNoteHtml(): ?string` returning `null` for a blank note, else
  `Str::markdown()` (GitHub-flavoured, `html_input` = `allow`).
  `ShiftController@updateNote`: validate `note` as
  `nullable|string|max:20000`, store `null` when whitespace-only, save
  `PlanningSettings::current()`, redirect back with a flash. Route
  `PUT /settings/shifts/note` behind `admin`, declared before
  `PUT /settings/shifts/{shift}`. `SettingsController@index` adds
  `shiftNote` (raw string, `''` when unset). Feature test `ShiftNoteTest`
  (guest and manager blocked; update persists; a blank body clears it to
  `null`; index carries `shiftNote`; a GFM table in the note renders a
  `<table>` via `shiftNoteHtml`; a raw `<span>` survives the render).
  Full PHP suite green.

- [ ] 2. **Frontend: the note editor on the Shifts tab.** New
  `resources/js/components/ShiftNoteForm.vue`, built like
  `PeriodSettingsForm.vue`: a labelled `MultilineInput` seeded from a
  `note` prop and one `ButtonPrimary` that sends
  `PUT /settings/shifts/note`. `Settings/Index.vue` shifts panel renders
  a `CardSeparator` then `ShiftNoteForm` after `ShiftList`, passing a
  `shiftNote` prop. `en.json`: `shifts.note_label`, `shifts.note_hint`,
  `shifts.note_save`, `shifts.flash.note_saved`. Vitest
  `ShiftNoteForm.test.js` (renders the seeded value; submits the note to
  the endpoint) and `SettingsIndex.test.js` (the shifts panel mounts the
  form on its prop). `npm run test` and `npm run build` green.

- [ ] 3. **Render the note atop the availability page.**
  `EmployeeController@edit` and `PersonalPageController@show` payloads add
  `shiftNoteHtml` from `PlanningSettings::current()->shiftNoteHtml()`.
  New `resources/js/components/ShiftNote.vue`: an `html` prop; renders a
  `div` with `v-html` and wrapper arbitrary-variant Tailwind classes
  (`[&_table]:…`, `[&_th]:…`, `[&_td]:…`, token borders and text
  colour); renders nothing when `html` is null. `Employees/Form.vue` and
  `Personal/Show.vue` take a `shiftNoteHtml` prop and render `ShiftNote`
  at the top of the Availability tab, above the "Weekly pattern"
  heading and the grid empty state. Feature test: the `edit` and `show`
  payloads carry the rendered `shiftNoteHtml`. Vitest `ShiftNote.test.js`
  (renders the HTML when present; renders nothing when null) and updates
  to `EmployeesForm.test.js` and `PersonalShow.test.js` (the block shows
  above the grid when the prop is set). `npm run test` and `npm run
  build` green.

- [ ] 4. **Docs and full checks.** `doc/roadmap.md` — the phase 3
  Shifts note mentions the information block. `doc/concept.md` — the
  Shifts section notes the Markdown information block shown on the
  availability page. Set this `plan.md` header to `4/4`. Run Pint,
  `php artisan test`, `npm run test`, `npm run build`, and
  `php artisan migrate` on the dev database — all green.

## Not done / deferred

- Everything under the spec's "Non-goals": per-shift notes, a preview,
  client-side rendering, a sanitizer, note history, a per-Business-Line
  note, and any planner use.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
