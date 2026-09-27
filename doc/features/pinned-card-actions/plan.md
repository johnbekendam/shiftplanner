Status: complete — 5/5

- [x] 1. Add a `fitHeight` prop to `AppLayout` and to `CenteredLayout`,
  and a `footer` slot to `CenteredLayout`. With `fitHeight`, the card is
  at most as tall as the page area, and its body scrolls. Vitest.

- [x] 2. Employee edit page: use `fitHeight`, cap the card to the page
  area with a scrolling body, move the button row to the card footer,
  and scroll the body to the top on a tab switch. Vitest.

- [x] 3. Personal page: use `fitHeight`, move the button row to the
  `CenteredLayout` footer, and scroll the body to the top on a tab
  switch. Vitest.

- [x] 4. Personal page: show the footer only with unsaved changes, while
  saving, or during the "Saved" confirmation. Block a tab switch with
  unsaved changes behind a Stay / Discard changes dialog. Vitest.

- [x] 5. Employee edit page: the same footer rule and tab-switch dialog.
  An archived employee keeps the Restore footer. Vitest.
