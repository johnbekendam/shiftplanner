# Workcenter Live Planning — Plan

Status: in progress — 2/5

Each step is one commit on the `work` branch. Each step starts with a
failing test and ends with a green full suite.

- [x] 1. Token on the workcenter. A migration adds `workcenters.live_token` and fills it for existing rows. A new workcenter gets a token when it is created. The token is not in `toPayload()` except for the Settings page.
- [x] 2. Live data endpoint. `GET /live/{token}` returns the Inertia page `Live` with two week blocks (current and next ISO week). Each block has the shifts of the workcenter, the days Monday to Friday, the assigned names, the open-spot count, and a `published` flag. An unpublished week has no assignments in the props. An unknown token and an archived workcenter return 404. The response has `noindex` and no-cache headers. The route needs no login.
- [ ] 3. Regenerate and copy on Settings. `POST /settings/workcenters/{workcenter}/live-token` (admin only) makes a new token, and the old URL returns 404. The Workcenters settings row shows the live-screen link with a copy button and a "Regenerate link" action. Add the `en.json` keys.
- [ ] 4. Live page and `LiveLayout.vue`. The chrome-free layout shows the workcenter name and the last-updated time. `Live.vue` shows the two week grids: names, muted "open" placeholders, a dash for zero capacity, the highlight on today's column, and the "not published yet" message. Add the `en.json` keys.
- [ ] 5. Auto refresh. The page runs an Inertia reload of its props every 60 seconds and updates the last-updated time on success. If a reload fails, the page keeps the last data. The timer stops when the page unmounts.
