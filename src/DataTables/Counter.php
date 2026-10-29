<?php

declare(strict_types=1);

namespace Daycry\Doctrine\DataTables;

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountOutputWalker;
use Doctrine\ORM\Tools\Pagination\CountWalker;

/**
 * Counts a DQL result without fetching a page or copying its result-set mapping.
 */
final class Counter
{
    public function count(QueryBuilder $builder, bool $outputWalkers = true): int
    {
        $original = $builder->getQuery();
        $query    = $builder->getEntityManager()->createQuery($builder->getDQL());
        $query->setParameters(clone $original->getParameters());

        foreach ($original->getHints() as $name => $value) {
            $query->setHint($name, $value);
        }
        if (! $query->hasHint(CountWalker::HINT_DISTINCT)) {
            $query->setHint(CountWalker::HINT_DISTINCT, true);
        }
        if ($outputWalkers) {
            $mapping = new ResultSetMapping();
            // Unquoted aliases are uppercase on Oracle/DB2 and lowercase on PostgreSQL.
            $mapping->addScalarResult('dctrn_count', 'count');
            $mapping->addScalarResult('DCTRN_COUNT', 'count');
            $query->setResultSetMapping($mapping);
            $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, CountOutputWalker::class);
        } else {
            $walkers   = $query->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
            $walkers   = is_array($walkers) ? $walkers : [];
            $walkers[] = CountWalker::class;
            $query->setHint(Query::HINT_CUSTOM_TREE_WALKERS, $walkers);
            $used = new Parser($query)->parse()->getParameterMappings();

            foreach ($query->getParameters()->toArray() as $parameter) {
                if (! isset($used[$parameter->getName()])) {
                    $query->getParameters()->removeElement($parameter);
                }
            }
        }

        try {
            return (int) array_sum(array_map(current(...), $query->getScalarResult()));
        } catch (NoResultException) {
            return 0;
        }
    }
}
