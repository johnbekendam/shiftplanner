# Planning Verification — Plan

Status: in progress — 1/4

- [x] 1. Endpoint: `GET /planning/verify?week_start=` returns the violations of each assignment in the week. A new `PlanningVerifier` service does the single-assignment checks: `unconfirmed`, `archived`, `holiday`, `unavailable`, `not_preferred`, `workcenter_ineligible`, `shift_hidden`, `competence_required`, `business_line_preference`.
- [ ] 2. Group checks in `PlanningVerifier`: `overlap`, `max_shifts_per_day`, `max_hours_per_week`, `max_hours_per_week_distribution`, `alternating_shift_pair`, `cell_overfilled`. Each marks all involved assignments.
- [ ] 3. Planning page: a **Verify planning** button gets the result. Violating badges turn red, and their tooltips list each violation. A result line shows the count or "No violations found".
- [ ] 4. Planning page: while verification is on, the page verifies again after a week change and after the week cells reload.
