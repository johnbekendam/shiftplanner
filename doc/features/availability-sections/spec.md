# Availability Sections — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The Availability tab on the personal page and on the employee edit page
hides the default week. The user must click a weekday letter in the
calendar header to see it. Many users do not find it. The date settings
share one card with the default week, below the calendar, and the legend
takes a separate info card.

## Solution

Both pages use the same Availability tab with four sections, from top to
bottom:

1. **Start date and weekly hours.** No change. The hours warning stays
   below them.
2. **Default availability.** A card with the header "Default
   availability". It always shows the Monday–Sunday grid. The schedule
   note sits at the bottom of this card, below a separator.
3. **Specific availability.** A row with two cards:
   - Left: the calendar card, as wide as its content. The legend is in
     the calendar footer, below the month grid. The separate info card
     is removed.
   - Right: the date card. It takes the remaining width. With no date
     selected, the header shows "Specific availability" and the body
     shows the hint "Click a date to change its availability". With a
     date selected, the header shows the date, and the body shows the
     date grid and the reset button as they are now. The "Block the
     whole day" checkbox sits at the bottom of the body, below a
     separator. On a holiday the body shows only the holiday notice, as
     it does now.
   - On narrow screens the two cards stack.
4. **Holidays.** No change.

The weekday letters in the calendar header are plain labels. They have no
hover, no selection and no click action. A click on a day selects it. A
second click on the same day deselects it.

## Key decisions

- **The default week is always visible.** It is the most important
  availability data. A hidden grid is easy to miss.
- **The weekday header has no action.** The default week is already on
  the screen, so a second way to reach it adds code without value.
- **The date card is always present.** The layout does not jump, and
  the hint tells the user that dates are clickable.
- **One card per block.** This matches the holidays card. The calendar
  card keeps its current look.
- **The schedule note belongs to the default week.** The note is about
  the shift schedule, and the default card is always visible.

## Non-goals

- Changes to saving, pending edits, the resolver or the backend.
- Changes to the calendar colors, the legend entries or the date grid.
- Changes to the start date, weekly hours, hours warning or holidays.
