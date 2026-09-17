# Employees Default Business Line Filter - Plan

Status: done - 6/6

## Steps

- [x] Find existing Employees page filter tests and auth prop patterns.
- [x] Add failing tests for first-load default filtering.
- [x] Add the tab-scoped default filter behavior to the Employees page.
- [x] Make sure explicit filter state prevents the default from applying again.
- [x] Keep the feature documentation current.
- [x] Run focused tests, then run the full test suite and build.

## Verification

- `npx vitest run tests/js/EmployeesIndex.test.js`: failed before implementation because the default filter did not run and explicit query state was not marked applied.
- `npx vitest run tests/js/EmployeesIndex.test.js`: passed after implementation.
- `php artisan test`: passed.
- `npx vitest run`: passed.
- `npm run build`: passed.
