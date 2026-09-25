# Alternating Shift Weekly Exclusion — Spec

## Problem

The `alternating_shift_pair` rule is always soft. The planner compares
each day with the same weekday 7 days earlier. If that day had one
shift of the pair, the planner prefers the other shift. It costs a
severity when the plan repeats the shift.

In use, this is not the behavior that the managers need. An employee
must not work both shifts of a pair in the same week. The soft rule
does not prevent this, and manual planning ignores the rule.

## Solution

An alternating shift pair becomes a hard weekly exclusion. An employee
cannot hold both shifts of a pair in the same week. The week is the
Monday–Sunday calendar week. The rule counts assignments across all
workcenters.

- **Automatic planning.** `PlanEligibility` rejects a candidate when the
  employee already holds the other shift of a pair in the same week.
  The greedy and hill-climb phases both use this check.
- **Manual planning.** `SchedulingEligibility` reports a violation for
  the proposed assignment. The eligible-employee endpoint excludes the
  employee. The assignment endpoint rejects a direct request with a
  validation error on `employee_id`.
- **Old behavior.** The planner removes the week-to-week preference.
  `PlanScorer` has no alternating-pair cost. `PlanSoftRules` does not
  read the pair. The planner no longer loads previous-week assignments.
- **Data.** A pair row stores `mode = hard` and `severity = null`. A
  migration updates the existing rows. The controller always writes
  these values and ignores client-supplied mode and severity.
- **Rules page.** A pair row shows `Hard` as its mode and no severity
  input. The add form asks for the two shifts only. A hint explains
  that the two shifts cannot be combined in the same week.

## Key decisions

- **Always hard.** The managers need a guarantee, not a preference. The
  rule has no mode or severity choice.
- **Manual planning enforces the rule.** This matches the hard caps in
  `manual-planning-hard-caps`. Manual and automatic planning then agree
  on what is allowed.
- **Manual checks count every assignment in the week.** All statuses
  and all workcenters count, the same as the manual hard caps.
- **Monday–Sunday week.** This matches the week of the max-hours
  distribution check. Each 2-week planning cycle holds two whole
  weeks, so the planner needs no data from outside the cycle.
- **The old soft preference goes.** The weekly exclusion replaces it. No
  week-to-week alternation preference remains.
- **One pair per shift stays.** The duplicate-shift validation does not
  change. The unordered pair stays fixed after creation.
- **The name stays.** The type string and the UI label stay
  `alternating_shift_pair` / "Alternating shift pair".

## Non-goals

- Flagging or fixing existing violations in stored plans.
- Letting a shift belong to more than one pair.
- A soft or configurable version of the rule.
- Any change to other rule types.
