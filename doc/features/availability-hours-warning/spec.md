# Availability Hours Warning — Spec

## Problem

An employee can select recurring availability that provides fewer hours
than their weekly working-hours target. The personal page does not show
this mismatch. The employee can then expect a schedule that cannot meet
their target.

## Solution

Show one persistent inline warning on the Availability tab of the personal
employee page and the admin employee page when preferred available hours
are less than weekly working hours.

The warning state depends on the available hours:

- If preferred available hours plus not-preferred hours meet the target,
  show that the plan will use not-preferred hours.
- If all available hours are below the target, show the available hours and
  the weekly-hours target.

The warning does not prevent the employee from saving either value. It
updates after the employee changes weekly hours or recurring availability.

## Key Decisions

- Use recurring availability only. Holidays and other date-specific
  changes do not change the warning.
- Treat Available as preferred availability. Treat Not preferred as
  available time that does not meet the employee's preference.
- Show no warning when preferred available hours meet or exceed the target.
- Show the not-preferred warning when preferred available hours are below
  the target and all available hours meet or exceed it.
- Show the insufficient-capacity warning when all available hours are below
  the target. It shows the total available hours and the target only.
- Hide the warning when weekly working hours are `0`. No target applies in
  this state.
- Show the same warning on the personal employee page and the admin
  employee page. The two pages use the same calculation and wording.
- Keep the warning non-blocking. Employees can intentionally provide less
  availability than their target.

## Non-goals

- Reject or prevent an availability update.
- Change availability data, shift definitions, or weekly-hours choices.
- Count holidays, leave, assigned shifts, or other date-specific data.
- Add the warning to scheduling views.