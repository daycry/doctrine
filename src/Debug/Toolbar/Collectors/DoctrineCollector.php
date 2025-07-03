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
            return '<h3>No Doctrine queries executed.</h3>';
        }

        $html = '<table>';
        $html .= '<thead><tr><th class="debug-bar-width6r">Time</th><th>SQL</th><th>Params</th></tr></thead><tbody>';

        foreach ($queries as $i => $query) {
            $sql      = $query['sql'] ?? '';
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql);
            $params   = htmlspecialchars(json_encode($query['params'] ?? []), ENT_QUOTES, 'UTF-8');
            $time     = isset($query['duration']) ? number_format($query['duration'], 4) : '';
            $html .= '<tr class="{class}" title="' . $sql . '" data-toggle="' . md5($sql) . '-trace">';
            $html .= '<td class="narrow">' . $time . '</td>';
            // Shorten SQL if too long (over 120 chars), show full SQL in tooltip
            $maxLen     = 120;
            $displaySql = mb_strlen($shortSql) > $maxLen ? mb_substr($shortSql, 0, $maxLen - 3) . '...' : $shortSql;
            $html .= '<td>' . $this->debugToolbarDisplay($displaySql) . '</td>';
            $html .= '<td>' . $params . '</td>';
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
                'query'     => $this->debugToolbarDisplay($query['sql']) ?? '',
            ];
        }

        return $data;
    }

    public function icon(): string
    {
        // Puedes usar el mismo icono que Database o uno personalizado
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhLY6A3YExLSwsA4nIycQDIDIhRWEBqamo/UNF/SjDQjF6ocZgAKPkRiFeEhoYyQ4WIBiA9QAuWAPEHqBAmgLqgHcolGQD1V4DMgHIxwbCxYD+QBqcKINseKo6eWrBioPrtQBq/BcgY5ht0cUIYbBg2AJKkRxCNWkDQgtFUNJwtABr+F6igE8olGQD114HMgHIxAVDyAhA/AlpSA8RYUwoeXAPVex5qHCbIyMgwBCkAuQJIY00huDBUz/mUlBQDqHGjgBjAwAAACexpph6oHSQAAAAASUVORK5CYII=';
    }

    /**
     * Returns string to display in debug toolbar
     */
    protected function debugToolbarDisplay(string $query): string
    {
        // Key words we want bolded
        static $highlight = [
            'AND',
            'AS',
            'ASC',
            'AVG',
            'BY',
            'COUNT',
            'DESC',
            'DISTINCT',
            'FROM',
            'GROUP',
            'HAVING',
            'IN',
            'INNER',
            'INSERT',
            'INTO',
            'IS',
            'JOIN',
            'LEFT',
            'LIKE',
            'LIMIT',
            'MAX',
            'MIN',
            'NOT',
            'NULL',
            'OFFSET',
            'ON',
            'OR',
            'ORDER',
            'RIGHT',
            'SELECT',
            'SUM',
            'UPDATE',
            'VALUES',
            'WHERE',
        ];

        $sql = esc($query);

        /**
         * @see https://stackoverflow.com/a/20767160
         * @see https://regex101.com/r/hUlrGN/4
         */
        $search = '/\b(?:' . implode('|', $highlight) . ')\b(?![^(&#039;)]*&#039;(?:(?:[^(&#039;)]*&#039;){2})*[^(&#039;)]*$)/';

        return preg_replace_callback($search, static fn ($matches): string => '<strong>' . str_replace(' ', '&nbsp;', $matches[0]) . '</strong>', $sql);
    }

    /**
     * Método público para testear debugToolbarDisplay
     */
    public function debugToolbarDisplayPublic(string $sql): string
    {
        return $this->debugToolbarDisplay($sql);
    }
}
