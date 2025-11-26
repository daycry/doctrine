<?php

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use CodeIgniter\Debug\Toolbar\Collectors\BaseCollector;

class DoctrineCollector extends BaseCollector
{
    protected $hasTimeline   = true;
    protected $hasTabContent = true;
    protected $hasVarData    = false;
    protected $title         = 'Doctrine';
    /** Optional injected SLC logger for testing/override */
    protected $slcLogger     = null;

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

    /**
     * Allow injecting a Second-Level Cache logger (primarily for testing).
     */
    public function setSecondLevelCacheLogger($logger): void
    {
        $this->slcLogger = $logger;
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
        $details = $queryCount > 0 ? "({$queryCount} quer" . ($queryCount > 1 ? 'ies' : 'y') . ')' : '';

        // Append compact SLC badge if enabled
        try {
            $logger = $this->slcLogger;
            if ($logger === null && class_exists('Config\\Services') && method_exists(\Config\Services::class, 'doctrine')) {
                $doctrine = \Config\Services::doctrine();
                if (method_exists($doctrine, 'getSecondLevelCacheLogger')) {
                    $logger = $doctrine->getSecondLevelCacheLogger();
                }
            }
            if ($logger !== null) {
                $hits = null; $misses = null; $puts = null;
                // Doctrine ORM 3.x StatisticsCacheLogger exposes getHitCount(), getMissCount(), getPutCount()
                if (method_exists($logger, 'getHitCount')) {
                    $hits = (int) $logger->getHitCount();
                }
                if (method_exists($logger, 'getMissCount')) {
                    $misses = (int) $logger->getMissCount();
                }
                if (method_exists($logger, 'getPutCount')) {
                    $puts = (int) $logger->getPutCount();
                }
                // Fallback legacy names or public properties if any custom stub
                if ($hits === null && property_exists($logger, 'cacheHits')) { $hits = (int) $logger->cacheHits; }
                if ($misses === null && property_exists($logger, 'cacheMisses')) { $misses = (int) $logger->cacheMisses; }
                if ($puts === null && property_exists($logger, 'cachePuts')) { $puts = (int) $logger->cachePuts; }
                $hits   = $hits   ?? 0;
                $misses = $misses ?? 0;
                $puts   = $puts   ?? 0;
                $total  = $hits + $misses;
                $ratio  = $total > 0 ? round(($hits / $total) * 100) : 0;
                $slcBadge = " SLC:" . $hits . '/' . $misses . '/' . $puts . " (" . $ratio . "%)";
                $details = trim($details . $slcBadge);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $details;
    }

    public function isEmpty(): bool
    {
        // Si hay queries, el colector no está vacío
        if (!empty(static::$queries)) {
            return false;
        }
        // Sin queries: mostrar panel si SLC activo
        $data = $this->getData();
        if (!empty($data['slc']) && $data['slc']['enabled'] === true) {
            return false;
        }
        return true;
    }

    public function getData(): array
    {
        $slc = [
            'enabled' => false,
            'hits'    => null,
            'misses'  => null,
            'puts'    => null,
        ];

        // Try to read Second-Level Cache statistics via the Doctrine service
        try {
            $logger = $this->slcLogger;
            if ($logger === null) {
                $doctrine = \Config\Services::doctrine();
                if (method_exists($doctrine, 'getSecondLevelCacheLogger')) {
                    $logger = $doctrine->getSecondLevelCacheLogger();
                }
            }
            if ($logger !== null) {
                $slc['enabled'] = true;
                $slc['hits']   = method_exists($logger, 'getHitCount')  ? (int) $logger->getHitCount()  : null;
                $slc['misses'] = method_exists($logger, 'getMissCount') ? (int) $logger->getMissCount() : null;
                $slc['puts']   = method_exists($logger, 'getPutCount')  ? (int) $logger->getPutCount()  : null;
                // Fallback to legacy public properties if still null
                if ($slc['hits'] === null && property_exists($logger, 'cacheHits')) { $slc['hits'] = (int) $logger->cacheHits; }
                if ($slc['misses'] === null && property_exists($logger, 'cacheMisses')) { $slc['misses'] = (int) $logger->cacheMisses; }
                if ($slc['puts'] === null && property_exists($logger, 'cachePuts')) { $slc['puts'] = (int) $logger->cachePuts; }
                $slc['hits']   = $slc['hits']   ?? 0;
                $slc['misses'] = $slc['misses'] ?? 0;
                $slc['puts']   = $slc['puts']   ?? 0;
            }
        } catch (\Throwable $e) {
            // Ignore SLC stats errors; keep toolbar resilient
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
        $html = '';

        // Render SLC statistics if available
        $data = $this->getData();
        if (!empty($data['slc']) && $data['slc']['enabled'] === true) {
            $hits   = $data['slc']['hits'] ?? 0;
            $misses = $data['slc']['misses'] ?? 0;
            $puts   = $data['slc']['puts'] ?? 0;
            $html  .= '<h3>Second-Level Cache</h3>';
            $html  .= '<table class="debug-bar-table"><thead><tr><th class="debug-bar-width6r">Hits</th><th class="debug-bar-width6r">Misses</th><th class="debug-bar-width6r">Puts</th></tr></thead><tbody>';
            $html  .= '<tr><td class="narrow">' . (int) $hits . '</td><td class="narrow">' . (int) $misses . '</td><td class="narrow">' . (int) $puts . '</td></tr>';
            $html  .= '</tbody></table>';
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

        foreach ($queries as $i => $query) {
            $sql      = $query['sql'] ?? '';
            $shortSql = preg_replace('/(select)(.+?)(from)/is', '$1 ... $3', $sql);
            $params   = htmlspecialchars(json_encode($query['params'] ?? []), ENT_QUOTES, 'UTF-8');
            $time     = isset($query['duration']) ? number_format($query['duration'], 4) : '';
            $html .= '<tr class="{class}" title="' . $sql . '" data-toggle="' . md5($sql) . '-trace">';
            $html .= '<td class="narrow">' . $time . ' ms</td>';
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
