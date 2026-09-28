# Roster Public Link — Spec

## Problem

The Roster page (`features/roster/`) needs a login. A manager wants to
send the roster to people who have no account.

## Solution

One secret link opens the roster without a login, like the workcenter
live screens.

### Access

- The page is at `/roster/{token}`. It needs no login.
- An unknown token returns 404.
- The response has `noindex` and no-cache headers.

### Token

- One token for the whole application, on the `planning_settings` row.
- The application makes the token the first time it needs it.
- An admin can regenerate the token. The old link then stops at once.

### Link admin

- The Settings General tab has a "Roster link" section. It shows the
  URL, a copy button, and a "Regenerate link" action with a confirm.
- Only an admin sees the section and can regenerate the link.

### What the page shows

- The same week grid as `/roster`: published assignments only, one row
  for each planned employee, shift and workcenter in each cell.
- The page uses `LiveLayout`, with no sidebar and no navigation.
- Week navigation and the business line filter work as on `/roster`.
- The filter has no default, because no user is logged in.
- The page does not refresh automatically.

## Key decisions

- **One global link.** It is simple to send and to manage. Anyone with
  the link sees the roster of all business lines.
- **Token on `planning_settings`.** That row already holds the global
  settings. No new table is necessary.
- **Admin only on Settings.** The link gives access without a login,
  so only an admin manages it.
- **Same controls, no refresh.** People open the link on their own
  device and look ahead or at one team. It is not a wall screen.

## Non-goals

- A link for each business line.
- Link expiry or a record of who opened the link.
- Automatic refresh.
