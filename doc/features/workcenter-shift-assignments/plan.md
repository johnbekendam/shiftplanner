# Workcenter Shift Assignments — Plan

Status: done — 3/3

Spec: `spec.md`. Reuses the `workcenter_shift`, `workcenter_shift_capacities`,
and `workcenter_shift_date_overrides` tables already migrated by
`workcenters/spec.md` — no new migration. One new controller, one new
page, one Save/Cancel bar reused from `TabSaveBar`.

- [x] 1. **Backend: the assignment controller and routes.**
  `WorkcenterShiftAssignmentController`: `index()` renders
  `Inertia::render('WorkcenterShifts', [...])` with `workcenters`
  (`{ id, name }`, non-archived), `shifts` (`{ id, name, start_time,
  end_time }`, start-time order via `Shift`'s default scope),
  `assignments` (one per `workcenter_shift` row: `{ workcenter_id,
  shift_id, spots: number[7] }`, weekday 1–7 in order, `0` for a
  missing capacity row). `store` validates `workcenter_id` (`exists`,
  not archived — a `Closure` rule), `shift_id` (`exists`), the pair not
  already in `workcenter_shift` (`ValidationException`, `UserController`
  guard style), `spots` (`required|array|size:7`, each
  `integer|min:0`); attaches the pivot and inserts the seven capacity
  rows. `update($workcenterId, $shiftId)` 404s if the pair is not
  assigned, validates `spots` the same way, upserts the seven rows.
  `destroy($workcenterId, $shiftId)` 404s the same way, detaches the
  pivot, deletes its capacity and date-override rows. Routes behind
  `admin`: `GET /workcenter-shifts`, `POST /workcenter-shifts`,
  `PUT /workcenter-shifts/{workcenter}/{shift}`,
  `DELETE /workcenter-shifts/{workcenter}/{shift}`. Feature test
  `WorkcenterShiftAssignmentTest` (guest and manager blocked on the
  index and on `store`; index payload shape, active-only workcenters,
  assignment spots; store creates the pivot and seven capacity rows;
  store rejects a duplicate pair, an archived workcenter, a missing
  workcenter/shift id, a negative `spots` value, or a short `spots`
  array; update writes all seven values and 404s on an unassigned pair;
  destroy removes the pivot and its capacity/override rows and 404s on
  an unassigned pair). Full PHP suite green (430 passed). Pint clean.

- [x] 2. **Frontend: the Workcenter Shifts page.** New
  `resources/js/pages/WorkcenterShifts.vue`: local `rows`/`committed`
  refs seeded from the `assignments` prop, keyed by `workcenter_id:shift_id`
  (no synthetic id — the pair is the identity); an add-row with a
  Workcenter `SelectInput`, a Shift `SelectInput`, and seven
  `NumberInput` fields defaulting to `0`, appending a local row on
  click; a table row per assignment with the workcenter/shift name as
  plain text, seven `NumberInput` cells, and a bin button that removes
  the row locally; a page-level `TabSaveBar` wired to a diff function
  (`POST` per new pair, `PUT` per pair with changed spots, `DELETE` per
  removed pair, `Promise.allSettled`, reseed from reloaded props on
  success — same shape `saveBusinessLines` uses). `AppLayout.vue` gains
  `{ label: __('nav.workcenter_shifts'), href: '/workcenter-shifts',
  icon: 'table-cells' }` in the admin block, after Mailbox.
  `AppLayoutNav.test.js` updated for the new nav item. `en.json`:
  `nav.workcenter_shifts` and a `workcenter_shifts.*` key set (title,
  column headers, weekday abbreviations, add, delete, select
  placeholders, list_empty, error.duplicate_pair,
  error.workcenter_archived, flash.saved). Vitest
  `WorkcenterShifts.test.js` (renders a row per assignment with names;
  empty state; Save/Cancel disabled with nothing changed; editing a
  spot cell enables Save and fires no request until clicked; adding a
  row via the two selects appends it locally with no request; removing
  a row deletes it locally with no confirm; Save fires the expected
  PUT/POST/DELETE; Cancel reverts without saving). `npm run test` (425
  passed) and `npm run build` green.

- [x] 3. **Docs and full checks.** `doc/roadmap.md` — phase 3 row and
  section note that workcenter-shift assignment shipped. `doc/concept.md`
  — the Workcenters entry rewritten: the record is name-only, attachment
  and capacity moved to the dedicated `/workcenter-shifts` page. Pint
  clean. `php artisan test`: 430 passed. `npm run test`: 425 passed.
  `npm run build`: green. `php artisan migrate`: nothing to migrate, as
  expected (no new migration this feature).

## Not done / deferred

- Everything under the spec's non-goals: date-override editing, employee
  assignment, automatic planning, rule definitions, bulk actions,
  manager-level access.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
