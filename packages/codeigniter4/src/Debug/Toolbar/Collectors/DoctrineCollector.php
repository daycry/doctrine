<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Override;
use Throwable;

class DoctrineCollector extends BaseCollector
{
    #[Override]
    protected $hasTimeline = true;

    #[Override]
    protected $hasTabContent = true;

    #[Override]
    protected $hasVarData = false;

    #[Override]
    protected $title = 'Doctrine';

    private readonly DoctrineCollectorState $state;

    public function __construct(?DoctrineCollectorState $state = null)
    {
        $this->state = $state ?? Services::doctrineCollectorState();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function addQuery(array $query): void
    {
        if ($this->state->maxQueries > 0 && count($this->state->queries) >= $this->state->maxQueries) {
            array_shift($this->state->queries);
        }
        $this->state->queries[] = $query;
    }

    public function setMaxQueries(int $maxQueries): void
    {
        $this->state->maxQueries = max(0, $maxQueries);
    }

    /**
     * Reset all collected queries. Useful in tests and per-request resets.
     */
    public function reset(): void
    {
        $this->state->queries = [];
    }

    /**
     * Allow injecting a Second-Level Cache logger (primarily for testing).
     */
    public function setSecondLevelCacheLogger(StatisticsCacheLogger $logger): void
    {
        $this->state->slcLogger = $logger;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQueries(): array
    {
        return $this->state->queries;
    }

    public function getTitle(bool $safe = false): string
    {
        return $this->title;
    }

    public function getBadgeValue(): int
    {
        return count($this->state->queries);
    }

    public function getTitleDetails(): string
    {
        $queryCount = count($this->state->queries);
        $details    = $queryCount > 0 ? "({$queryCount} quer" . ($queryCount > 1 ? 'ies' : 'y') . ')' : '';

        $logger = $this->resolveSlcLogger();
        if ($logger !== null) {
            $hits     = $logger->getHitCount();
            $misses   = $logger->getMissCount();
            $puts     = $logger->getPutCount();
            $total    = $hits + $misses;
            $ratio    = $total > 0 ? round(((float) $hits / (float) $total) * 100.0) : 0.0;
            $slcBadge = sprintf(' SLC:%d/%d/%d (%d%%)', $hits, $misses, $puts, $ratio);
            $details  = trim($details . $slcBadge);
        }

        return $details;
    }

    /**
     * Read existing manager statistics without creating a Doctrine service.
     */
    private function resolveSlcLogger(): ?StatisticsCacheLogger
    {
        try {
            return $this->state->slcLogger ?? Services::peekDoctrine()?->getSecondLevelCacheLogger();
        } catch (Throwable) {
            // Toolbar must never break the app.
            return null;
        }
    }

    public function isEmpty(): bool
    {
        // If we collected queries, the panel must render.
        if (! empty($this->state->queries)) {
            return false;
        }
        // No queries: still render the panel if SLC stats are enabled.
        $data = $this->getData();

        return ! (! empty($data['slc']) && $data['slc']['enabled'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $slc = [
            'enabled' => false,
            'hits'    => null,
            'misses'  => null,
            'puts'    => null,
        ];

        $logger = $this->resolveSlcLogger();
        if ($logger !== null) {
            $slc['enabled'] = true;
            $slc['hits']    = $logger->getHitCount();
            $slc['misses']  = $logger->getMissCount();
            $slc['puts']    = $logger->getPutCount();
        }

        return [
            'queries' => $this->getQueries(),
            'slc'     => $slc,
        ];
    }

    /**
     * Return the HTML table for the Doctrine queries for use in the Debug Toolbar.
     */
    public function display(): string
    {
        $queries = $this->getQueries();
        $html    = '';

        // Render SLC statistics if available
        $data = $this->getData();
        if (! empty($data['slc']) && $data['slc']['enabled'] === true) {
            $hits   = $data['slc']['hits'] ?? 0;
            $misses = $data['slc']['misses'] ?? 0;
            $puts   = $data['slc']['puts'] ?? 0;
            $html .= '<h3>Second-Level Cache</h3>';
            $html .= '<table class="debug-bar-table"><thead><tr><th class="debug-bar-width6r">Hits</th><th class="debug-bar-width6r">Misses</th><th class="debug-bar-width6r">Puts</th></tr></thead><tbody>';
            $html .= '<tr><td class="narrow">' . (int) $hits . '</td><td class="narrow">' . (int) $misses . '</td><td class="narrow">' . (int) $puts . '</td></tr>';
            $html .= '</tbody></table>';
            if (empty($queries)) {
                $html .= '<p>All results served from Second-Level Cache (no DB queries executed).</p>';
            }
        }

        if (empty($queries)) {
            if ($html === '') {
                return '<h3>No Doctrine queries executed.</h3>';
            }

            return $html; // show SLC info only
        }

        $html .= '<table>';
        $html .= '<thead><tr><th class="debug-bar-width6r">Time</th><th>SQL</th><th>Params</th></tr></thead><tbody>';

        foreach ($queries as $query) {
            $sql      = $query['sql'] ?? '';
            $sql      = is_string($sql) ? $sql : 'SQL unavailable';
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql) ?? $sql;
            $params   = htmlspecialchars(json_encode($query['params'] ?? []), ENT_QUOTES, 'UTF-8');
            $time     = isset($query['duration']) ? number_format($query['duration'], 4) : '';
            $html .= '<tr class="{class}" title="' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '" data-toggle="' . md5($sql) . '-trace">';
            $html .= '<td class="narrow">' . $time . ' ms</td>';
            // Shorten SQL if too long (over 120 chars), show full SQL in tooltip
            $maxLen     = 120;
            $displaySql = mb_strlen($shortSql) > $maxLen ? mb_substr($shortSql, 0, $maxLen - 3) . '...' : $shortSql;
            $html .= '<td>' . $this->debugToolbarDisplay($displaySql) . '</td>';
            $html .= '<td>' . $params . '</td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function formatTimelineData(): array
    {
        $data = [];

        foreach ($this->state->queries as $query) {
            $data[] = [
                'name'      => 'Doctrine Query',
                'component' => 'Doctrine',
                'start'     => $query['start'] ?? 0,
                'duration'  => $query['duration'] ?? 0,
                'query'     => $this->debugToolbarDisplay((string) ($query['sql'] ?? '')),
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

        return preg_replace_callback($search, static fn ($matches): string => '<strong>' . str_replace(' ', '&nbsp;', $matches[0]) . '</strong>', $sql) ?? $sql;
    }
}
