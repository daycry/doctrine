<?php

namespace Daycry\Doctrine\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;

class DoctrineCollector extends BaseCollector
{
    protected $queries = [];

    public function addQuery(array $query)
    {
        $this->queries[] = $query;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getTitle(bool $safe = false): string
    {
        return 'Doctrine';
    }

    public function getData(): array
    {
        return [
            'queries' => $this->getQueries(),
        ];
    }
}
