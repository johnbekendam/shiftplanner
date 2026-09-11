# Shift Note Spacing

## Problem

Paragraphs in rendered shift notes and schedule notes sit close together
(`[&_p]:my-1`, 0.25rem). Administrators writing longer notes have no way to
add visual breathing room, either as a general improvement to the default
rhythm or as a deliberate break between sections of a note.

## Solution

- Increase the default paragraph gap in the shared `ShiftNote` component
  from `my-1` to `my-2`.
- Add a new Markdown marker, `:---`, on its own line, that renders as a
  fixed extra vertical gap wherever the author places it. It follows the
  same preprocessing pattern as the existing `:button[Label](target)`
  syntax in `MarkdownRenderer`: extracted before conversion, replaced with
  a placeholder element after.
- Each `:---` occurrence renders its own independent spacer block, so
  repeating the marker stacks the space with no extra counting logic.

## Key Decisions

- `:---` lives in the shared `MarkdownRenderer` so it's recognized (and
  stripped to a harmless empty element) everywhere Markdown is rendered,
  but visible styling for it is added only to the `ShiftNote` component.
  Both shift notes and schedule notes render through that one component,
  so both get the feature; message-template emails (rendered through the
  same `MarkdownRenderer` but a separate, inline-styled Blade layout) do
  not — no inline-style spacer is added there.
- One `:---` renders roughly 1rem of extra space — 2x the new default
  paragraph gap, enough to read as an intentional break.
- `shifts.note_hint` and `shifts.schedule_note_hint` mention the new
  syntax so administrators can discover it without reading source code.

## Non-goals / Scope Boundaries

- Do not add a sized/configurable variant (e.g. `:spacer:2`) — stacking
  the marker covers that need.
- Do not make `:---` produce visible spacing in message-template emails.
- Do not change spacing for other block elements (headings, lists,
  tables) — only paragraph spacing and the new spacer marker.
