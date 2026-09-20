# Workcenter Live Planning — Spec

## Problem

Staff at a workcenter have no easy way to see the planning of the week.
They must ask a manager or open their personal page. A manager wants a
screen near each workcenter that always shows the current planning,
with no one operating it.

## Solution

Each workcenter gets a live-planning page. A screen or kiosk browser
opens the page and leaves it open. The page reloads its data every 60
seconds.

### Access

- Each workcenter has a secret token. The page is at `/live/{token}`.
  It needs no login. This follows the `/personal/{token}` pattern.
- An unknown token returns 404. A token of an archived workcenter also
  returns 404.
- The response carries `noindex` and no-cache headers.

### Link admin

- Each workcenter row on Settings > Workcenters shows the live-screen
  link, with a copy button and a "Regenerate link" action.
- Regenerate makes a new token. The old URL stops working at once.
- A migration adds `workcenters.live_token` and fills it for existing
  workcenters. A new workcenter gets a token when it is created.

### What the page shows

- Two week blocks, stacked: the current ISO week and the next week.
  The current week changes on its own on Monday.
- A week block shows only if a manager published that week for this
  workcenter (`published_weeks`). Otherwise the block shows a
  "not published yet" message.
- The grid has one row for each shift of the workcenter. It has one
  column for each day, Monday to Friday.
- Each cell lists the full names of the assigned employees, one for
  each line. It shows a muted "open" placeholder for each unfilled
  spot. Capacity comes from `Workcenter::spotsFor()`. A cell with zero
  capacity shows a dash.
- The column of today has a highlight.
- The page is read-only. It shows no fixed marks and no competences.

### Refresh

- The page reloads its data with an Inertia reload every 60 seconds.
  It does not reload the whole page.
- If a reload fails, the page keeps the last data on screen.
- The header shows the workcenter name and the time of the last
  successful update.

### Layout

- A new `LiveLayout.vue` has no sidebar and no navigation. It fills the
  viewport and is sized for a large screen.
- Colors use the existing `--color-*` tokens. All text is in
  `resources/lang/en.json`.

## Key decisions

- **Secret URL, not login.** A wall screen cannot keep a login session.
  A token URL works with any kiosk browser. A manager can regenerate a
  leaked link.
- **Published weeks only.** The screen matches what employees see on
  their personal page. Staff never see a roster that still changes.
- **Current and next week.** The screen stays useful on Friday
  afternoon and needs no input.
- **Names and open spots.** Open spots show where the workcenter has a
  gap. An empty cell would look the same as a day with no shift.
- **New layout.** `AppLayout` shows navigation to a visitor who is not
  logged in. `CenteredLayout` is too narrow for two week grids.
- **Token on `workcenters`.** One token for each workcenter. A screen
  has no pairing step and no per-screen token.

## Non-goals

- Week navigation controls on the page.
- Draft weeks on the page.
- Competences or fixed marks in a cell.
- Per-screen tokens or screen pairing.
- One screen for several workcenters.
- Push updates (websockets). The page polls.
