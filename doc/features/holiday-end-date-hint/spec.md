# Holiday End Date Hint — Spec

## Problem

People enter holidays with a start date and an end date. It is not clear
that the end date is part of the holiday. Some people enter the day they
return to work as the end date. Then the system blocks one day too many.

## Solution

Show a short hint below the holiday table:

> The end date is part of the holiday. Enter the last day you are off.

## Key decisions

- **Put the hint in `HolidayList.vue`.** The employee page
  (`Personal/Show.vue`) and the admin employee form (`Employees/Form.vue`)
  both use this component. Admins also enter holidays, so they also see
  the hint. The text uses "you", the same as the existing
  `availability.start_date.hint`.
- **Show the hint below the table.** Use small secondary text, the same
  style as the other hints.
- **Always show the hint.** Show it also when the list is read-only
  (locked or archived). A person who reads an existing holiday must also
  know that the end date is included.
- **Use the short text.** Do not add an example.
- **Add the i18n key `availability.holidays.hint`** in `en.json`.

## Non-goals

- No change to how the system stores or validates holidays.
- No change to the date inputs or the column labels.
