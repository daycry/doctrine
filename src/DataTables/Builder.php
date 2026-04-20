<?php

declare(strict_types=1);

namespace Daycry\Doctrine\DataTables;

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
        $paginator = new Paginator($query, $fetchJoinCollection = true);
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
                            $orX->add($query->expr()->like($fieldName, ":filter_{$i}_{$j}"));
                        }
                        $andX->add($orX);

                        for ($j = 0; $j < count($valueArr); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", '%' . trim($valueArr[$j]) . '%');
                        }
                        break;

                    case '><':
                        $valueArr = explode(',', $value);
                        if (count($valueArr) === 2) {
                            $andX->add($query->expr()->between($fieldName, ":filter_{$i}_0", ":filter_{$i}_1"));
                            $query->setParameter("filter_{$i}_0", trim($valueArr[0]));
                            $query->setParameter("filter_{$i}_1", trim($valueArr[1]));
                        }
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
        $operator = preg_match('~^\[(?<operator>[A-Z!=%<>•]+)\]~i', $raw, $m) ? strtoupper($m['operator']) : '%';
        $value    = preg_replace('~^\[[A-Z!=%<>•]+\]~i', '', $raw);
        // Normalize synonyms
        if (in_array($operator, ['LIKE', '%%'], true)) {
            $operator = '%';
        }
        $valid = ['!=', '<', '>', 'IN', 'OR', '><', '=', '%'];
        if (! in_array($operator, $valid, true)) {
            $operator = '%';
        }

        return [$operator, trim($value)];
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
        $this->validate();
        $query     = clone $this->queryBuilder;
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        return $paginator->count();
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
        $dataPaginator = new Paginator($dataQuery, true);
        $dataPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);
        $data = [];

        foreach ($dataPaginator as $obj) {
            $data[] = $obj;
        }

        // Filtered count (reuses the already-built filtered query)
        $filteredPaginator = new Paginator($filteredQuery, true);
        $filteredPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        // Total count (unfiltered)
        $totalQuery     = clone $this->queryBuilder;
        $totalPaginator = new Paginator($totalQuery, true);
        $totalPaginator->setUseOutputWalkers($this->useOutputWalkers ?? true);

        return [
            'data'            => $data,
            'draw'            => $this->requestParams['draw'] ?? 0,
            'recordsFiltered' => $filteredPaginator->count(),
            'recordsTotal'    => $totalPaginator->count(),
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
                $column    = $columns[(int) ($sort['column'])];
                $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', (int) ($sort['column']));
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
        if (array_key_exists('length', $this->requestParams)) {
            $length = (int) ($this->requestParams['length']);
            if ($length > 0) {
                $query->setMaxResults($length);
            }
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
