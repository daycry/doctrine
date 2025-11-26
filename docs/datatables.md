# DataTables + Doctrine: Filtering and Search

This module integrates DataTables with Doctrine ORM/DBAL, enabling dynamic pagination, ordering, and filtering on DQL queries.

## Key Concepts

- `columnField`: which DataTables column property contains the identifier. Typical values: `data` or `name`.
- `columnAliases`: maps DataTables columns to DQL fields (e.g., `name` → `t.name`).
- `searchableColumns`: whitelist of columns used for global LIKE search. When set, only these columns are used in the global OR.
- `withCaseInsensitive(true)`: enables case-insensitive matching for LIKE (both global and per-column when the operator is LIKE/`%` or `=`). It does not apply to non-LIKE operators (`IN`, `><`, `>`, `<`, `!=`).

## Supported Per-Column Operators

Column filters accept a bracket-prefixed operator in the value. Format: `[OPERATOR]value`. If the operator is not recognized, `%` (LIKE) is used by default.

| Mode                   | Pattern             | Description                                                                                 |
|------------------------|---------------------|---------------------------------------------------------------------------------------------|
| LIKE '%…%' (default)   | `[%]term` or `term` | LIKE '%term%'; any part of the term may match a value in the column.                        |
| Equality               | `[=]term`           | Exact match: column = term.                                                                 |
| Not Equal              | `[!=]term`          | Not equal: column != term.                                                                  |
| Greater Than           | `[>]number`         | Greater than: column > number.                                                              |
| Less Than              | `[<]number`         | Less than: column < number.                                                                 |
| IN list                | `[IN]a,b,c`         | IN list: one of the comma-separated terms must exactly match.                               |
| OR (LIKE-group)        | `[OR]a,b,c`         | OR of LIKE '%…%' for each term: column LIKE '%a%' OR '%b%' OR '%c%'.                        |
| BETWEEN range          | `[><]min,max`       | Range: column BETWEEN min AND max.                                                          |
| LIKE synonyms          | `[LIKE]term`, `[%%]term` | Synonyms for LIKE '%term%'.                                                             |

Notes:
- For `[OR]`, the builder uses one LIKE per term; if `withCaseInsensitive(true)` is enabled, `lower()` is applied to the column and to the corresponding parameter.
- Operators that do not use LIKE (`IN`, `><`, `>`, `<`, `!=`) are unaffected by `withCaseInsensitive(true)`.

## Column Validation

- If a column value is numeric or does not match a valid DQL identifier, it is ignored to avoid errors (e.g., `data='0'`).
- Use `columnAliases` to map friendly DataTables names to DQL paths: `{ 'id': 't.id', 'name': 't.name' }`.

## Examples

### Global Search (LIKE)
```php
$builder
  ->withSearchableColumns(['t.name'])
  ->withCaseInsensitive(true)
  ->withColumnField('data')
  ->withRequestParams([
    'search' => ['value' => 'am', 'regex' => false],
    'columns' => [
      ['data' => 'id', 'searchable' => true],
      ['data' => 'name', 'searchable' => true]
    ],
  ]);
```
Returns rows where `t.name` contains "am".

### Per-Column Filter with `IN`
```php
$builder->withRequestParams([
  'columns' => [
    ['data' => 'id', 'searchable' => true, 'search' => ['value' => '[IN]1,2'] ],
    ['data' => 'name', 'searchable' => true, 'search' => ['value' => ''] ],
  ]
]);
```

### Per-Column Filter with `OR`
```php
$builder->withRequestParams([
  'columns' => [
    ['data' => 'name', 'searchable' => true, 'search' => ['value' => '[OR]name1,name2'] ],
  ]
]);
```

### Filter with `BETWEEN`
```php
$builder->withRequestParams([
  'columns' => [
    ['data' => 'id', 'searchable' => true, 'search' => ['value' => '[><]1,2'] ],
  ]
]);
```

### Invalid Operator → Fallback to LIKE
```php
$builder->withRequestParams([
  'columns' => [
    ['data' => 'name', 'searchable' => true, 'search' => ['value' => '[XYZ]am'] ],
  ]
]);
// Interpreted as LIKE "%am%"
```

## Best Practices

- Configure `columnAliases` and ensure entities/joins are properly defined so DQL identifiers are valid.
- Define `searchableColumns` to constrain global search and avoid LIKE on unintended columns.
- Avoid sending numeric indices in `data` as column identifiers.
- Use `withCaseInsensitive(true)` when you expect mixed capitalization in search terms.

## Test References

Tests in `tests/DataTableTest.php` cover:
- Global and per-column search.
- Operators `[IN]`, `[OR]`, `[><]`, `[=]`, `[!=]`, `[%]` and synonyms.
- Fallback to `[%]` for invalid operators.
- Ignoring columns with invalid (numeric) identifiers.

Doctrine integrates smoothly with DataTables to provide server-side pagination, ordering, and filtering using Doctrine ORM/DBAL.

## Basic Usage

```php
use Daycry\Doctrine\DataTables\Builder;
use Doctrine\ORM\Query\Expr\Join;

$qb = $this->doctrine->em->createQueryBuilder();
$qb->select('p.uuid AS id, p.name AS name, p.companyName AS companyName, ps.name AS status, p.version AS version')
   ->from(App\Models\Entity\WebProjects::class, 'p')
   ->innerJoin(App\Models\Entity\WebProjectsStatuses::class, 'ps', Join::WITH, 'p.webProjectStatus = ps.id')
   ->andWhere('p.deletedAt IS NULL');

$builder = Builder::create()
    ->withQueryBuilder($qb)
    ->withRequestParams($this->request->getGet())
    // Map DataTables column names to DQL fields used in select
    ->withColumnAliases([
        'id'          => 'p.uuid',
        'name'        => 'p.name',
        'companyName' => 'p.companyName',
        'status'      => 'ps.name',
        'version'     => 'p.version',
    ])
    // Restrict global LIKE search to safe text columns
    ->withSearchableColumns(['p.name', 'p.companyName', 'ps.name'])
    // Optional: case-insensitive matching
    ->withCaseInsensitive(true)
    // Optional: disable OutputWalkers if your query includes complex selects
    ->setUseOutputWalkers(false);

$response = $builder->getResponse();
return $this->response->setJSON($response);
```

## Troubleshooting

- Error: `Not all identifier properties can be found in the ResultSetMapping`
  - Use `->setUseOutputWalkers(false)` when your select includes scalar mappings or complex joins.

- Error: `Expected =, <, <=, <>, >, >=, !=, got 'LIKE'`
  - This happens when an invalid column (e.g., numeric index `6`) is used in global search.
  - Ensure you provide `->withColumnAliases([...])` and `->withSearchableColumns([...])` so only valid DQL fields participate in LIKE conditions.
  - See `docs/DATATABLES_FIX.md` for the detailed explanation and the implemented fix.

## Filtering Operators (per-column)

In the per-column search box, you can prefix your term to apply an operator. Prefixes are case-insensitive and terms are trimmed.

- `[=]value` : exact match
- `[!=]value` : not equal
- `[>]10` : greater than
- `[<]10` : less than
- `[%]term` : LIKE `'%term%'` (default if no operator)
- `[IN]a,b,c` : `IN (a,b,c)` exact match list
- `[OR]a,b,c` : `LIKE '%a%' OR LIKE '%b%' OR LIKE '%c%'`
- `[><]min,max` : `BETWEEN min AND max`

For the full matrix of search modes, see `docs/search_modes.md`.
