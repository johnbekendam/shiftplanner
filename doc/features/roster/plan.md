# Roster — Plan

Status: in progress — 3/5

- [x] 1. Route and controller: `GET /roster` for all logged-in users
  renders `Roster` with the week (`?week=`, current ISO week by
  default), its seven days, and today. A guest is sent to login.
- [x] 2. Rows: one row for each employee with a published assignment in
  the week, sorted by name, with the business line abbreviation and
  the assignments (shift, workcenter) for each day. Unpublished
  (week, workcenter) pairs are left out.
- [x] 3. Business line filter on the server: the `business_lines` query
  of the Employees page, with "none". Extract the shared parse logic
  from `EmployeeController` so both pages use it.
- [ ] 4. Page: `Roster.vue` in `AppLayout` with the week grid, the muted
  dash for an empty day, the highlight for today, the empty state,
  and previous/next week buttons. Add "Roster" to the sidebar after
  "Employees". All text in `en.json`.
- [ ] 5. Business line filter on the page: extract the Employees filter
  menu and its once-per-tab default into a shared component, and use
  it on the Employees page and the Roster page.
