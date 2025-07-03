<?php

namespace Daycry\Doctrine\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;

class DoctrineCollector extends BaseCollector
{
    protected $hasTimeline   = true;
    protected $hasTabContent = true;
    protected $hasVarData    = false;
    protected $title         = 'Doctrine';

    /**
     * Queries executed (static for compatibility with Toolbar)
     *
     * @var array
     */
    protected static $queries = [];

    /**
     * Add a query to the collector.
     */
    public function addQuery(array $query): void
    {
        static::$queries[] = $query;
    }

    /**
     * Get all stored queries.
     */
    public function getQueries(): array
    {
        return static::$queries;
    }

    /**
     * Get the collector title.
     */
    public function getTitle(bool $safe = false): string
    {
        return $this->title;
    }

    /**
     * Get the badge value for the toolbar.
     */
    public function getBadgeValue(): int
    {
        return count(static::$queries);
    }

    /**
     * Get the title details for the toolbar.
     */
    public function getTitleDetails(): string
    {
        $queryCount = count(static::$queries);

        return $queryCount > 0 ? "({$queryCount} quer" . ($queryCount > 1 ? 'ies' : 'y') . ')' : '';
    }

    /**
     * Check if the collector is empty.
     */
    public function isEmpty(): bool
    {
        return empty(static::$queries);
    }

    /**
     * Get the data for the toolbar template.
     */
    public function getData(): array
    {
        return [
            'queries' => $this->getQueries(),
        ];
    }

    /**
     * Return the data for the toolbar template.
     */
    public function display(): array
    {
        $queries = &static::$queries;
        $data    = [];

        foreach ($queries as $i => $query) {
            $sql = $query['sql'] ?? '';
            // Shorten SELECT for long queries
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql);
            $data[]   = [
                'class'       => '',
                'hover'       => '',
                'duration'    => isset($query['executionMS']) ? number_format($query['executionMS'], 2) . ' ms' : '',
                'sql'         => $sql,
                'display_sql' => $shortSql,
                'params'      => json_encode($query['params'] ?? []),
                'trace'       => [],
                'trace-file'  => '',
                'qid'         => md5($sql . $i),
            ];
        }

        return ['queries' => $data];
    }

    /**
     * Data for the toolbar timeline.
     */
    protected function formatTimelineData(): array
    {
        $data = [];

        foreach (static::$queries as $query) {
            $data[] = [
                'name'      => 'Doctrine Query',
                'component' => 'Doctrine',
                'start'     => $query['start'] ?? 0,
                'duration'  => $query['duration'] ?? 0,
                'query'     => $query['sql'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * Get the icon for the toolbar tab.
     */
    public function icon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhLY6A3YExLSwsA4nIycQDIDIhRWEBqamo/UNF/SjDQjF6ocZgAKPkRiFeEhoYyQ4WIBiA9QAuWAPEHqBAmgLqgHcolGQD1V4DMgHIxwbCxYD+QBqcKINseKo6eWrBioPrtQBq/BcgY5ht0cUIYbBg2AJKkRxCNWkDQgtFUNJwtABr+F6igE8olGQD114HMgHIxAVDyAhA/AlpSA8RYUwoeXAPVex5qHCbIyMgwBCkAuQJIY00huDBUz/mUlBQDqHGjgBjAwAAACexpph6oHSQAAAAASUVORK5CYII=';
    }
}
