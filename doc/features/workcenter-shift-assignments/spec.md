# Workcenter Shift Assignments — Spec

Roadmap phase 3 continuation. Second of three features that build on
`doc/features/workcenters/spec.md`. Workcenters and shifts already
exist as separate, independently maintained lists. This feature links
them and sets the open-spot capacity each pairing needs — the "required
headcount per shift" the roadmap left as "needs a design session"
(`doc/roadmap.md:15`).

## Problem

Nothing records which shifts a workcenter runs, or how many people each
one needs on a given weekday. `workcenters/spec.md`'s first shipped
version put this on the Workcenters Settings tab, behind an expandable
row per workcenter. The user asked for a page boundary instead: Settings
defines elements (workcenters, shifts), a separate page relates them.
Assigning shifts to workcenters, and tuning their capacity, is a more
frequent task than naming a workcenter — it deserves its own page and
its own view of every assignment at once, not one buried inside each
workcenter's row.

## Solution

### Data

Reuses tables already migrated by `workcenters/spec.md`:

- `workcenter_shift` — the pivot. One row per (workcenter, shift) pair
  is one assignment.
- `workcenter_shift_capacities` — one row per (workcenter, shift,
  weekday), `weekday` ISO 1 (Monday) to 7 (Sunday), `spots` unsigned
  integer `>= 0`. Created (seven rows, `spots = 0`) when an assignment
  is created, deleted when it is removed.
- `workcenter_shift_date_overrides` — untouched here. A later feature's
  calendar view owns per-date editing.

No schema change. This feature is new controllers, routes, and one page.

### Page — `/workcenter-shifts`

A new sidebar item, **Workcenter Shifts**, admin-only (`nav.workcenter_shifts`,
icon `table-cells`), next to Mailbox and Settings in
`AppLayout.vue`. Route behind the existing `admin` middleware group.

One page, one table, no tabs. Every (workcenter, shift) assignment
across every workcenter is one row:

| Workcenter | Shift | Mon | Tue | Wed | Thu | Fri | Sat | Sun | |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Line 1 | Early | 4 | 4 | 4 | 4 | 2 | 0 | 0 | 🗑 |
| Line 1 | Late | 2 | 2 | 2 | 2 | 2 | 0 | 0 | 🗑 |
| Line 2 | Early | 3 | 3 | 3 | 3 | 3 | 1 | 0 | 🗑 |

Built on the same explicit-save model as the rest of the app
(`settings-explicit-save/spec.md`): local edit-until-Save state, one
Save/Cancel pair for the whole page (`TabSaveBar`, reused outside
Settings — it takes `dirty`/`saving`/`justSaved` props and is not
Settings-specific), no autosave, no confirm on a local change.

- **Existing rows** — the Mon–Sun `NumberInput` cells are the only
  editable fields. The Workcenter and Shift columns are fixed once a row
  exists. Changing which pair an assignment covers means removing the
  row and adding a new one, not editing it in place.
- **New rows** — an add row holds a Workcenter `SelectInput`, a Shift
  `SelectInput` (every workcenter/shift, not just unassigned ones — a
  duplicate pair is caught the same way an edit conflict is, see
  Validation), and seven spot fields, all starting at `0`. Submitting it
  appends a local row. Nothing is sent until Save.
- **Delete** — a bin button removes the row locally, same as
  `BusinessLineList`: no confirm, nothing destroyed until Save.
- **Save** diffs local state against last-committed state:
  - `POST /workcenter-shifts` per new row —
    `{ workcenter_id, shift_id, spots: number[7] }`. The server attaches
    the pivot and writes the seven capacity rows in one pass.
  - `PUT /workcenter-shifts/{workcenter}/{shift}` per row whose spots
    changed — `{ spots: number[7] }`.
  - `DELETE /workcenter-shifts/{workcenter}/{shift}` per removed row.
    Detaches the pivot and deletes its capacity and date-override rows,
    the same cleanup `WorkcenterShiftController::update` did before.
  - Same `Promise.allSettled` + reseed-from-reloaded-props shape every
    other explicit-save list already uses.

### Controller

`WorkcenterShiftAssignmentController`:

- `index()` — `Inertia::render('WorkcenterShifts', [...])`:
  `workcenters` (`{ id, name }`, active only, position order — an
  archived workcenter cannot take a new assignment, see Validation),
  `shifts` (`{ id, name, start_time, end_time }`, start-time order),
  `assignments` (one entry per existing `workcenter_shift` row:
  `{ workcenter_id, shift_id, spots: number[7] }`, `spots` weekday 1–7
  in order, `0` for a missing capacity row).
- `store(Request)` — validates `workcenter_id` exists and is not
  archived, `shift_id` exists, the pair is not already assigned
  (`ValidationException`, same style as `UserController`'s guard), and
  `spots` is an array of seven `integer|min:0`. Attaches the pivot,
  inserts the seven capacity rows.
- `update(Request, $workcenterId, $shiftId)` — 404 if the pair is not an
  existing assignment. Validates `spots` the same way. Upserts the seven
  rows.
- `destroy($workcenterId, $shiftId)` — 404 if not assigned. Detaches the
  pivot, deletes its capacity and date-override rows.

## Key decisions

- **A dedicated page, not a Settings tab.** The user's own framing:
  Settings defines elements, a separate page relates them. This page
  also needs the whole-table view a per-workcenter expandable row could
  not give — seeing every workcenter's coverage at a glance is the
  point.
- **One page-wide Save/Cancel, not per-row saves.** Matches the
  explicit-save model the rest of the app just finished moving to
  (`settings-explicit-save/spec.md`). `TabSaveBar` is reused outside
  Settings since it is already generic (props in, `save`/`cancel`
  events out) — no Settings-specific coupling to strip out.
- **Workcenter and Shift are fixed once a row exists.** Letting them
  change in place would mean silently moving a capacity history to a
  different pair. Remove-and-re-add is explicit about what happened
  instead.
- **The table lists every assignment across every workcenter, not one
  workcenter at a time.** The user asked for a single table with
  workcenter and shift as columns, not a per-workcenter view. A manager
  comparing coverage across workcenters reads one table instead of
  opening each one.
- **An archived workcenter cannot take a new assignment**, but a row
  already pointing at one (created before it was archived) still shows
  and stays editable — archiving does not retroactively touch existing
  assignments, consistent with `workcenters/spec.md`'s
  soft-archive-keeps-history decision.
- **Admin-only**, matching every other page in this area. No
  manager-scoped permission model exists in the app.

## Non-goals

- Editing per-date overrides — a later feature's calendar view owns
  that.
- Employee assignment, manual planning UI, the "fixed" flag — the
  `scheduling` feature.
- Rule-based automatic planning, hard/soft rules, the OR-Tools `/solve`
  contract — a later, separate feature.
- Bulk actions (copy one workcenter's pattern to another, fill a whole
  column at once). A plain per-cell grid is enough for now.
- Manager-level (non-admin) access.
