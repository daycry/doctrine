# DataTables Search Modes

This document is the **canonical reference** for the per-column operators
supported by `Daycry\Doctrine\DataTables\Builder`. Other docs (`README.md`,
`docs/datatables.md`) link here instead of duplicating the table.

## Key Concepts

- Operators are applied per column via the DataTables request `columns[*].search.value`.
- Prefixes are case-insensitive (`[in]` ≡ `[IN]`); search terms are trimmed.
- Operators not listed below silently fall back to `[%]` (LIKE `'%term%'`) — see
  [Unknown Operators](#unknown-operators) for the recognised list.
- The DataTables `regex` flag is **not supported**. Sending `regex: true`
  (global or per column) raises `InvalidArgumentException`. Use the bracket
  operators below instead.

## Operator Matrix

| Mode                    | Pattern                              | Description                                                                                                                                                              |
|-------------------------|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| LIKE `'%…%'` (default)  | `[%]term` or `term`                  | LIKE `'%term%'`; any substring match. Affected by `withCaseInsensitive(true)` (wraps column and parameter in `lower()`).                                                |
| LIKE synonyms           | `[LIKE]term` · `[%%]term`            | Identical to `[%]`.                                                                                                                                                      |
| Equality                | `[=]term`                            | Exact match: `column = term`. Affected by `withCaseInsensitive(true)`.                                                                                                  |
| Not Equal               | `[!=]term`                           | `column != term`. **Always case-sensitive.**                                                                                                                            |
| Greater Than            | `[>]term`                            | `column > term`. Always case-sensitive.                                                                                                                                  |
| Less Than               | `[<]term`                            | `column < term`. Always case-sensitive.                                                                                                                                  |
| IN list                 | `[IN]a,b,c`                          | `column IN (a, b, c)`. Always case-sensitive. Subject to `withMaxFilterValues()` cap.                                                                                  |
| OR (LIKE-group)         | `[OR]a,b,c`                          | `column LIKE '%a%' OR LIKE '%b%' OR …`. Affected by `withCaseInsensitive(true)`. Subject to `withMaxFilterValues()` cap.                                                |
| BETWEEN range           | `[><]min,max`                        | `column BETWEEN min AND max`. **Requires exactly 2 values**; throws `InvalidArgumentException` otherwise. Always case-sensitive.                                       |

## Limits & Validation

- **`[IN]` and `[OR]` value cap.** Both operators reject lists with more values
  than `Builder::withMaxFilterValues()` (default `500`). Exceeding the cap raises
  `InvalidArgumentException`. Set `PHP_INT_MAX` to disable; values smaller than `1`
  also throw.

- **`[><]` strict arity.** The Between operator requires exactly two
  comma-separated values (`[><]min,max`). Sending `[><]50` or `[><]1,2,3`
  raises `InvalidArgumentException` — previously this failed silently.

- **No regex.** Both `search.regex: true` (global) and `columns[N].search.regex: true`
  (per column) raise `InvalidArgumentException`. The exception message points the
  caller to the bracket-prefix alternatives.

- **DQL-safe field names.** Field identifiers go through `isValidDQLField()`. Numeric
  indices and identifiers with characters outside `[A-Za-z_][\w.]*` are silently
  dropped from `WHERE`/`ORDER BY` to prevent malformed DQL (avoids the historic
  *"Expected =, <, … got 'LIKE'"* error).

## Unknown Operators

If the prefix does not match any of the operators recognised below, the Builder
silently falls back to `[%]` (LIKE `'%term%'`).

Recognised tokens:

```
=    !=    <    >    %    %%    LIKE    IN    OR    ><
```

A typo such as `[XYZ]term` or `[*%]term` is therefore treated as
`LIKE '%[XYZ]term%'` or `LIKE '%[*%]term%'` — useful to know when debugging
silent matches that look wrong.

## Case-Insensitivity Rules

`withCaseInsensitive(true)` only affects operators that compile to LIKE or
equality — i.e. **`[%]`, `[%%]`, `[LIKE]`, `[OR]` and `[=]`**. The remaining
operators (`[!=]`, `[<]`, `[>]`, `[IN]`, `[><]`) are always case-sensitive.

## Examples

Per-column IN:

```php
'columns' => [
    ['data' => 'id', 'searchable' => true, 'search' => ['value' => '[IN]1,2,3']],
],
```

Per-column OR (LIKE group):

```php
'columns' => [
    ['data' => 'name', 'searchable' => true, 'search' => ['value' => '[OR]alpha,beta']],
],
```

BETWEEN range:

```php
'columns' => [
    ['data' => 'price', 'searchable' => true, 'search' => ['value' => '[><]10,99']],
],
// Sending '[><]10' or '[><]1,2,3' throws InvalidArgumentException.
```

## Related

- Builder API and global search: [datatables.md](datatables.md)
- `withMaxFilterValues()` configuration: [datatables.md#builder-api](datatables.md)
