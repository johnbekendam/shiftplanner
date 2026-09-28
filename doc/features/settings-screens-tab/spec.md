# Settings Screens Tab — Spec

## Problem

The links that open a page without a login are in two locations. The
roster link is on the General tab. The live-screen links are a column
on the Workcenters tab. An admin who wants to send or regenerate a link
must know where each one is.

## Solution

A new Settings tab, **Screens**, holds all these links.

- The tab is the last tab, after Information.
- The tab shows one list:
  1. The **Roster link** row, unchanged: title and help text on the
     left, copy and regenerate buttons on the right.
  2. A **Live screens** heading with a help line. Then one row for each
     active workcenter, in workcenter order: the name on the left, copy
     and regenerate buttons on the right. Regenerate asks for a confirm.
- If no active workcenter exists, the live screens part shows an empty
  message.
- The tab has no save bar. Copy and regenerate act at once.
- The tab shows saved workcenters only. A new workcenter gets its row
  after the Workcenters tab is saved.
- The Workcenters tab no longer has the "Live screen" column.
- The General tab no longer has the roster link section.

## Key decisions

- **One list, roster on top.** It uses the row style of the roster
  link. All links look and work the same.
- **Last tab.** Links change rarely. The configuration tabs keep their
  positions.
- **Saved workcenters only.** An unsaved workcenter has no token yet.
- **No backend change.** The endpoints and tokens stay the same. Only
  the Settings page layout changes.

## Non-goals

- Changes to the live-screen page or the roster page.
- Links for archived workcenters.
- New link types.
