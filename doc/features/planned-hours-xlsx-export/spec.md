# Planned Hours Excel Export

## Problem

The planned-hours report exports a CSV file. The file uses a comma as the
field separator and a dot as the decimal separator. Excel reads a CSV with
the separators of the user's language settings. With Dutch settings, Excel
does not split the columns. It also reads `7.5` as text or as the date
7 May.

A `sep=,` first line fixes only the field separator. It does not fix the
decimal separator. It also makes Excel ignore the UTF-8 byte order mark,
and other tools read the line as data.

## Solution

The export writes an `.xlsx` file instead of a CSV file. The file stores
typed cells. Excel shows each value with the language settings of the user.

- **Date** is a real Excel date cell with the number format `yyyy-mm-dd`.
- **Hours** is a numeric cell with the number format `0.00`.
- The column headers come from the `reports.planned_hours.column.*` keys.
- The sheet name comes from the `reports.tab.planned_hours` key.
- The file name is `planned-hours.xlsx`.
- The export button label is "Export Excel".

The rows do not change. The export covers the full date range, sorted by
date ascending, without pagination. The route URL and route name do not
change.

## Key decisions

- **Excel file, not CSV.** A CSV cannot match every locale, because the
  decimal character is fixed when the server writes the file. Typed cells
  in an `.xlsx` file have no separator problem.
- **Replace the CSV.** One export button keeps the UI and the code simple.
  Other tools (pandas, Power Query, LibreOffice) also read `.xlsx`.
- **`openspout/openspout`.** A small streaming writer with typed cells and
  number formats. PhpSpreadsheet is too large for a three-column export.
- **ISO date display.** The date sorts and filters as a date. It looks the
  same for all users and matches the report on screen.
- **Always two decimals.** The column aligns, and it matches the rounding
  to two decimals that the report already does.
- **Fixed file name.** The file name stays the same as before, with a new
  extension.

## Non-goals

- A CSV export next to the Excel export.
- A date range in the file name.
- Styling, formulas, totals, or other sheets.
- Excel export for the other reports.
