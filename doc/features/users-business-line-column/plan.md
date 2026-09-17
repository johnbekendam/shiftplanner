# Users Business Line Column - Plan

Status: done - 6/6

## Steps

- [x] Find existing user index payload and table tests.
- [x] Add failing tests for the Users table business line column.
- [x] Include each user's business line abbreviation in the index payload.
- [x] Render the new Business line column in the Users table.
- [x] Add language keys for the column and empty value.
- [x] Run the focused tests, then run the full test suite.

## Verification

- `php artisan test --filter=test_user_list_includes_business_line_abbreviations`: failed before implementation because `users.0.business_line` was missing.
- `npx vitest run tests/js/UsersIndex.test.js`: failed before implementation because the Business line header was missing.
- `php artisan test --filter=test_user_list_includes_business_line_abbreviations`: passed after implementation.
- `npx vitest run tests/js/UsersIndex.test.js`: passed after implementation.
- `php artisan test`: passed.
- `npx vitest run`: passed.
- `npm run build`: passed.
