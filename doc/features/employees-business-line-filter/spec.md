# Employees — Business Line Filter — Spec

## Problem

The Employees page always lists every employee. A manager who wants to
see only one business line's people has no way to narrow the list. The
dashboard, which already breaks capacity down by business line, has no
link from a line's chart to that line's employees either.

## Solution

### Filter menu on the Employees page

A "Business lines" button sits next to the search box. Clicking it
opens a dropdown panel with one checkbox per business line, in
position order, plus a "No business line" checkbox for employees with
`business_line_id: null`. All boxes are checked by default. Toggling a
box reloads the list immediately, the same way search already
debounces and reloads — no separate Apply step.

- The panel closes on an outside click or Escape, matching the
  floating-panel pattern already used for the scheduling spots menu
  (`ShiftWeekTable.vue`).
- The button label stays the static "Business lines" text regardless of
  selection state.

### URL and backend filtering

Selection lives in the URL as `business_lines[]=<id>` repeated per
checked line, plus the literal value `none` for the "No business line"
box — mirroring how `search`/`sort`/`direction` already round-trip
through the query string.

- All-selected (the default) omits the param entirely, like the
  existing filters omit their default values.
- `EmployeeController::index` reads `business_lines[]`. When present,
  it filters to employees whose `business_line_id` is in the given ids,
  OR (if `none` is present) `business_line_id IS NULL`. When absent,
  no filter is applied (defaults to showing everyone, which is
  equivalent to "all checked").
- An unrecognized id (deleted business line, tampered URL) is silently
  ignored rather than erroring — matches how `sort` already falls back
  instead of rejecting bad input.
- `Employees/Index` receives `businessLines` (id, abbreviation) for
  rendering the menu, and `selectedBusinessLines` (the active list of
  ids, using `'none'` for the no-line option) so the page can restore
  checkbox state after a reload or on first load with a URL already
  carrying the param.

### Dashboard chart header → filtered Employees link

Each dashboard card's header becomes a link:

- A business-line card's header links to
  `/employees?business_lines[]=<line id>` — only that line checked.
- The "Overall" card's header links to `/employees` — the default,
  every line and "No business line" checked.

## Key decisions

- **Immediate apply, no Apply button.** Matches the page's existing
  search-as-you-type pattern; one filter mechanism to learn instead of
  two.
- **All-selected omits the query param.** Keeps the default URL clean
  and consistent with how `search`/`sort`/`direction` already behave —
  a fresh `/employees` visit and an explicit "everything checked" visit
  are the same URL.
- **"No business line" is a first-class filter option.** Without it,
  unassigned employees would be stuck always-visible or always-hidden
  regardless of the checkboxes, which would make the filter feel
  incomplete next to the "No business line" label the table already
  shows.
- **Bad/stale ids are ignored, not rejected.** A deleted business line
  or a hand-edited URL shouldn't 500 or dead-end the page; it just
  filters to whatever ids still resolve.
- **Floating panel, not a new gated component.** The dropdown reuses
  `CheckboxInput` and `ButtonSecondary`, positioned with the same
  outside-click/Escape-to-close technique `ShiftWeekTable.vue` already
  uses for its spots menu. This is a page-level composition, not a new
  entry in the gated Input/Button/Card/Layout sets.
- **Dashboard link target reuses the same query param.** No new
  backend contract between Dashboard and Employees — the chart-header
  link is just a URL into the filter that already exists.

## Non-goals

- No change to the Employees page's search, sort, or pagination
  behavior.
- No multi-select "AND" logic between search and business lines beyond
  both narrowing the same list (they already compose: search text +
  selected lines).
- No persistence of the filter across page types beyond the URL (no
  cookie/localStorage remembering the last selection).
- No change to the dashboard's own charts, colors, or coverage donuts.
