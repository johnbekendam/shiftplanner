# Roster Public Link — Plan

Status: done — 3/3

- [x] 1. Public route: `GET /roster/{token}` without login renders the
  roster data of `/roster` as page `RosterPublic`, with `noindex` and
  no-cache headers. The token is on `planning_settings` and is made on
  first use. An unknown token returns 404. Share the data build with
  `RosterController`.
- [x] 2. Regenerate: `POST /settings/roster-token` (admin only) makes a
  new token. The old URL returns 404. The Settings page gets the roster
  URL as a prop.
- [x] 3. Front end: `RosterPublic.vue` in `LiveLayout` reuses the roster
  grid (extract it from `Roster.vue`), with week navigation and the
  filter, and no default filter. The Settings General tab shows the
  "Roster link" section with copy and regenerate. All text in `en.json`.
