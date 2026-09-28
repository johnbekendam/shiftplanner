# Settings General Information — Spec

## Problem

The Settings Information tab holds only the shift information note.
One small tab for one field makes the tab row longer than necessary.

## Solution

- Move the shift information note form to the General tab, below the
  period form.
- Remove the Information tab.
- The General tab has one save bar for both forms. Save sends each
  changed form to its own endpoint, one after the other. Cancel reverts
  both forms.
- The General tab shows the dirty dot when one of the two forms has
  changes.

## Key decisions

- **One save bar.** The Shifts tab already saves two forms (the shift
  list and the schedule note) with one save bar. The General tab does
  the same.
- **One request after the other.** A new Inertia visit cancels the
  visit that runs. The period form saves first, then the note.
- **No backend change.** The endpoints stay the same.

## Non-goals

- Changes to the note content or the period settings.
