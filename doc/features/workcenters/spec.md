# Workcenters — Spec

Roadmap phase 3 continuation. The roadmap left shift coverage as "needs a
design session" (`doc/roadmap.md:15`) after shift definitions shipped.
This feature adds the missing shift-to-workcenter link and the
required-spot figure per day. `doc/concept.md:66-67` and
`doc/features/shift-definitions/spec.md:116-117,142` flag both as
deferred.

This is the first of two features. It stops at the workcenter, its
shifts, and their open-spot counts — no employee touches this feature.
A follow-up `scheduling` feature adds employee assignment against these
spots. A later, separate feature adds rule-based automatic planning.

## Problem

A manager cannot group shifts by where they run, or say how many people
each shift needs on a given day. Shifts are flat, global templates with
no concept of a place, and nothing records a headcount target anywhere.

## Solution

### Workcenter record

A new `workcenters` table:

| column | notes |
| --- | --- |
| `id` | |
| `name` | required, case-insensitive unique, max 50 |
| `description` | nullable, max 255 |
| `position` | integer, sets the manual order |
| `archived_at` | nullable timestamp |
| timestamps | |

A `Workcenter` model with a position-ordered default scope (archived
workcenters sort after active ones), a `shifts()` `belongsToMany`, and a
`toPayload()` returning
`{ id, name, description, position, archived_at }`.

A Workcenter has no link to a Business Line. `doc/concept.md` and
`business-lines/spec.md` keep Business Lines as the org/FTE-target unit.
A Workcenter is where shifts and headcount live instead. The two may
connect later through a planning rule (for example, a preference for
employees from a matching Business Line). That rule is not part of this
feature.

### Workcenter ↔ Shift

A `workcenter_shift` pivot table: `workcenter_id`, `shift_id`,
timestamps, unique on the pair. `Shift` gains a `workcenters()`
`belongsToMany`.

A shift stays a global, reusable template (`shift-definitions/spec.md`
is unchanged). The same shift — say "Early", 06:00–14:00 — can be used
by more than one workcenter. Each use gets its own open-spot count.
Nothing about the shift record itself changes.

### Open-spot capacity

A new `workcenter_shift_capacities` table:

| column | notes |
| --- | --- |
| `id` | |
| `workcenter_id` | `constrained`, cascade on delete |
| `shift_id` | `constrained`, cascade on delete |
| `weekday` | unsigned tinyint, ISO 1 (Monday) to 7 (Sunday) |
| `spots` | unsigned integer, `>= 0` |
| timestamps | |

Unique on `(workcenter_id, shift_id, weekday)`. One row per weekday
(1–7) exists for every workcenter/shift pair once that pair is created,
defaulting to `spots = 0`.

A separate `workcenter_shift_date_overrides` table:

| column | notes |
| --- | --- |
| `id` | |
| `workcenter_id` | `constrained`, cascade on delete |
| `shift_id` | `constrained`, cascade on delete |
| `date` | date |
| `spots` | unsigned integer, `>= 0` |
| timestamps | |

Unique on `(workcenter_id, shift_id, date)`. A calendar day's open-spot
count is the matching override if one exists, otherwise the weekday
default. Deleting an override reverts that date to the weekday default.

`Workcenter::spotsFor(Shift $shift, Carbon $date): int` resolves this:
override first, weekday default otherwise.

### Settings page — new "Workcenters" tab

`/settings` stays admin-only. The tabbed card gains a **Workcenters**
tab after **Shifts**, built on the explicit-save model
`settings-explicit-save/spec.md` already shipped for every other tab:
local edits only, one Save/Cancel pair per tab, no autosave, no confirm
on a local change.

**Workcenter list** — `WorkcenterList.vue`, in the shape of
`BusinessLineList.vue`: a name field, a description field, and a drag
handle for reordering, backed by `useDragReorder`. Editing a field,
adding a row, removing a row, or dragging a row only changes local
state. A row's bin button is available only while that workcenter has
no shift attached (checked against its local shift list, so the gate
tracks an in-progress, unsaved attach or detach too). Once it has at
least one shift, the bin button is replaced by an **Archived** checkbox
instead — reversible, and edited like any other field. Archived rows
sort after active ones and show a muted row style; nothing hides them.

**Row detail — shifts and capacity** — a row expands to show:

- A multi-select of shifts to attach (checkboxes against every shift by
  name). Toggling one changes the row's local shift list only.
- Each attached shift shows a 7-column grid (Mon–Sun) of spot-count
  number fields for the weekday default. Editing a cell changes local
  state only.
- Date overrides are not edited here. They come from the `scheduling`
  feature's calendar view, which owns per-day editing. This tab only
  sets the recurring pattern.

**Save** diffs local state against the last-committed state and fires
one request per change, the same `Promise.allSettled` shape the other
tabs use:

- `DELETE /settings/workcenters/{id}` for a removed row (only reachable
  when that row had no shift attached, so the server-side reject on a
  non-empty workcenter is a defense-in-depth path, not a normal one).
- `PUT /settings/workcenters/{id}` for a row whose name, description, or
  archived flag changed. `archived` in the request body maps to setting
  or clearing `archived_at`.
- `POST /settings/workcenters` for a new row.
- `PUT /settings/workcenters/reorder` with the full ordered id list, if
  the order changed.
- `PUT /settings/workcenters/{id}/shifts` with the full shift-id list,
  for a workcenter whose attached shifts changed. The server adds or
  removes pivot rows, and creates or deletes the seven weekday-default
  rows for that shift as needed.
- `PUT /settings/workcenters/{id}/shifts/{shift}/capacity` with all
  seven weekday values, for each (workcenter, shift) pair whose grid
  changed.

On success every request's result reseeds committed state from the
reloaded `workcenters` prop, matching how `saveBusinessLines` and
`saveShifts` already work. Cancel discards local state back to
committed, no confirmation.

`WorkcenterController` mirrors `BusinessLineController` (`store`,
`update`, `destroy`, `reorder`). A `WorkcenterShiftController` handles
the shift-list and capacity endpoints. `SettingsController@index` adds
`workcenters` as
`{ id, name, description, position, archived_at, shifts: [{ id, name, weekday_capacities: [spots x7] }] }`
in position order. A workcenter's attached-shift count, for the bin/archive
gate, is `shifts.length`.

## Key decisions

- **No Business Line link.** The user confirmed workcenters are an
  independent grouping. A future rule may use Business Line as a soft
  preference signal, but that is planning-time logic, not a schema
  relationship.
- **Shift stays global. The pivot is many-to-many.** Reusing "Early"
  across workcenters avoids duplicate shift records with identical
  clock ranges. Open-spot capacity is keyed on the (workcenter, shift)
  pair, never on the shift alone.
- **Weekday default plus per-date override, not per-date-only.** A
  weekday pattern (`Mon 4, Tue 4, ... Sun 0`) covers the common case
  without re-entering the same number into the future. A date override
  row handles one-off exceptions. Resolution order is override, then
  default.
- **Capacity spans all seven ISO weekdays**, unlike the Mon–Fri-only
  `recurring_availabilities` grid. A workcenter may run shifts on
  weekends even where employee-preference collection does not.
- **Soft-archive, not hard delete, once a workcenter has data.** Shift
  and capacity records reference the workcenter. Archiving keeps that
  history intact for the `scheduling` feature and any later reporting.
  A workcenter with no shift attached yet can still be deleted outright,
  matching how an unused row elsewhere in Settings behaves.
- **Archiving is a plain field, not a confirmed action.** It is fully
  reversible, so it follows the same no-confirm, edit-until-Save rule
  `settings-explicit-save/spec.md` already set for every other field in
  Settings, rather than a one-off confirm dialog.
- **Admin-only**, matching Shifts and Business Lines today. No
  manager-scoped permission model exists in the app. This feature does
  not introduce one.
- **This feature stops before employees.** Assignment, manual and
  automatic planning, and the "fixed" flag are the `scheduling` feature.
  Keeping the split means this spec's plan lands independently and does
  not block on assignment-model decisions still to be detailed.

## Non-goals

- Employee assignment to a workcenter/shift/date, manual planning UI,
  and the "fixed" assignment flag — all `scheduling` feature.
- Rule-based automatic planning, hard/soft rule definitions, and the
  OR-Tools `/solve` contract — a later, separate feature.
- Competence-to-workcenter gating (`competences/spec.md` flags this as
  future work; still deferred here).
- Manager-level (non-admin) access to workcenter management.
- Editing date overrides from the Settings tab — that lands with the
  `scheduling` feature's calendar view.
