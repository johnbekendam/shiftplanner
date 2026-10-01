# Demand — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The Schedule page shows one table for all workcenters. Each row is a
workcenter and shift pair with seven weekday slot counts. A planner
changes the slots of one date on the planning page, from a menu in the
day header. The demand of a workcenter is in two places. The planner
cannot see which dates differ from the weekday defaults, or which dates
have open slots.

## Solution

The Schedule page becomes the **Demand** page. It uses `AppLayout` and
the section style of the availability tab. It shows one workcenter at a
time, in three sections.

### 1. Workcenter selector

A select at the top picks the workcenter. The URL holds it as
`?workcenter=<id>`. For that workcenter the server sends:

- its shifts, with the seven weekday defaults,
- all its date overrides,
- the assigned count per shift and date.

A workcenter change with unsaved edits opens the Stay / Discard dialog.

### 2. Default demand

A card with the header "Default demand". It has one row per shift: the
shift name and times, seven slot inputs (Monday to Sunday) and a delete
button. An add row below has a shift select. The select lists only the
shifts that the workcenter does not have yet.

### 3. Calendar and date card

A row with two cards, as on the availability tab. On narrow screens the
cards stack.

The calendar fills each day from the slots and the assigned counts of
the workcenter. Pending edits count.

- All slots of the day are filled: success.
- One or more slots are open: warning.
- The day has no slots: muted.

A day with one or more date overrides gets a border in the text color
of its own fill. The legend in the calendar footer explains the fills
and the border. Month navigation stays in the browser.

A click on a day selects it. A second click deselects it. With no day
selected, the date card shows a hint. With a day selected, the header
shows the date. The body has one row per shift: the name and times, the
assigned count and a slot input. The input minimum is the assigned
count. An overridden input gets a border. The footer has "Reset to
default". It removes all overrides of that date. The footer shows only
when the date has overrides.

A date value equal to the weekday default is not an override. Any date
is editable, past dates included.

### Saving

All edits stay pending until the user clicks Save. The page footer has
Cancel and Save, as on the availability tab. One Save writes the added
and removed shifts, the changed defaults and the changed dates. The
unsaved-changes guard warns before the user leaves the page.

Removal of a shift deletes its weekday defaults and its date overrides.
Planned assignments of that shift stay. Planning verification shows
them. The server still rejects a date value below the assigned count.

### Persistence

The page stores the selected workcenter and the selected day in
`sessionStorage`, with a key per user. A visit without `?workcenter`
restores the stored workcenter. An archived or deleted workcenter falls
back to the first active workcenter. A missing or invalid stored day
falls back to today. The calendar opens on the month of the selected day.

### Rename

"Schedule" becomes "Demand" in the navigation, the page title, the page
(`Demand.vue`), the controller (`DemandController`), the routes
(`/demand/...`) and the i18n keys (`demand.*`). The date override routes
move from `/planning/spots/...` to `/demand/...`. The old `/schedule`
URL redirects to `/demand`. The tables and models keep their names.

### Planning page

The planning page loses the slot menu and the reset button in the day
header. The day header becomes plain text. The planning page still
shows the slots from the demand.

## Key decisions

- **Explicit Save.** This matches the availability tab. The user can
  cancel a set of edits, and the calendar shows them before the save.
- **One workcenter per view, all dates at once.** One workcenter has few
  override rows. Month navigation needs no request and pending edits
  survive it.
- **Override border, as on the availability calendar.** The same signal
  means "differs from the default" on both calendars.
- **One reset per date.** This mirrors the availability date card.
- **sessionStorage for the selection.** This matches the selected week
  on the planning page. It needs no backend change.
- **Full rename with a redirect.** Code names match the UI. Old
  bookmarks still work.
- **Assignments stay on shift removal.** This is the current behavior.

## Non-goals

- Database migrations. The tables `workcenter_shift`,
  `workcenter_shift_capacities` and `workcenter_shift_date_overrides`
  stay as they are.
- Edits to more than one workcenter in one save.
- Changes to the planner, the planning rules or planning verification.
- Removal of planned assignments when demand drops.
- Persistence across browser tabs or devices.
