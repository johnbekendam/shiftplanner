# Planning Verification — Spec

## Problem

A stored planning can break hard constraints. A manager can add a rule
after assignments exist. An employee can add a holiday after being
assigned. A manager can lower the spots of a cell. The planning page
does not show these problems, so the manager cannot find and fix them.

## Solution

A **Verify planning** button on `/planning` checks every assignment in
the visible week. Each assignment that breaks a hard constraint gets a
red badge. The badge tooltip lists each violation on its own line, below
the normal state text. A line next to the button shows the result:
"N assignments break a rule" or "No violations found".

### Checks

The check finds every violation of an assignment, not only the first.

| Code | Violation | Marked assignments |
| --- | --- | --- |
| `unconfirmed` | The employee is not confirmed. | This one |
| `archived` | The employee is archived. | This one |
| `holiday` | The employee is on holiday that day. | This one |
| `unavailable` | The availability grid marks the cell unavailable or has no row. | This one |
| `not_preferred` | The cell is not preferred and the `not_preferred_shift` rule is hard. | This one |
| `workcenter_ineligible` | The employee is not a member of the workcenter. | This one |
| `shift_hidden` | The shift is hidden and not enabled for the employee at this workcenter. | This one |
| `competence_required` | A hard rule requires a competence that the employee does not have. | This one |
| `business_line_preference` | A hard rule requires another business line. | This one |
| `overlap` | Another same-day assignment of the employee overlaps in time. | Both |
| `max_shifts_per_day` | The employee holds more shifts that day than the hard cap. | All that day |
| `max_hours_per_week` | The cycle total is more than 2 × `weekly_hours`. | All of the employee in the week |
| `max_hours_per_week_distribution` | The week total is more than `weekly_hours` + 4. | All of the employee in the week |
| `alternating_shift_pair` | The employee holds both pair shifts in the week. | All pair shifts in the week |
| `cell_overfilled` | The cell has more assignments than spots. | All in the cell |

The checks count assignments in all workcenters. The hour checks use the
same cap hours and the same 2-week cycle as the manual hard caps.

### Flow

1. The manager clicks **Verify planning**.
2. The page gets `GET /planning/verify?week_start=Y-m-d`. The response
   lists the violations of each assignment ID.
3. Verification stays on until a full page reload. When the week
   changes, or the week cells reload after an edit, the page verifies
   again.

## Key decisions

- **All hard constraints.** Hard planning rules and basic eligibility
  both make a planning invalid. Soft rules stay out, because the
  planner can break them on purpose.
- **The visible week only.** The manager fixes what the screen shows.
  The cycle hour check still counts the whole cycle. The check covers
  all workcenters of the week, also the ones that the filter hides. The
  result line counts them too.
- **Mark all involved assignments.** The manager decides which one to
  change. The tool does not guess.
- **Re-verify after each change.** A fixed assignment loses its red
  badge at once.
- **Red overrides the state color.** The red uses the existing
  `--color-badge-error-*` tokens. The tooltip still shows the state.
- **A new service owns the checks.** It returns all violations for a set
  of existing assignments. The existing manual check stays as it is,
  because it tests one proposed assignment and stops at the first failure.

## Non-goals

- Soft-rule violations.
- Verifying weeks other than the visible week.
- Automatic fixes.
- Blocking publish or send when violations exist.
- Adding the competence and business-line checks to manual assignment.
