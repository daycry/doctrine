<?php

namespace Daycry\Doctrine\DataTables;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Class Builder
 */
class Builder
{
    /**
     * @var array
     */
    protected $columnAliases = [];

    /**
     * @var string
     */
    protected $columnField = 'data'; // or 'name'

    /**
     * @var string
     */
    protected $indexColumn = '*';

    /**
     * @var bool
     */
    protected $caseInsensitive = false;

    /**
     * @var ORMQueryBuilder|QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $requestParams;

    /**
     * @var bool
     */
    protected $useOutputWalkers;

    /**
     * @return array
     */
    public function getData()
    {
        $query   = $this->getFilteredQuery();
        $columns = &$this->requestParams['columns'];

        // Order
        if (array_key_exists('order', $this->requestParams)) {
            $order = &$this->requestParams['order'];

            foreach ($order as $sort) {
                $column = &$columns[(int) ($sort['column'])];
                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }
                $query->addOrderBy($column[$this->columnField], $sort['dir']);
            }
        }

        // Offset
        if (array_key_exists('start', $this->requestParams)) {
            $query->setFirstResult((int) ($this->requestParams['start']));
        }

        // Limit
        if (array_key_exists('length', $this->requestParams)) {
            $length = (int) ($this->requestParams['length']);
            if ($length > 0) {
                $query->setMaxResults($length);
            }
        }

        // Fetch
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers);
        $result = [];

        foreach ($paginator as $obj) {
            $result[] = $obj;
        }

        return $result;
    }

    /**
     * @return ORMQueryBuilder|QueryBuilder
     */
    public function getFilteredQuery()
    {
        $query   = clone $this->queryBuilder;
        $columns = &$this->requestParams['columns'];
        $c       = count($columns);

        // Search
        if (array_key_exists('search', $this->requestParams)) {
            if ($value = trim($this->requestParams['search']['value'])) {
                $orX = $query->expr()->orX();

                for ($i = 0; $i < $c; $i++) {
                    $column = &$columns[$i];
                    if ($column['searchable'] === 'true') {
                        if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                            $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                        }
                        if ($this->caseInsensitive) {
                            $searchColumn = 'lower(' . $column[$this->columnField] . ')';
                            $orX->add($query->expr()->like($searchColumn, 'lower(:search)'));
                        } else {
                            $orX->add($query->expr()->like($column[$this->columnField], ':search'));
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
            $column = &$columns[$i];
            $andX   = $query->expr()->andX();

            if (($column['searchable'] === true) && ($value = trim($column['search']['value']))) {
                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }

                // $operator = preg_match('~^\[(?<operator>[=!%<>]+)\].*$~', $value, $matches) ? $matches['operator'] : '=';
                $operator = preg_match('~^\[(?<operator>[INOR=!%<>•]+)\].*$~i', $value, $matches) ? strtoupper($matches['operator']) : '%•';
                $value    = preg_match('~^\[(?<operator>[INOR=!%<>•]+)\](?<term>.*)$~i', $value, $matches) ? $matches['term'] : $value;
                
                if ($this->caseInsensitive) {
                    $searchColumn = 'lower(' . $column[$this->columnField] . ')';
                    $filter       = "lower(:filter_{$i})";
                } else {
                    $searchColumn = $column[$this->columnField];
                    $filter       = ":filter_{$i}";
                }

                switch ($operator) {
                    case '!=': // Not equals; usage: [!=]search_term
                        $andX->add($query->expr()->neq($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '%%': // Like; usage: [%%]search_term
                        $andX->add($query->expr()->like($searchColumn, $filter));
                        $value = "%{$value}%";
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '<': // Less than; usage: [>]search_term
                        $andX->add($query->expr()->lt($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '>': // Greater than; usage: [<]search_term
                        $andX->add($query->expr()->gt($searchColumn, $filter));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case 'IN': // IN; usage: [IN]search_term,search_term  -> This equals OR with complete terms
                        $value  = explode(',', $value);
                        $params = [];

                        for ($j = 0; $j < count($value); $j++) {
                            $params[] = ":filter_{$i}_{$j}";
                        }
                        $andX->add($query->expr()->in($column[$this->columnField], implode(',', $params)));

                        for ($j = 0; $j < count($value); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", trim($value[$j]));
                        }
                        break;

                    case 'OR': // OR; usage: [IN]search_term,search_term  -> This equals OR with complete terms
                        $value  = explode(',', $value);
                        $params = [];
                        $orX    = $query->expr()->orX();

                        for ($j = 0; $j < count($value); $j++) {
                            $orX->add($query->expr()->like($column[$this->columnField], ":filter_{$i}_{$j}"));
                        }
                        $andX->add($orX);

                        for ($j = 0; $j < count($value); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", '%' . trim($value[$j]) . '%');
                        }
                        break;

                    case '><': // Between than; usage: [><]search_term,search_term
                        $value  = explode(',', $value);
                        $params = [];

                        for ($j = 0; $j < count($value); $j++) {
                            $params[] = ":filter_{$i}_{$j}";
                        }
                        $andX->add($query->expr()->between($column[$this->columnField], trim($params[0]), trim($params[1])));

                        for ($j = 0; $j < count($value); $j++) {
                            $query->setParameter("filter_{$i}_{$j}", $value[$j]);
                        }
                        break;

                    case '=': // Equals; usage: [=]search_term
                        $andX->add($query->expr()->eq($column[$this->columnField], ":filter_{$i}"));
                        $query->setParameter("filter_{$i}", $value);
                        break;

                    case '%': // Like(default); usage: [%]search_term
                    default:
                        $andX->add($query->expr()->like($column[$this->columnField], ":filter_{$i}"));
                        $value = "{$value}%";
                        $query->setParameter("filter_{$i}", $value);
                        break;
                }
            }
            if ($andX->count() >= 1) {
                $query->andWhere($andX);
            }
        }

        // Done
        return $query;
    }

    /**
     * @return int
     */
    public function getRecordsFiltered()
    {
        $query     = $this->getFilteredQuery();
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers);

        return $paginator->count();
    }

    /**
     * @return int
     */
    public function getRecordsTotal()
    {
        $query     = clone $this->queryBuilder;
        $paginator = new Paginator($query, $fetchJoinCollection = true);
        $paginator->setUseOutputWalkers($this->useOutputWalkers);

        return $paginator->count();
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return [
            'data'            => $this->getData(),
            'draw'            => $this->requestParams['draw'],
            'recordsFiltered' => $this->getRecordsFiltered(),
            'recordsTotal'    => $this->getRecordsTotal(),
        ];
    }

    /**
     * @param string $indexColumn
     *
     * @return static
     */
    public function withIndexColumn($indexColumn)
    {
        $this->indexColumn = $indexColumn;

        return $this;
    }

    /**
     * @param string|null $useOutputWalkers
     *                                      return static
     */
    public function setUseOutputWalkers($useOutputWalkers)
    {
        $this->useOutputWalkers = $useOutputWalkers;

        return $this;
    }

    /**
     * @param array $columnAliases
     *
     * @return static
     */
    public function withColumnAliases($columnAliases)
    {
        $this->columnAliases = $columnAliases;

        return $this;
    }

    /**
     * @param bool $caseInsensitive
     *
     * @return static
     */
    public function withCaseInsensitive($caseInsensitive)
    {
        $this->caseInsensitive = $caseInsensitive;

        return $this;
    }

    /**
     * @param string $columnField
     *
     * @return static
     */
    public function withColumnField($columnField)
    {
        $this->columnField = $columnField;

        return $this;
    }

    /**
     * @param ORMQueryBuilder|QueryBuilder $queryBuilder
     *
     * @return static
     */
    public function withQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * @param array $requestParams
     *
     * @return static
     */
    public function withRequestParams($requestParams)
    {
        $this->requestParams = $requestParams;

        return $this;
    }
}
