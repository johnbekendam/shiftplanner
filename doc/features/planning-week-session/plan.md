Status: complete - 3/3

- [x] 1. Persist the selected planning week in per-user `sessionStorage`. Restore it on plain planning-page visits, let explicit dates replace it, and discard invalid stored values. Test navigation, refresh behavior, URL precedence, user isolation, and fallback behavior. Commit as `feat: persist selected planning week`.
- [x] 2. Persist selected workcenters in per-user `sessionStorage`. Preserve empty selections, remove stale IDs, select new workcenters, and fall back to all workcenters when storage is invalid. Test restore, updates, reconciliation, user isolation, and fallback behavior. Commit as `feat: persist selected planning workcenters`.
- [x] 3. Persist selected shifts in per-user `sessionStorage`. Preserve empty selections, remove stale IDs, select new shifts, and fall back to all shifts when storage is invalid. Test restore, updates, reconciliation, user isolation, and fallback behavior. Commit as `feat: persist selected planning shifts`.
