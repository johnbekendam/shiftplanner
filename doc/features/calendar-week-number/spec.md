# Calendar Week Number

## Problem

The calendar on the planning page does not show week numbers. The planning tables use ISO week numbers, so a planner cannot match a calendar row to a week number. The selected week also has a border on each day, and a colored bar on the left marks published weeks. This is hard to read now that the planner always selects a whole week.

## Solution

Change the shared `Calendar` component.

- **Week number column:** a new first column with the ISO week number of each week row. It is a subtle label and not a button. The weekday header row gets a blank first cell, so the columns line up.
- **Selected week:** one rounded border around the whole week row, that is the week number and all 7 day cells. The per-day border on the days of the selected week is removed. The hover border on a day stays. When no week is selected (a weekday header is selected instead), no border shows.
- **Publish marker:** the colored bar on the left of the row is removed. A marked week (all checked workcenters are published) colors its week number cell with the color family of `weekMarkerColor`. An unmarked week number has a muted color.
- **Colors:** no new color token. The number cell uses the existing badge and muted tokens. The selection border uses the tab-active border token that the day borders use now.
- **Partial weeks:** a week that crosses a month boundary shows in both months with the same number. The selection border covers the whole row, including the blank cells.

## Key Decisions

- **ISO week number.** It matches the planning tables and the emails.
- **Display only.** A click target on a subtle label would look passive but act. Days stay the only way to select a week.
- **Move the publish marker to the week number.** A border around the week and a bar on the left edge would double up. The number cell shows the publish state with the same colors as the bar.
- **Change the shared component.** Only the planning page uses `Calendar`, so one change covers it.

## Non-goals

- A click on the week number.
- A heading text for the week number column.
- Week number formats other than ISO.
- A change to how the publish state is computed.
