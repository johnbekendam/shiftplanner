# Planning Calendar Export

## Problem

An employee sees planned shifts on the personal page. The employee must copy each shift into a calendar by hand.

## Solution

Each row of the planning table opens a shift card when the user clicks it. The card shows the week, date, day, shift, working hours, workcenter, and contact. On the personal page, the card has an **Add to calendar** button. The button downloads an `.ics` file with one event for that shift. The employee opens the file to add the event to any calendar app.

The event has these fields:

- **Title:** the shift name and the workcenter, for example `Early – Line 1`.
- **Start and end:** the shift date with the shift start time and end time.
- **Location:** the workcenter name.
- **Description:** the contact name, when the workcenter has one.
- **UID:** stable for each date, shift, and workcenter, so a second import updates the event and does not add a copy.

The file name is `shift-dd-mm-yyyy.ics`.

## Key Decisions

- Use an `.ics` download. It works in Outlook, Google, Apple, and phone calendars. It needs no account or login.
- Build the file in the browser. The page already has all the data, so no new route is needed.
- Use floating local times (no time zone in the file). The calendar shows the shift at the same clock time for the employee. The app has one site, and its server time zone is UTC, so a fixed zone would be wrong.
- Shifts never cross midnight (start is before end), so each event is on one day.
- Show the button on the personal page only. The employee edit page is for planners.
- Put the button in the shift card (`ShiftDetailsDialog`), not in the table. A table row has no icon or tooltip.
- The rows are clickable on both pages. The employee edit page shows the same card without the button.
- Use the existing `calendar` icon on the button.

## Non-goals

- Downloading all shifts in one file, or a subscribable calendar feed.
- Google or Outlook links.
- Reminders or alarms in the event.
- A button on the employee edit page.
