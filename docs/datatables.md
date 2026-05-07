# DataTables + Doctrine: Filtering and Search

`Daycry\Doctrine\DataTables\Builder` provides server-side pagination, ordering
and filtering for DataTables, built on top of a Doctrine `QueryBuilder`.

For the canonical operator reference (modes, validation, exceptions) see
[`search_modes.md`](search_modes.md). This document focuses on the Builder API
and end-to-end examples.

## Key Concepts

- `columnField` — which DataTables column property carries the field name.
  Use `'data'` (default) when columns look like `{"data": "name"}`, or
  `'name'` when they use `{"name": "name"}`.
- `columnAliases` — maps DataTables identifiers to DQL paths
  (`'name' => 't.name'`).
- `searchableColumns` — whitelist of DQL fields used for the global LIKE
  search. When set, only these columns participate in the global OR.
- `withCaseInsensitive(true)` — wraps both the column and the parameter in
  `lower()`. Applies only to LIKE-based searches (default `[%]`, `[OR]`)
  and equality `[=]`. Operators `[IN]`, `[><]`, `[>]`, `[<]`, `[!=]` are
  always case-sensitive.

## Builder API

| Method | Description |
|--------|-------------|
| `Builder::create()` | Static factory; equivalent to `new Builder()`. |
| `withQueryBuilder($qb)` | Set the base DQL `QueryBuilder` (ORM or DBAL). |
| `withRequestParams(array $params)` | Set the DataTables request payload. |
| `withColumnAliases(array $aliases)` | Map DataTables column identifiers to DQL paths. |
| `withColumnField(string $field)` | DataTables column property used as field name (`'data'` or `'name'`). |
| `withSearchableColumns(array $cols)` | Whitelist DQL fields for the global LIKE search. |
| `withCaseInsensitive(bool $flag)` | Enable case-insensitive LIKE / equality via `lower()`. |
| `withIndexColumn(string $col)` | Override the index column used in pagination. |
| `withMaxFilterValues(int $max)` | Cap the values accepted by `[IN]` and `[OR]`. Throws `InvalidArgumentException` if `< 1`. Use `PHP_INT_MAX` to disable. **Default: 500**, intended as a DoS guard. |
| `setUseOutputWalkers(bool $flag)` | Toggle Doctrine `Paginator` output walkers. Set `false` when pagination fails with scalar-select queries (eg. *"Not all identifier properties can be found in the ResultSetMapping"*). |
| `getData()` | Execute and return the paginated, filtered, ordered result. |
| `getRecordsFiltered()` | Count records matching the current filters (no pagination). |
| `getRecordsTotal()` | Count records with no filter applied. |
| `getResponse()` | Return the full DataTables response array (`draw`, `recordsTotal`, `recordsFiltered`, `data`). |

## Per-Column Operators

Column filters accept a bracket-prefixed operator: `[OPERATOR]value`. The
canonical reference, including limits, exceptions and case-insensitivity
rules, lives in **[`search_modes.md`](search_modes.md)**.

Quick recap of the operators supported by the Builder:

```
[%]   (LIKE, default)   [=]   [!=]   [>]   [<]   [IN]   [OR]   [><]
```

Synonyms `[LIKE]` and `[%%]` map to `[%]`. Unknown prefixes silently fall
back to `[%]`.

> **Important.** The DataTables `regex` flag (both `search.regex: true` and
> `columns[N].search.regex: true`) is **not supported** and raises
> `InvalidArgumentException: 'Regex search is not supported.'` whenever the
> matching search value is non-empty. The flag is tolerated alongside an
> empty value, since DataTables clients commonly include it in every
> payload regardless of intent. Use the bracket operators instead.

> **`[><]` strict arity.** `[><]min,max` requires *exactly* two
> comma-separated values; sending one or three throws
> `InvalidArgumentException` (it no longer fails silently).

## Column Validation

- A column whose `data` is numeric or doesn't match
  `^[A-Za-z_][\w.]*$` is dropped from `WHERE` and `ORDER BY` to prevent
  malformed DQL (this is the historic *"6 LIKE :search"* failure).
- Use `columnAliases` to map friendly DataTables identifiers to DQL paths.

## Examples

### Global Search (LIKE)

```php
$builder
    ->withSearchableColumns(['t.name'])
    ->withCaseInsensitive(true)
    ->withColumnField('data')
    ->withRequestParams([
        'search'  => ['value' => 'am', 'regex' => false],
        'columns' => [
            ['data' => 'id',   'searchable' => true],
            ['data' => 'name', 'searchable' => true],
        ],
    ]);
```

Returns rows where `t.name` contains `"am"` (case-insensitive).

### Per-Column `[IN]`

```php
$builder->withRequestParams([
    'columns' => [
        ['data' => 'id',   'searchable' => true, 'search' => ['value' => '[IN]1,2,3']],
        ['data' => 'name', 'searchable' => true, 'search' => ['value' => '']],
    ],
]);
```

### Per-Column `[OR]`

```php
$builder->withRequestParams([
    'columns' => [
        ['data' => 'name', 'searchable' => true, 'search' => ['value' => '[OR]alpha,beta']],
    ],
]);
```

### `[><]` BETWEEN

```php
$builder->withRequestParams([
    'columns' => [
        ['data' => 'price', 'searchable' => true, 'search' => ['value' => '[><]10,99']],
    ],
]);
// Sending '[><]10' or '[><]1,2,3' raises InvalidArgumentException.
```

### Invalid Operator Falls Back to LIKE (silent)

```php
$builder->withRequestParams([
    'columns' => [
        ['data' => 'name', 'searchable' => true, 'search' => ['value' => '[XYZ]am']],
    ],
]);
// Equivalent to LIKE '%[XYZ]am%' — useful to know when debugging silent matches.
```

## Best Practices

- Configure `columnAliases` and ensure entities/joins are properly defined
  so DQL identifiers are valid.
- Define `searchableColumns` to constrain global search to safe text columns.
- Avoid sending numeric indices in `data` as column identifiers.
- Use `withCaseInsensitive(true)` only when you actually want case-insensitive
  matching — it does not affect `[IN]`, `[><]`, `[!=]`, `[>]`, `[<]`.
- Tune `withMaxFilterValues()` to a value matching your business rules; the
  default 500 prevents accidental DoS via huge `[IN]` lists.

## Full Example

```php
use Daycry\Doctrine\DataTables\Builder;
use Doctrine\ORM\Query\Expr\Join;

$qb = $this->doctrine->em->createQueryBuilder();
$qb->select('p.uuid AS id, p.name AS name, p.companyName AS companyName, ps.name AS status, p.version AS version')
   ->from(\App\Models\Entity\WebProjects::class, 'p')
   ->innerJoin(\App\Models\Entity\WebProjectsStatuses::class, 'ps', Join::WITH, 'p.webProjectStatus = ps.id')
   ->andWhere('p.deletedAt IS NULL');

$builder = Builder::create()
    ->withQueryBuilder($qb)
    ->withRequestParams($this->request->getGet())
    ->withColumnAliases([
        'id'          => 'p.uuid',
        'name'        => 'p.name',
        'companyName' => 'p.companyName',
        'status'      => 'ps.name',
        'version'     => 'p.version',
    ])
    ->withSearchableColumns(['p.name', 'p.companyName', 'ps.name'])
    ->withCaseInsensitive(true)
    ->setUseOutputWalkers(false);

return $this->response->setJSON($builder->getResponse());
```

## Troubleshooting

- *"Not all identifier properties can be found in the ResultSetMapping"* —
  scalar-select queries don't expose entity identifiers. Call
  `setUseOutputWalkers(false)` on the Builder.
- *"Expected =, <, <=, <>, >, >=, !=, got 'LIKE'"* — caused by an invalid
  column identifier (eg. a numeric index `6`) leaking into the global
  search. Always provide `withColumnAliases([...])` and
  `withSearchableColumns([...])` so only valid DQL fields participate in
  LIKE conditions.

## Test References

`tests/DataTableTest.php` and `tests/DataTablesBuilderEdgeCasesTest.php`
cover global and per-column searches, every supported operator, fallback to
`[%]` for unknown prefixes, the `[><]` arity validation, the regex rejection,
and the numeric-column dropout.
