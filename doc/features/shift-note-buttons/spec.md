# Shift Note Buttons

## Problem

Administrators can use Markdown in the shift information text shown on employee and personal pages, but button syntax currently works only in composed email messages.

## Solution

Support the existing universal button syntax in shift information text:

- `:button[Button text](target)` renders a styled web button.
- `:button[Button text](:link)` is not required for shift notes because shift notes do not resolve the personal-link placeholder.

Reuse the shared button syntax parser while keeping email table markup and web page markup separate. The existing email behavior must remain unchanged.

## Key Decisions

- Keep `shiftNoteHtml()` as the shared server-side rendering entry point for both employee and personal pages.
- Reuse the button syntax and URL safety rules used by composed emails.
- Render a normal responsive anchor for web pages, styled through the existing theme tokens and the shared `ShiftNote` component.
- Keep ordinary Markdown links unchanged.
- Preserve existing Markdown features and raw HTML behavior.

## Non-goals / Scope Boundaries

- Do not change the settings editor syntax or validation beyond accepting the existing button notation.
- Do not change email button rendering.
- Do not add button alignment, size, or color options.
- Do not add personal-link placeholder resolution to shift notes.
