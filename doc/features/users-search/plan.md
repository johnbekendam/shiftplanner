# Users Search - Plan

Status: done - 6/6

## Steps

- [x] Find existing Employees search patterns and Users tests.
- [x] Add failing backend tests for Users search by name and email.
- [x] Add failing Vue tests for the Users search field and debounce behavior.
- [x] Add server-side Users search handling.
- [x] Add the Users search input and query reload behavior.
- [x] Run focused tests, then run the full test suite and build.

## Verification

- `php artisan test --filter=test_user_list_search_filters_by_name_or_email`: failed before implementation because the user list was unfiltered.
- `npx vitest run tests/js/UsersIndex.test.js`: failed before implementation because the search input was missing.
- `php artisan test --filter=test_user_list_search_filters_by_name_or_email`: passed after implementation.
- `npx vitest run tests/js/UsersIndex.test.js`: passed after implementation.
- `php artisan test`: passed.
- `npx vitest run`: passed.
- `npm run build`: passed.
