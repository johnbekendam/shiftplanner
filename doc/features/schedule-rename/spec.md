# Schedule Rename — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The page that shows the published shifts per week is called "Roster".
The user wants the name "Schedule". The name "Schedule" became free when
the old Schedule page became Demand (see `doc/features/demand/`).

## Solution

"Roster" becomes "Schedule" in the UI and in the code.

- **Labels.** The navigation, the page title and the Settings screens
  text: "Schedule link", its help text, the regenerate dialog and the
  flash message.
- **Code.** The pages (`Schedule.vue`, `SchedulePublic.vue`), the
  component (`ScheduleView.vue`), the controllers (`ScheduleController`,
  `SchedulePublicController`), the service (`ScheduleWeek`), the i18n
  keys (`schedule.*`), the route names and the tests.
- **URLs.** The page moves to `/schedule`. The public link moves to
  `/schedule/{token}`. Settings shows the new public URL. `/roster`
  redirects to `/schedule`, and `/roster/{token}` redirects to
  `/schedule/{token}`.

The redirect from `/schedule` to `/demand` is removed, because
`/schedule` is now the Schedule page.

## Key decisions

- **Full rename, as for Demand.** Code names match the UI.
- **Redirects for the old URLs.** People outside the app use the public
  link. Old links must continue to work.
- **The `/schedule` to `/demand` redirect goes.** It existed for less
  than a day. The new Schedule page needs the URL.
- **The data layer keeps its names.** The column
  `planning_settings.roster_token`, the `PlanningSettings` token methods
  and the backup archive format do not change. This prevents a
  migration and keeps old backups compatible.

## Non-goals

- Database migrations.
- Changes to the behavior of the page or the public link.
- Changes to message templates that users wrote, for example a subject
  that contains "Roster".
