<?php

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;

class DoctrineCollector extends BaseCollector
{
    protected $hasTimeline   = true;
    protected $hasTabContent = true;
    protected $hasVarData    = false;
    protected $title         = 'Doctrine';

    /**
     * Queries ejecutadas (estático para compatibilidad con Toolbar)
     *
     * @var array
     */
    protected static $queries = [];

    public function addQuery(array $query): void
    {
        static::$queries[] = $query;
    }

    public function getQueries(): array
    {
        return static::$queries;
    }

    public function getTitle(bool $safe = false): string
    {
        return $this->title;
    }

    public function getBadgeValue(): int
    {
        return count(static::$queries);
    }

    public function getTitleDetails(): string
    {
        $queryCount = count(static::$queries);

        return $queryCount > 0 ? "({$queryCount} quer" . ($queryCount > 1 ? 'ies' : 'y') . ')' : '';
    }

    public function isEmpty(): bool
    {
        return static::$queries === [];
    }

    public function getData(): array
    {
        return [
            'queries' => $this->getQueries(),
        ];
    }

    /**
     * Return the HTML table for the Doctrine queries for use in the Debug Toolbar.
     */
    public function display(): string
    {
        $queries = $this->getQueries();
        if (empty($queries)) {
            return '<div class="ci-debug-panel"><p>No Doctrine queries executed.</p></div>';
        }

        $html = '<table>';
        $html .= '<thead><tr><th style="width:40px;">#</th><th>SQL</th><th>Params</th><th style="width:80px;">Time</th></tr></thead><tbody>';

        foreach ($queries as $i => $query) {
            $sql      = $query['sql'] ?? '';
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql);
            $params   = htmlspecialchars(json_encode($query['params'] ?? []), ENT_QUOTES, 'UTF-8');
            $time     = isset($query['duration']) ? number_format($query['duration'], 4) : '';
            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            // Shorten SQL if too long (over 120 chars), show full SQL in tooltip
            $maxLen     = 120;
            $displaySql = mb_strlen($shortSql) > $maxLen ? mb_substr($shortSql, 0, $maxLen - 3) . '...' : $shortSql;
            $html .= '<td style="width:200px;"><pre title="' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($displaySql, ENT_QUOTES, 'UTF-8') . '</pre></td>';
            $html .= '<td class="doctrine-params">' . $params . '</td>';
            $html .= '<td>' . $time . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Returns the HTML table for the Doctrine queries to be displayed in the Debug Toolbar.
     *
     * @return string HTML table with queries and tooltips for full SQL
     */
    public function displayHtml(): string
    {
        $queries = $this->display()['queries'] ?? [];
        if (empty($queries)) {
            return '<div class="ci-debug-doctrine-empty">No Doctrine queries executed.</div>';
        }
        $html = '<style>.ci-debug-doctrine-table { width: 100%; border-collapse: collapse; font-size: 13px; } .ci-debug-doctrine-table th, .ci-debug-doctrine-table td { border: 1px solid #ddd; padding: 6px 8px; } .ci-debug-doctrine-table th { background: #f5f5f5; } .ci-debug-doctrine-sql { max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; } .ci-debug-doctrine-table tr:nth-child(even) { background: #fafbfc; } .ci-debug-doctrine-duration { text-align: right; color: #888; } </style>';
        $html .= '<table class="ci-debug-doctrine-table">';
        $html .= '<thead><tr><th>#</th><th>SQL</th><th>Params</th><th>Time</th></tr></thead><tbody>';

        foreach ($queries as $i => $query) {
            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            $html .= '<td class="ci-debug-doctrine-sql" title="' . htmlspecialchars($query['sql']) . '">' . htmlspecialchars($query['display_sql']) . '</td>';
            $html .= '<td><pre style="margin:0;white-space:pre-wrap;">' . htmlspecialchars($query['params']) . '</pre></td>';
            $html .= '<td class="ci-debug-doctrine-duration">' . htmlspecialchars($query['duration']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

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

    public function icon(): string
    {
        // Puedes usar el mismo icono que Database o uno personalizado
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhLY6A3YExLSwsA4nIycQDIDIhRWEBqamo/UNF/SjDQjF6ocZgAKPkRiFeEhoYyQ4WIBiA9QAuWAPEHqBAmgLqgHcolGQD1V4DMgHIxwbCxYD+QBqcKINseKo6eWrBioPrtQBq/BcgY5ht0cUIYbBg2AJKkRxCNWkDQgtFUNJwtABr+F6igE8olGQD114HMgHIxAVDyAhA/AlpSA8RYUwoeXAPVex5qHCbIyMgwBCkAuQJIY00huDBUz/mUlBQDqHGjgBjAwAAACexpph6oHSQAAAAASUVORK5CYII=';
    }
}
