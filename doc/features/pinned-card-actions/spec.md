# Pinned Card Actions — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The employee edit page and the personal page show one tabbed card. The
Cancel and Save buttons are the last part of the card body. On a long tab,
for example Availability, the buttons are below the screen. The user must
scroll down to find them, and can forget unsaved changes.

## Solution

On both pages the card is never taller than the visible page area:

- The card header (the tabs) and the card footer (the buttons) always
  show.
- The card body scrolls when its content is too tall.
- A short tab keeps a short card. The card shrinks to its content.
- A tab switch shows the new tab from its top.

The button row moves into the `Card` footer slot. It gets the footer
background and top border. The separator above the buttons goes.

- Employee edit page: the footer holds Delete, Cancel and Save. For an
  archived employee it holds only Restore, for an admin. A manager sees
  no footer on an archived employee.
- Personal page: the footer holds Withdraw, Cancel and Save. When
  employee changes are locked, the page has no buttons and no footer.

The layouts get this behavior as an option:

- `AppLayout` gets a `fitHeight` prop. The page content then fills the
  height of the page area instead of growing past it.
- `CenteredLayout` gets a `fitHeight` prop, a `footer` slot, and a
  `scrollKey` prop. A change of `scrollKey` scrolls the body to its top.

## Key decisions

- **Max height, not fixed height.** A short tab does not get a tall
  empty card.
- **The card footer holds the buttons.** This matches the `Card`
  component and separates the fixed row from the scrolling body.
- **Back to the top on a tab switch.** One body scrolls for all tabs, so
  a new tab must not open halfway down.
- **Opt-in layout props.** Other pages that use the two layouts do not
  change.

## Non-goals

- The create-employee form. It has no tabs and its Create button stays
  in the form.
- A scroll position per tab.
- Other pages with long cards.
