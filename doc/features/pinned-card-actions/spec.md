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

The button row moves into the `Card` footer slot. It keeps the footer
top border, but uses the body background (the new `Card` `footerClass`
prop). The separator above the buttons goes.

- Employee edit page: the footer holds Cancel and Save. Delete sits at
  the bottom of the Details tab, below a separator, so it shows only on
  that tab. For an archived employee there is no Delete, and the footer
  holds only Restore, for an admin. A manager sees
  no footer on an archived employee.
- Personal page: the footer holds Cancel and Save. Withdraw sits at the
  bottom of the Details tab, below a separator, so it shows only on that
  tab. When employee changes are locked, the page has no Withdraw, no
  buttons and no footer.

The layouts get this behavior as an option:

- `AppLayout` gets a `fitHeight` prop. The page content then fills the
  height of the page area instead of growing past it.
- `CenteredLayout` gets a `fitHeight` prop, a `footer` slot, and a
  `scrollKey` prop. A change of `scrollKey` scrolls the body to its top.

### Save per tab

Cancel and Save act on the current tab only:

- A tab with unsaved changes cannot be left without a decision. A click
  on another tab opens a dialog: "Stay" (the default) or "Discard
  changes". Discard resets the changes and then opens the other tab.
  Because of this rule, unsaved changes always belong to the open tab.
- The footer with Cancel and Save shows only when there is something to
  save, while saving, and for the short "Saved" confirmation after a
  save. Tabs without editable data (Information, Planning) never show it.
- For an archived employee, the footer with Restore shows as before.

## Key decisions

- **Max height, not fixed height.** A short tab does not get a tall
  empty card.
- **The card footer holds the buttons.** This matches the `Card`
  component and separates the fixed row from the scrolling body.
- **Back to the top on a tab switch.** One body scrolls for all tabs, so
  a new tab must not open halfway down.
- **One save per page, but only one tab can hold changes.** Blocking the
  tab switch makes the page Save act on the open tab, without a separate
  save per tab.
- **A dialog on a blocked tab switch.** A click that does nothing would
  look broken.
- **Opt-in layout props.** Other pages that use the two layouts do not
  change.

## Non-goals

- The create-employee form. It has no tabs and its Create button stays
  in the form.
- A scroll position per tab.
- Other pages with long cards.
