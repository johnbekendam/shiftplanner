# Max Hours Rounding

## Problem

The `max_hours_per_week` rule caps an employee at `weekly_hours × 2` over
the 14-day planning cycle. The check uses the real start-to-end duration of
each shift. Shifts are slightly longer than 8 hours: Morning and Day are
8.25 hours, and Evening is 9 hours because it includes a dinner break.

An employee with 8 weekly hours has a 16-hour cap. Two shifts add up to more
than 16 hours, so the planner cannot assign a second shift in the cycle.
The same problem gets larger for employees who work several evening shifts.

## Solution

The max-hours cap counts each shift at a rounded duration. The duration
rounds to the nearest multiple of 4 hours:

- A tie rounds up. For example, 6 hours counts as 8, and 10 hours counts as 12.
- A shift always counts as at least 4 hours.

Examples: 8.25 hours and 9 hours both count as 8. An employee with 8 weekly
hours gets 2 shifts per cycle. An employee with 40 weekly hours gets 10.

The rounded duration applies in all three max-hours checks:

- the autoplanner hard rule (`PlanEligibility`)
- the autoplanner soft-rule cost (`PlanScorer`)
- the manual-planning hard cap (`SchedulingEligibility`)

The rule hint on the planning-rules page mentions the rounding.

## Key decisions

- **Round, do not add a margin.** A fixed margin still fails across several
  9-hour evening shifts. Rounding counts each shift as a whole block.
- **Round only for the cap.** Equal-workload fairness, the greedy candidate
  order, reports, and the CSV export keep the real hours. This limits the
  impact of the change.
- **A fixed 4-hour step.** The step is a code constant. There is no
  migration, setting, or form field.
- **Ties round up, with a minimum of 4 hours.** The cap stays on the strict
  side, and a short shift never counts as zero.

## Non-goals

- A stored "accounted hours" value per shift.
- A configurable step or margin.
- Changes to reports, fairness, or any other use of shift duration.
