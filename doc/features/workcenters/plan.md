# Workcenters — Plan

Status: in progress — 2/5

Spec: `spec.md`. Roadmap phase 3 continuation. Settings CRUD mirrors
`BusinessLineController` (`store`, `update`, `destroy`, `reorder`). The
Workcenters tab follows `settings-explicit-save/spec.md`'s shipped
model: local edit-until-Save state, diffed and sent as one request per
change on Save, via the same `putAsync`/`postAsync`/`deleteAsync` +
`Promise.allSettled` shape `saveBusinessLines`/`saveShifts` already use.
A second controller owns the shift-attachment and capacity endpoints.

- [x] 1. **Backend: Workcenter record and Settings CRUD.** Migration
  `workcenters` (`name` string unique max 50, `description` string
  nullable max 255, `position` integer, `archived_at` timestamp
  nullable, timestamps), plus the `workcenter_shift` pivot table
  (`workcenter_id`, `shift_id`, timestamps, unique on the pair) — pulled
  in from step 3 since the delete guard below needs it to exist.
  `Workcenter` model (`$fillable` the four editable fields;
  position-ordered default scope with archived rows last; `shifts()`
  belongsToMany; `toPayload()` →
  `{ id, name, description, position, archived_at }`). `Shift` gains
  `workcenters()` belongsToMany. `WorkcenterFactory`.
  `WorkcenterController` with `store` (name required, case-insensitive
  unique, max 50; description nullable, max 255), `update` (same rules,
  unique ignoring self, plus `archived` boolean that sets or clears
  `archived_at`), `destroy` (`ValidationException` if the workcenter has
  any shift attached, matching `UserController`'s guard style),
  `reorder` (full, distinct id-set validation like
  `BusinessLineController::completeIdSet`, sets each row's `position` to
  its index). Shared uniqueness `Closure` like
  `BusinessLineController::uniqueName`. `SettingsController@index` adds
  `workcenters` in position order. Routes behind `admin`:
  `POST /settings/workcenters`, `PUT /settings/workcenters/{workcenter}`,
  `DELETE /settings/workcenters/{workcenter}`,
  `PUT /settings/workcenters/reorder`. Feature test
  `WorkcenterConfigTest` (guest and manager blocked; index payload in
  position order, archived last; create appends; blank, too-long, and
  duplicate-case name rejected; description optional; update; update
  with `archived: true` sets `archived_at`, `false` clears it; reorder
  sets positions and rejects a partial or foreign id set; delete removes
  an empty workcenter; delete on one with an attached shift is
  rejected). Full PHP suite green (416 passed). Pint clean.

- [x] 2. **Frontend: the Workcenters Settings tab, list only.** New
  `resources/js/components/WorkcenterList.vue`, structured like
  `BusinessLineList.vue`: local `rows` ref seeded from an `items` prop
  (each row's `shifts` defaults to `[]` when absent), emitting
  `update:items` on change; a name `TextInput`, a description
  `TextInput`, and a drag handle per row (`useDragReorder`); an add
  row; a bin button that removes the row locally, shown only while
  `item.shifts` is empty; an **Archived** `CheckboxInput` shown instead
  once `item.shifts` is non-empty. No request fires from the component
  itself. `Settings/Index.vue` gains a `workcenters` tab after `shifts`:
  `committedWorkcenters`/`currentWorkcenters` refs, `workcentersDirty`
  computed (new/removed/reordered rows, or a changed name, description,
  or archived flag), `saveWorkcenters()` (diffs to `deleteAsync`,
  `putAsync` per edited row, `putAsync('/settings/workcenters/reorder', …)`,
  `postAsync` per new row, mirroring `saveBusinessLines`), and
  `cancelWorkcenters()`. `en.json`: `settings.tab.workcenters` and the
  `workcenters.*` key set (name, description, add, add_placeholder,
  archived, delete, list_empty, error.name_taken,
  flash.saved). Vitest `WorkcenterList.test.js` (row edit, add, and
  remove change local state and emit; the bin button is hidden and the
  Archived checkbox shows once a row's `shifts` array is non-empty) and
  a `SettingsIndex.test.js` addition (the tab's Save fires the expected
  delete/put/post/reorder requests for a mixed change set, matching the
  existing Business Lines save test). `npm run test` and `npm run build`
  green.

- [ ] 3. **Backend: shift attachment and capacity.** Migrations
  `workcenter_shift_capacities` (`workcenter_id`, `shift_id`, `weekday`
  unsigned tinyint 1–7, `spots` unsigned integer default 0, timestamps,
  unique on the triple), `workcenter_shift_date_overrides`
  (`workcenter_id`, `shift_id`, `date`, `spots` unsigned integer,
  timestamps, unique on the triple). The `workcenter_shift` pivot
  already exists (step 1). `Workcenter` gains `spotsFor(Shift $shift,
  Carbon $date): int` (override first, weekday default otherwise, `0`
  if neither row exists). `WorkcenterShiftController@update` (replaces
  the full
  shift-id list for a workcenter: attaches new pivots and creates their
  seven weekday-capacity rows at `spots = 0`; detaches removed pivots
  and deletes their weekday-capacity and date-override rows).
  `WorkcenterShiftController@updateCapacity` (one shift, all seven
  weekday values in one request; each `spots` `integer|min:0`; upserts
  the seven rows). Routes behind `admin`:
  `PUT /settings/workcenters/{workcenter}/shifts`,
  `PUT /settings/workcenters/{workcenter}/shifts/{shift}/capacity`.
  `SettingsController@index`'s `workcenters` payload gains `shifts:
  [{ id, name, weekday_capacities: [spots x7] }]` per workcenter (shift
  order by `start_time` then `name`, matching the Shift default scope).
  Feature test `WorkcenterShiftTest` (guest and manager blocked;
  attaching a shift creates seven zero-spot rows; detaching a shift
  deletes its capacity and override rows; capacity update writes all
  seven values; a malformed or negative `spots` value is rejected;
  `spotsFor` returns the override when one exists for the date, the
  weekday default otherwise, and `0` when neither exists). Full PHP
  suite green.

- [ ] 4. **Frontend: row detail — shifts and capacity, folded into the
  tab Save.** `WorkcenterList.vue` rows become expandable. Expanded, a
  row shows a shift multi-select (checkbox per shift, labeled with name
  and clock range) that changes the row's local `shifts` array, and per
  attached shift a 7-column (Mon–Sun) `NumberInput` grid for the
  weekday default that changes local state on each cell. Both changes
  only mark the tab dirty; nothing fires until Save. `Settings/Index.vue`'s
  `workcentersDirty` and `saveWorkcenters()` extend to cover this: a
  changed shift-id set on a still-present workcenter adds a
  `putAsync('/settings/workcenters/{id}/shifts', { shift_ids })` to the
  Save batch; a changed weekday-capacities array for a still-attached
  shift adds a `putAsync('.../shifts/{shiftId}/capacity', { spots })`.
  A removed or newly attached shift's capacity is covered by the
  shift-list request alone, not a separate capacity request. `en.json`
  adds `workcenters.shifts.*` (attach label, weekday abbreviations
  reused from an existing key if one exists, capacity field label,
  error.invalid_spots). Vitest additions: `WorkcenterList.test.js`
  (expanding a row shows its shift checkboxes and capacity grid;
  toggling a shift or editing a capacity cell changes local state and
  emits, firing no request) and `SettingsIndex.test.js` (Save sends the
  shift-list and capacity requests for a changed row, and skips them for
  an unchanged one). `npm run test` and `npm run build` green.

- [ ] 5. **Docs and full checks.** `doc/roadmap.md` — phase 3 row and
  section note that workcenters shipped (shift attachment and per-day
  open-spot capacity; employee assignment still later). `doc/concept.md`
  — a Workcenters entry under Core Data; update the "shift-to-workcenter
  link comes in a later phase" note. `doc/features/shift-definitions/spec.md`
  — a short note that the workcenter link shipped here. Set this
  `plan.md` header to `5/5`. Run Pint, `php artisan test`,
  `npm run test`, `npm run build`, and `php artisan migrate` on the dev
  database — all green.

## Not done / deferred

- Everything under the spec's non-goals: employee assignment, manual
  and automatic planning, the "fixed" flag, rule definitions, the
  `/solve` contract, competence gating, manager-level access, and
  editing date overrides from this tab.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
