<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;
use Config\Services;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Throwable;

class DoctrineCollector extends BaseCollector
{
    protected $hasTimeline   = true;
    protected $hasTabContent = true;
    protected $hasVarData    = false;
    protected $title         = 'Doctrine';

    /**
     * Optional injected SLC logger for testing/override
     */
    protected ?StatisticsCacheLogger $slcLogger = null;

    /**
     * Cached resolved SLC logger (separate sentinel: false = not yet resolved).
     */
    private false|StatisticsCacheLogger|null $resolvedLogger = false;

    /**
     * Cached payload returned by getData() to avoid duplicate Service lookups
     * during a single render (isEmpty() then display() call it).
     *
     * @var array<string, mixed>|null
     */
    private ?array $cachedData = null;

    /**
     * Maximum number of queries to keep in memory. Older entries are dropped
     * (FIFO) once the cap is reached. Use 0 to disable the cap.
     */
    protected int $maxQueries = 1000;

    /**
     * Queries collected during the request.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $queries = [];

    /**
     * @param array<string, mixed> $query
     */
    public function addQuery(array $query): void
    {
        if ($this->maxQueries > 0 && count($this->queries) >= $this->maxQueries) {
            array_shift($this->queries);
        }
        $this->queries[]  = $query;
        $this->cachedData = null;
    }

    public function setMaxQueries(int $maxQueries): void
    {
        $this->maxQueries = max(0, $maxQueries);
    }

    /**
     * Reset all collected queries. Useful in tests and per-request resets.
     */
    public function reset(): void
    {
        $this->queries        = [];
        $this->cachedData     = null;
        $this->resolvedLogger = false;
    }

    /**
     * Allow injecting a Second-Level Cache logger (primarily for testing).
     */
    public function setSecondLevelCacheLogger(StatisticsCacheLogger $logger): void
    {
        $this->slcLogger      = $logger;
        $this->resolvedLogger = false;
        $this->cachedData     = null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getTitle(bool $safe = false): string
    {
        return $this->title;
    }

    public function getBadgeValue(): int
    {
        return count($this->queries);
    }

    public function getTitleDetails(): string
    {
        $queryCount = count($this->queries);
        $details    = $queryCount > 0 ? "({$queryCount} quer" . ($queryCount > 1 ? 'ies' : 'y') . ')' : '';

        $logger = $this->resolveSlcLogger();
        if ($logger !== null) {
            $hits     = $logger->getHitCount();
            $misses   = $logger->getMissCount();
            $puts     = $logger->getPutCount();
            $total    = $hits + $misses;
            $ratio    = $total > 0 ? round(($hits / $total) * 100) : 0;
            $slcBadge = ' SLC:' . $hits . '/' . $misses . '/' . $puts . ' (' . $ratio . '%)';
            $details  = trim($details . $slcBadge);
        }

        return $details;
    }

    /**
     * Resolve and memoize the Second-Level Cache logger.
     * Returns null when SLC stats are not enabled or not yet wired up.
     */
    private function resolveSlcLogger(): ?StatisticsCacheLogger
    {
        if ($this->resolvedLogger !== false) {
            return $this->resolvedLogger;
        }

        try {
            if ($this->slcLogger !== null) {
                return $this->resolvedLogger = $this->slcLogger;
            }

            if (class_exists('Config\\Services') && method_exists(Services::class, 'doctrine')) {
                $doctrine = Services::doctrine();
                if (method_exists($doctrine, 'getSecondLevelCacheLogger')) {
                    return $this->resolvedLogger = $doctrine->getSecondLevelCacheLogger();
                }
            }
        } catch (Throwable) {
            // Toolbar must never break the app.
        }

        return $this->resolvedLogger = null;
    }

    public function isEmpty(): bool
    {
        // If we collected queries, the panel must render.
        if (! empty($this->queries)) {
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
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

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

        return $this->cachedData = [
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
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql);
            $params   = htmlspecialchars(json_encode($query['params'] ?? []), ENT_QUOTES, 'UTF-8');
            $time     = isset($query['duration']) ? number_format($query['duration'], 4) : '';
            $html .= '<tr class="{class}" title="' . htmlspecialchars((string) $sql, ENT_QUOTES, 'UTF-8') . '" data-toggle="' . md5((string) $sql) . '-trace">';
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
     * @return array<int, array<string, mixed>>
     */
    protected function formatTimelineData(): array
    {
        $data = [];

        foreach ($this->queries as $query) {
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
