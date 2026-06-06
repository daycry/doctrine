<?php

declare(strict_types=1);

namespace Daycry\Doctrine\DataTables;

use Closure;
use CodeIgniter\Exceptions\InvalidArgumentException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Builder for DataTables integration with Doctrine ORM/DBAL.
 * Enables dynamic pagination, filtering, and ordering of results.
 *
 * @property bool                         $caseInsensitive   Case-insensitive search
 * @property array<string, string>        $columnAliases     Column aliases DataTables => DB
 * @property string                       $columnField       Column field ('data' or 'name')
 * @property string                       $indexColumn       Index column
 * @property ORMQueryBuilder|QueryBuilder $queryBuilder      Doctrine QueryBuilder
 * @property array<string, mixed>|null    $requestParams     DataTables request parameters
 * @property list<string>                 $searchableColumns Columns allowed for global LIKE search
 * @property bool                         $useOutputWalkers  Use OutputWalkers in paginator
 */
class Builder
{
    /**
     * Column aliases DataTables => DB
     *
     * @var array<string, string>
     */
    protected array $columnAliases = [];

    /**
     * Column field ('data' or 'name')
     */
    protected string $columnField = 'data';

    /**
     * Index column
     */
    protected string $indexColumn = '*';

    /**
     * Case-insensitive search
     */
    protected bool $caseInsensitive = false;

    /**
     * Doctrine QueryBuilder
     */
    protected ORMQueryBuilder|QueryBuilder|null $queryBuilder = null;

    /**
     * DataTables request parameters
     *
     * @var array<string, mixed>|null
     */
    protected ?array $requestParams = null;

    /**
     * Use OutputWalkers in paginator
     */
    protected ?bool $useOutputWalkers = null;

    /**
     * Columns allowed for global LIKE search
     *
     * @var list<string>
     */
    protected array $searchableColumns = [];

    /**
     * Maximum number of values allowed for IN and OR filter operators.
     * Prevents DoS via excessively large filter value lists.
     */
    protected int $maxFilterValues = 500;

    /**
     * Hard cap on the page size (DataTables `length`). When greater than 0, any
     * request asking for more rows than this — including DataTables' "All"
     * sentinel `length=-1`, which otherwise produces an unbounded result set —
     * is clamped to this value. 0 (default) keeps the legacy behaviour (no cap).
     */
    protected int $maxPageLength = 0;

    /**
     * Whether the data Paginator fetches to-many collections via an id sub-query
     * (`fetchJoinCollection`). Defaults to true (Doctrine's safe default). Set to
     * false when the query has no to-many fetch join to collapse the page fetch
     * from two queries (id sub-query + WHERE-IN) into one. Count queries are
     * unaffected by this flag.
     */
    protected bool $fetchJoinCollection = true;

    /**
     * Optional precomputed unfiltered total. When set (an int or a Closure
     * returning an int), getRecordsTotal()/getResponse() return it instead of
     * issuing a COUNT query — useful to cache an expensive total across draws.
     */
    protected Closure|int|null $recordsTotal = null;

    /**
     * Static factory for fluent usage.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set columns allowed for global LIKE search.
     *
     * @param list<string> $columns
     *
     * @return $this
     */
    public function withSearchableColumns(array $columns): static
    {
        $this->searchableColumns = $columns;

        return $this;
    }

    /**
     * Set the maximum number of values accepted by IN and OR filter operators.
     * Use PHP_INT_MAX to disable the limit.
     */
    public function withMaxFilterValues(int $maxFilterValues): static
    {
        if ($maxFilterValues < 1) {
            throw new InvalidArgumentException('maxFilterValues must be at least 1. Use PHP_INT_MAX to disable the limit.');
        }

        $this->maxFilterValues = $maxFilterValues;

        return $this;
    }

    /**
     * Set a hard upper bound on the page size returned to the client.
     *
     * Protects against resource exhaustion from `length=-1` ("All") or an
     * arbitrarily large `length`, which would otherwise hydrate the entire
     * filtered table. Pass 0 to disable the cap (legacy behaviour).
     */
    public function withMaxPageLength(int $maxPageLength): static
    {
        if ($maxPageLength < 0) {
            throw new InvalidArgumentException('maxPageLength must be 0 (disabled) or a positive integer.');
        }

        $this->maxPageLength = $maxPageLength;

        return $this;
    }

    /**
     * Set whether the paginated data fetch uses `fetchJoinCollection`.
     * Leave true (default) for queries that fetch-join a to-many association;
     * set false for the common scalar/single-entity SELECT to save one query.
     */
    public function withFetchJoinCollection(bool $fetchJoinCollection): static
    {
        $this->fetchJoinCollection = $fetchJoinCollection;

        return $this;
    }

    /**
     * Provide a precomputed unfiltered total (int or a Closure returning int) so
     * getRecordsTotal()/getResponse() skip the COUNT query. Invalidation is the
     * caller's responsibility.
     */
    public function withRecordsTotal(Closure|int $recordsTotal): static
    {
        $this->recordsTotal = $recordsTotal;

        return $this;
    }

    /**
     * Returns paginated, filtered, and ordered data for DataTables.
     *
     * @return list<object>
     *
     * @throws InvalidArgumentException
     */
    public function getData(): array
    {
        $this->validate();
        $query   = $this->getFilteredQuery();
        $columns = $this->requestParams['columns'];
        $this->applyOrdering($query, $columns);
        $this->applyPagination($query);
        $paginator = new Paginator($query, $this->fetchJoinCollection);
        $paginator->setUseOutputWalkers($this->useOutputWalkers ?? true);
        $result = [];

        foreach ($paginator as $obj) {
            $result[] = $obj;
        }

        return $result;
    }

    /**
     * Returns a filtered QueryBuilder based on DataTables parameters.
     *
     * @throws InvalidArgumentException
     */
    public function getFilteredQuery(): ORMQueryBuilder|QueryBuilder
    {
        $this->validate();
        $query   = clone $this->queryBuilder;
        $columns = $this->requestParams['columns'];
        $c       = count($columns);

        // Reject regex search mode only when it would actually be applied (non-empty value).
        // DataTables clients commonly send `regex` keys for every column even when the value is
        // empty; raising on empty values would force callers to strip noise from their payload.
        $globalSearchValue = trim((string) ($this->requestParams['search']['value'] ?? ''));
        if ($globalSearchValue !== '' && ! empty($this->requestParams['search']['regex'])) {
            throw new InvalidArgumentException('Regex search is not supported. Use bracket-prefix operators like [IN], [OR], [><] instead.');
        }

        foreach ($columns as $column) {
            $columnSearchValue = trim((string) ($column['search']['value'] ?? ''));
            if ($columnSearchValue !== '' && ! empty($column['search']['regex'] ?? null)) {
                throw new InvalidArgumentException('Regex per-column search is not supported. Use bracket-prefix operators like [IN], [OR], [><] instead.');
            }
        }

        // Search
        if (array_key_exists('search', $this->requestParams)) {
            $value = mb_substr(trim($this->requestParams['search']['value'] ?? ''), 0, 255);
            if ($value !== '') {
                $orX = $query->expr()->orX();

                for ($i = 0; $i < $c; $i++) {
                    $column = $columns[$i];
                    if ($this->isColumnSearchable($column)) {
                        $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $i);

                        // Only allow LIKE on configured searchable columns
                        if (! empty($this->searchableColumns) && ! in_array($fieldName, $this->searchableColumns, true)) {
                            continue;
                        }

                        // Skip if field is not valid for DQL (prevents numeric indices and invalid identifiers)
                        if (! $this->isValidDQLField($fieldName)) {
                            continue;
                        }

                        if ($this->caseInsensitive) {
                            $searchColumn = 'lower(' . $fieldName . ')';
                            $orX->add($query->expr()->like($searchColumn, 'lower(:search)'));
                        } else {
                            $orX->add($query->expr()->like($fieldName, ':search'));
                        }
                    }
                }
                if ($orX->count() >= 1) {
                    $query->andWhere($orX)
                        ->setParameter('search', "%{$value}%");
                }
            }
        }

        // Filter
        for ($i = 0; $i < $c; $i++) {
            $column = $columns[$i];
            $andX   = $query->expr()->andX();
            $value  = trim($column['search']['value'] ?? '');
            if ($this->isColumnSearchable($column) && $value !== '') {
                $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $i);

                // Skip if field is not valid for DQL (prevents numeric indices and invalid identifiers)
                if (! $this->isValidDQLField($fieldName)) {
                    continue;
                }

                // Parse operator and value via helper for maintainability
                [$operator, $value] = $this->parseFilterOperator($value);
                if ($this->caseInsensitive) {
                    $searchColumn = 'lower(' . $fieldName . ')';
                    $filter       = "lower(:filter_{$i})";
                } else {
                    $searchColumn = $fieldName;
                    $filter       = ":filter_{$i}";
                }

                switch ($operator) {
                    case '!=':
                        $andX->add($query->expr()->neq($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '<':
                        $andX->add($query->expr()->lt($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '>':
                        $andX->add($query->expr()->gt($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case 'IN':
                        $valueArr = explode(',', $value);
                        if (count($valueArr) > $this->maxFilterValues) {
                            throw new InvalidArgumentException(sprintf(
                                'IN filter exceeds maximum allowed values (%d). Got %d. Use withMaxFilterValues() to adjust the limit.',
                                $this->maxFilterValues,
                                count($valueArr),
                            ));
                        }
                        $params = [];

                        for ($j = 0; $j < count($valueArr); $j++) {
                            $params[] = ":filter_{$i}_{$j}";
                        }
                        $andX->add($query->expr()->in($fieldName, implode(',', $params)));

                        for ($j = 0; $j < count($valueArr); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", trim($valueArr[$j]));
                        }
                        break;

                    case 'OR':
                        $valueArr = explode(',', $value);
                        if (count($valueArr) > $this->maxFilterValues) {
                            throw new InvalidArgumentException(sprintf(
                                'OR filter exceeds maximum allowed values (%d). Got %d. Use withMaxFilterValues() to adjust the limit.',
                                $this->maxFilterValues,
                                count($valueArr),
                            ));
                        }
                        $orX = $query->expr()->orX();

                        for ($j = 0; $j < count($valueArr); $j++) {
                            // Honor caseInsensitive: $searchColumn is already lower()-wrapped
                            // when the flag is on; wrap each placeholder to match.
                            $orPlaceholder = $this->caseInsensitive ? "lower(:filter_{$i}_{$j})" : ":filter_{$i}_{$j}";
                            $orX->add($query->expr()->like($searchColumn, $orPlaceholder));
                        }
                        $andX->add($orX);

                        for ($j = 0; $j < count($valueArr); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", '%' . trim($valueArr[$j]) . '%');
                        }
                        break;

                    case '><':
                        $valueArr = explode(',', $value);
                        if (count($valueArr) !== 2) {
                            throw new InvalidArgumentException(sprintf(
                                'BETWEEN operator [><] requires exactly 2 comma-separated values, got %d.',
                                count($valueArr),
                            ));
                        }
                        $andX->add($query->expr()->between($fieldName, ":filter_{$i}_0", ":filter_{$i}_1"));
                        $query->setParameter("filter_{$i}_0", trim($valueArr[0]));
                        $query->setParameter("filter_{$i}_1", trim($valueArr[1]));
                        break;

                    case '=':
                        $andX->add($query->expr()->eq($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '%':
                    default:
                        $andX->add($query->expr()->like($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", "%{$value}%");
                        break;
                }
            }
            if ($andX->count() >= 1) {
                $query->andWhere($andX);
            }
        }

        return $query;
    }

    /**
     * Parse a raw filter value extracting the operator and cleaned term.
     * Returns [operator, value] with fallback to '%'.
     * Supported operators: !=, <, >, IN, OR, ><, =, %, LIKE, %% (LIKE/%% normalize to %).
     *
     * @return array{0: string, 1: string}
     */
    private function parseFilterOperator(string $raw): array
    {
        $validPattern  = '~^\[(?<operator>!=|><|>|<|=|%%|%|IN|OR|LIKE)\]~i';
        $bracketPrefix = '~^\[[^\]]+\]~';

        if (preg_match($validPattern, $raw, $m)) {
            $operator = strtoupper($m['operator']);
            $value    = preg_replace($validPattern, '', $raw);
        } else {
            // Unknown bracket prefixes (typos like "[XYZ]") fall back to LIKE; the
            // prefix itself is stripped so the user-facing search term works as-is.
            $operator = '%';
            $value    = preg_replace($bracketPrefix, '', $raw);
        }

        // Normalize synonyms
        if ($operator === 'LIKE' || $operator === '%%') {
            $operator = '%';
        }

        return [$operator, trim($value ?? $raw)];
    }

    /**
     * Returns the number of filtered records.
     */
    public function getRecordsFiltered(): int
    {
        $query     = $this->getFilteredQuery();
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        return $paginator->count();
    }

    /**
     * Returns the total number of records (without filters).
     */
    public function getRecordsTotal(): int
    {
        $injected = $this->resolveInjectedRecordsTotal();
        if ($injected !== null) {
            return $injected;
        }

        $this->validate();
        $query     = clone $this->queryBuilder;
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        return $paginator->count();
    }

    /**
     * Resolve the injected unfiltered total, or null when none was provided.
     */
    private function resolveInjectedRecordsTotal(): ?int
    {
        if ($this->recordsTotal === null) {
            return null;
        }

        return (int) ($this->recordsTotal instanceof Closure ? ($this->recordsTotal)() : $this->recordsTotal);
    }

    /**
     * Returns the DataTables response array.
     *
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        $this->validate();
        $filteredQuery = $this->getFilteredQuery();
        $columns       = $this->requestParams['columns'];

        // Data (with ordering + pagination)
        $dataQuery = clone $filteredQuery;
        $this->applyOrdering($dataQuery, $columns);
        $this->applyPagination($dataQuery);
        $dataPaginator = new Paginator($dataQuery, $this->fetchJoinCollection);
        $dataPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);
        $data = [];

        foreach ($dataPaginator as $obj) {
            $data[] = $obj;
        }

        // Filtered count (reuses the already-built filtered query)
        $filteredPaginator = new Paginator($filteredQuery, true);
        $filteredPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        // Total count (unfiltered) — use the injected/cached total when provided,
        // otherwise compute it via a Paginator.
        $recordsTotal = $this->resolveInjectedRecordsTotal();
        if ($recordsTotal === null) {
            $totalQuery     = clone $this->queryBuilder;
            $totalPaginator = new Paginator($totalQuery, true);
            $totalPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);
            $recordsTotal = $totalPaginator->count();
        }

        return [
            'data'            => $data,
            'draw'            => $this->requestParams['draw'] ?? 0,
            'recordsFiltered' => $filteredPaginator->count(),
            'recordsTotal'    => $recordsTotal,
        ];
    }

    /**
     * Sets the index column.
     */
    public function withIndexColumn(string $indexColumn): static
    {
        $this->indexColumn = $indexColumn;

        return $this;
    }

    /**
     * Sets useOutputWalkers for the paginator.
     */
    public function setUseOutputWalkers(bool $useOutputWalkers): static
    {
        $this->useOutputWalkers = $useOutputWalkers;

        return $this;
    }

    /**
     * Sets column aliases.
     *
     * @param array<string, string> $columnAliases
     */
    public function withColumnAliases(array $columnAliases): static
    {
        $this->columnAliases = $columnAliases;

        return $this;
    }

    /**
     * Enables or disables case-insensitive search.
     */
    public function withCaseInsensitive(bool $caseInsensitive): static
    {
        $this->caseInsensitive = $caseInsensitive;

        return $this;
    }

    /**
     * Sets the column field ('data' or 'name').
     */
    public function withColumnField(string $columnField): static
    {
        $this->columnField = $columnField;

        return $this;
    }

    /**
     * Sets the Doctrine QueryBuilder.
     */
    public function withQueryBuilder(ORMQueryBuilder|QueryBuilder $queryBuilder): static
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * Sets the DataTables request parameters.
     *
     * @param array<string, mixed> $requestParams
     */
    public function withRequestParams(array $requestParams): static
    {
        $this->requestParams = $requestParams;

        return $this;
    }

    /**
     * Validates that required properties are set.
     */
    protected function validate(): void
    {
        if ($this->queryBuilder === null) {
            throw new InvalidArgumentException('QueryBuilder is not set.');
        }
        if (! is_array($this->requestParams) || empty($this->requestParams['columns'])) {
            throw new InvalidArgumentException('Request parameters or columns are not set.');
        }
    }

    /**
     * Applies ordering to the query.
     *
     * @param array<int|string, mixed> $columns
     */
    protected function applyOrdering(ORMQueryBuilder|QueryBuilder $query, array $columns): void
    {
        if (array_key_exists('order', $this->requestParams)) {
            $order = $this->requestParams['order'];

            foreach ($order as $sort) {
                // Skip entries whose client-supplied column index is missing or
                // out of range, instead of warning on an undefined array key.
                if (! isset($sort['column'])) {
                    continue;
                }
                $columnIndex = (int) $sort['column'];
                if (! array_key_exists($columnIndex, $columns)) {
                    continue;
                }

                $column    = $columns[$columnIndex];
                $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $columnIndex);
                $dir       = strtoupper($sort['dir'] ?? 'ASC');
                $dir       = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';

                // Only add ordering if field is valid for DQL
                if ($this->isValidDQLField($fieldName)) {
                    $query->addOrderBy($fieldName, $dir);
                }
            }
        }
    }

    /**
     * Applies offset and limit to the query.
     */
    protected function applyPagination(ORMQueryBuilder|QueryBuilder $query): void
    {
        if (array_key_exists('start', $this->requestParams)) {
            $query->setFirstResult((int) ($this->requestParams['start']));
        }

        $length = array_key_exists('length', $this->requestParams)
            ? (int) ($this->requestParams['length'])
            : 0;

        if ($this->maxPageLength > 0) {
            // Clamp unbounded ("All"/length<=0) or oversized requests to the cap.
            $effective = ($length <= 0 || $length > $this->maxPageLength) ? $this->maxPageLength : $length;
            $query->setMaxResults($effective);
        } elseif ($length > 0) {
            $query->setMaxResults($length);
        }
    }

    /**
     * Helper: Check if a column is searchable.
     * Accepts both boolean true and string 'true'.
     *
     * @param array<string, mixed> $column
     */
    protected function isColumnSearchable(array $column): bool
    {
        return
            (isset($column['searchable']) && ($column['searchable'] === true || $column['searchable'] === 'true'))
            && isset($column[$this->columnField]) && $column[$this->columnField] !== '';
    }

    /**
     * Helper: Resolve column alias if set.
     */
    protected function resolveColumnAlias(string $field): string
    {
        return $this->columnAliases[$field] ?? $field;
    }

    /**
     * Helper: Resolve field name for DQL, handling DataTables column configuration.
     *
     * @param mixed $columnValue The column value from DataTables (could be field name or index)
     * @param int   $columnIndex The column index as fallback
     *
     * @return string The resolved field name
     */
    protected function resolveFieldName($columnValue, int $columnIndex): string
    {
        // If columnValue is numeric or empty, it's likely an index, not a field name
        if (is_numeric($columnValue) || empty($columnValue)) {
            return (string) $columnIndex; // Return as string to be caught by isValidDQLField
        }

        // Resolve alias if exists
        return $this->resolveColumnAlias((string) $columnValue);
    }

    /**
     * Helper: Check if field name is valid for DQL queries.
     * Prevents numeric indices and invalid identifiers from being used in DQL.
     *
     * @param string $field The field name to validate
     *
     * @return bool True if field is valid for DQL, false otherwise
     */
    protected function isValidDQLField(string $field): bool
    {
        // Must match valid DQL identifier pattern (letters, numbers, underscore, dots for joins)
        // Must not be purely numeric
        return ! empty($field)
            && ! is_numeric($field)
            && (bool) preg_match('/^[a-zA-Z_]\w*(\.[a-zA-Z_]\w*)*$/', $field);
    }
}
