# Export All Planned Hours

## Problem

The planned-hours export covers only the From/To range on the Planned hours
tab. A manager who needs the full history must pick a very wide range by
hand.

## Solution

The Planned hours tab gets a second button, "Export all to Excel", next to
"Export Excel". The button downloads `planned-hours-all.xlsx`.

The file has the same content rules and layout as the ranged export:

- Only published hours count.
- Only active workcenters show.
- One row for each workcenter and day, sorted by date ascending.
- Date cells use `yyyy-mm-dd` and hours cells use `0.00`.
- The headers and the sheet name come from the language file.

The only difference is the date range. The file covers every date that has
published hours.

The button uses the existing export route with the `all=1` parameter. With
this parameter, the route ignores the From/To values.

## Key decisions

- **No date limit, same shape.** The file keeps the summed rows. It does not
  add assignment details or draft hours.
- **A second button.** The From/To range still controls the table and the
  ranged export. A checkbox would make the table page over all dates too.
- **Secondary button style.** The two buttons look different.
- **A different file name.** The two downloads do not have the same name.
- **The same route.** The `all=1` parameter reuses the export code.

## Non-goals

- One row for each assignment, or employee details.
- Draft (unpublished) hours.
- Archived workcenters.
- An "all dates" mode for the table on the page.
