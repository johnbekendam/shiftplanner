Status: complete - 8/8

- [x] 1. Add the audit event schema, model, recorder, and core employee create,
  update, and confirmation events. Test changed values, actor snapshots, and
  immutable event records. Commit as `feat: audit core employee changes`.
- [x] 2. Add employee archival and administrator restoration. Replace single
  and bulk deletion with archival, preserve related data, and test role access
  and one event per employee. Commit as `feat: archive and restore employees`.
- [x] 3. Add Active, Archived, and All employee list filters. Make archived
  employee forms read-only and add the administrator restore action. Test the
  list, form, archive, and restore flows. Commit as
  `feat: add archived employee management`.
- [x] 4. Exclude archived employees from planning, scheduling choices, and
  operational selectors. Block personal-link writes, signup, and account
  linking until an administrator restores the employee. Test each boundary.
  Commit as `feat: suspend archived employees`.
- [x] 5. Audit manager and administrator changes to holidays, recurring
  availability, competences, workcenters, and question answers. Test create,
  update, and removal values. Commit as `feat: audit employee configuration`.
- [x] 6. Audit public signup, personal-link changes, account linking, and
  employee changes from imports. Test typed sources and the strongest
  available actor identity. Commit as `feat: audit employee change sources`.
- [x] 7. Add the employee audit timeline for authorized users. Show newest
  events first with expandable changed values, including archived employees.
  Test authorization and payload formatting. Commit as
  `feat: show employee audit timeline`.
- [x] 8. Add the administrator audit page with employee and actor search,
  action and source filters. Keep audit events outside application
  backup exports and restores. Test filters, authorization, retention, and
  backup exclusion. Commit as `feat: add employee audit log`.