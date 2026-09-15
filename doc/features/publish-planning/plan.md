# Publish Planning — Plan

Status: done — 6/6

Spec: `spec.md`. `published_weeks` existence = published, no boolean
column. Publish/unpublish never touches `ShiftAssignment` or gates
editing. No auto-planner enforcement code — nothing exists to guard yet.

- [x] 1. **Backend: `published_weeks` table, model, publish/unpublish
  routes.** Migration `published_weeks` (`week_start` date unique,
  timestamps). `PublishedWeek` model (fillable `week_start`, cast
  `date:Y-m-d`). `PublishedWeekController@store` (`firstOrCreate` on
  `week_start` — re-publishing is a no-op) and `@destroy` (`whereDate`
  delete, no-op if absent). Routes `POST /scheduling/weeks/{weekStart}/publish`,
  `DELETE /scheduling/weeks/{weekStart}/publish`, admin-gated, `date`
  constrained to `\d{4}-\d{2}-\d{2}`. `en.json`: `scheduling.flash.published`,
  `scheduling.flash.unpublished`. `PublishedWeekTest` (8 cases: guest
  and manager blocked on both store and destroy; store creates a row;
  re-store is idempotent — still one row; destroy removes it; destroy
  is a no-op when no row exists). Full PHP suite green (510 passed; 4
  pre-existing, unrelated `GraphTransportTest` failures). Pint clean.

- [x] 2. **Backend: `SchedulingController` publish-state props.**
  `Employee::shiftAssignments()` `hasMany` added. `index()` gains
  `weekPublished` (bool) and `publishedDays` (`{ [day]: true }`,
  computed by mapping each day 1..daysInMonth to its Monday and
  checking which of those Mondays have a `PublishedWeek` row).
  `SchedulingIndexTest` +5 (published/unpublished week-selected cases;
  every day in a published week marked; a week spanning into the
  adjacent month still marks the current month's days; nothing marked
  when nothing's published). Full PHP suite green (515 passed). Pint
  clean.

- [x] 3. **Frontend: `Calendar.vue` week-row restructure + marker.**
  Day-grid restructured from one flat `grid grid-cols-7` into a `weeks`
  computed (7-cell chunks, first row's leading slots padded) rendered
  as one row-`<div>` per week via `<template v-for>` (no extra wrapper
  per cell — `v-if`/`v-else` between a pad `<div>` and the day
  `<button>`, same DOM per cell as before). Two new props:
  `weekMarkerDays` (`{ [day]: true }`, simpler than the spec's
  color-per-day map — a page only ever wants one marker color at a
  time) and `weekMarkerColor` (family name, default `'custom'`) — a
  new `BORDER_CLASS` map (mirroring `COLOR_CLASS` but for
  `border-(--color-badge-*-border)`) colors a row's left border when
  any of its real days are marked. `Calendar.test.js` +3 (a week with
  a marked day gets the border; an unmarked week doesn't; a custom
  `weekMarkerColor` is honored) — all 9 pre-existing tests unchanged
  and still green, confirming the refactor didn't touch day-button
  behavior. `npm run test` (483 passed) and `npm run build` green.

- [x] 4. **Frontend: wire publish/unpublish into `Scheduling.vue`.**
  New props `weekPublished`, `publishedDays`; `:week-marker-days="publishedDays"`
  passed to `Calendar`. A header (a "Published" pill + a
  `ButtonSecondary` reading Publish/Unpublish) above the workcenter
  cards, gated on `workcenters.length` (so it shows whenever the
  page isn't in its empty state, independent of whether the filter
  currently hides every card). `togglePublish()` uses
  `postAsync`/`deleteAsync` against `/scheduling/weeks/{weekStart}/publish`,
  matching the page's existing immediate-write convention. `en.json`:
  `scheduling.publish`, `scheduling.unpublish`, `scheduling.published_label`.
  `Scheduling.test.js` +5 (button text and click behavior for both
  states; header shows with no visible cards; `publishedDays` reaches
  the calendar as a week marker). `npm run test` (487 passed) and
  `npm run build` green.

- [x] 5. **Backend + frontend: employee's own Planning tab.** New
  `App\Services\PlannedShifts::forEmployee(Employee, bool $publishedOnly)`
  — shared by both this step and step 6 — groups an employee's
  `ShiftAssignment`s by Monday–Sunday week (`{ weekStart, weekEnd,
  published, assignments: [{ date, workcenter_name, shift_name,
  start_time, end_time }] }`), filtering to published weeks only when
  `$publishedOnly`. `PersonalPageController::show` gains `plannedShifts`
  (`publishedOnly: true`). New shared component
  `resources/js/components/PlannedShiftsList.vue` (`weeks`,
  `showPublishedMarker` props) — one heading per week's date range, a
  row per assignment, an empty state; the marker is hidden by default
  since the employee's own view never needs it. New "Planning" tab in
  `Personal/Show.vue` (no `hasError`, read-only). `PersonalPageTest`
  +2 (unpublished-week assignments never appear; published ones do,
  correctly shaped). `PlannedShiftsList.test.js` (4 cases: empty
  state; week heading + assignment rows; marker hidden by default;
  marker shown and correct per week when enabled). `PersonalShow.test.js`
  +2 (Planning tab listed; renders the list). Full PHP suite green
  (518 passed). `npm run test` (492 passed) and `npm run build` green.

- [x] 6. **Backend + frontend: admin's Planning tab.**
  `EmployeeController::edit` gains `plannedShifts`
  (`publishedOnly: false` — every assignment, draft included).
  `Employees/Form.vue` reuses `PlannedShiftsList` with
  `show-published-marker`, new "Planning" tab entry. `EmployeeAdminTest`
  +1 (both a published and a draft week appear, each marked
  correctly). `EmployeesForm.test.js` +1 (Planning tab present; both
  markers render). `doc/roadmap.md` — phase 6 marked Done, with a body
  section describing what shipped and the still-open auto-planner
  invariant. `doc/concept.md` — left as-is; its phase-6 wording
  already reads as the target workflow, not a claim about current
  build order, so no correction was needed. Full PHP suite green (518
  passed; same 4 pre-existing unrelated failures). `npm run test`
  (493 passed) and `npm run build` green. Pint clean on every touched
  file (the failures Pint reports elsewhere are pre-existing and
  untouched by this feature).

## Not done / deferred

- Everything under the spec's non-goals: auto-planner enforcement code,
  per-workcenter publish granularity, a publish precondition, an
  unpublish confirmation step, date-range restrictions on which weeks
  are publishable, employee notification on publish.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
