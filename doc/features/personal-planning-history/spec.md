# Personal Planning History — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The Planning tab on the personal page shows all published shifts of the
employee in one table, from old to new. Past shifts come first, so the
employee must scroll past them to find the next shift. The past shifts
are also not marked as history.

## Solution

The Planning tab has two parts. A past shift is a shift with a date
before today. Today uses the date of the browser.

1. **Upcoming.** The upcoming table shows the shifts of today and later,
   from old to new, with "Add to calendar". The working times list and
   the schedule note stay below it. The working times list shows only
   the upcoming shifts. An empty upcoming table shows "No planned shifts
   yet.", as it does now.
2. **History.** A separator at the bottom of the tab, then the heading
   "History" and a table with all past published shifts, from new to
   old. A past shift has no "Add to calendar". A click on a row opens
   the shift details, as in the upcoming table. With no past shifts,
   the separator and the history do not show.

The personal page opens on the Planning tab only when the employee has
one or more upcoming shifts. Otherwise it opens on the Information tab.

The server already sends all published shifts. The browser splits them.

## Key decisions

- **All history.** An employee has about 250 shifts per year. One table
  holds this without problems, so a limit adds no value.
- **History from new to old.** The recent past is what the employee
  looks for most.
- **History at the bottom.** The upcoming shifts, working times and note
  are the main content. A long history does not push them down.
- **Today is upcoming.** A shift of today is still work to do.
- **No calendar export for the past.** A past event in a calendar has no
  use.
- **History does not open the tab.** The Planning tab is for the next
  shifts. An employee with only past shifts starts on Information.
- **Split in the browser.** The data is already on the page, so no
  backend change is necessary.

## Non-goals

- The Planning tab on the employee edit page.
- A limit on the age of the history, paging or a date filter.
- Draft (not published) shifts.
