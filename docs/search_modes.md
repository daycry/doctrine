# DataTables Search Modes

This document describes supported per-column search operators for the DataTables + Doctrine integration.

## Key Concepts

- Prefixes are case-insensitive and search terms are trimmed.
- Operators are applied per column via the DataTables request `columns[*].search.value`.
- Unknown operators default to LIKE '%term%'.

| Mode                | Pattern                   | Description                                                                                         |
|---------------------|---------------------------|-----------------------------------------------------------------------------------------------------|
| LIKE '%…%' (default)| `[%]term` or `term`       | LIKE '%term%' search; any part of the term may match a value in the column.                         |
| Equality            | `[=]term`                 | Exact match: column = term.                                                                         |
| Not Equal           | `[!=]term`                | Not equal: column != term.                                                                          |
| Greater Than        | `[>]number`               | Greater than: column > number.                                                                      |
| Less Than           | `[<]number`               | Less than: column < number.                                                                         |
| IN list             | `[IN]a,b,c`               | IN list: one of the comma-separated terms must exactly match.                                       |
| OR (LIKE-group)     | `[OR]a,b,c`               | OR of LIKE '%…%' for each term: column LIKE '%a%' OR '%b%' OR '%c%'.                                |
| BETWEEN range       | `[><]min,max`             | Range: column BETWEEN min AND max.                                                                  |
| LIKE synonyms       | `[LIKE]term`, `[%%]term`  | Synonyms for LIKE '%term%'.                                                                         |

## Notes

- Global search applies LIKE to configured text columns. Use `withSearchableColumns([...])` and `withColumnAliases([...])` to ensure valid fields.
- Invalid or unknown operators default to LIKE '%term%'.
