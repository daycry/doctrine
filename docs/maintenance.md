# Maintenance and major-version work - 2026-10-28

The user approved a major release without backward-compatibility layers and selected
PHP 8.5 as the minimum runtime. The implementation uses ORM 3.7+, DBAL 4.5+, Symfony
Cache/Console 8, DoctrineExtensions 1.5+ and CodeIgniter 4.7+.
See [the migration guide](migration-major.md).

## Implemented behavior

- Preserve base-query parameters when DataTables adds filters.
- Share/reset services by the exact effective database group, with collision-free keys.
- Reopen EntityManagers using their original constructor configuration.
- Enforce native lazy objects; remove generated-proxy configuration.
- Use OffsetPaginator/WindowPage and a separate count walker for unfiltered totals.
- Remove getData/getRecordsFiltered/withIndexColumn/setUseOutputWalkers from Builder.
- Cap pages at 200 by default; reject disabling the cap and invalid page positions.
- Honor manual TTL zero, isolate array pools and configure READ_WRITE SLC locking.
- Share cache construction and query instrumentation; avoid initializing managers for stats reset.
- Publish a minimal configuration subclass.
- Add doctor JSON/readiness probes and metadata warmup, with failure exit codes and redacted errors.
- Remove unused VCR dependencies and the redundant post-update autoload script.
- Align test namespaces with PSR-4 and exclude timestamped framework migrations
  from the development classmap; CodeIgniter discovers those migrations itself.
- Analyze optional extensions with stubs and correct source typing without lowering analysis levels.
- Run CI on PHP 8.5 with highest/minimum non-vulnerable dependencies and coverage on one matrix job.

## Evidence and review

The first maintenance pass measured code duplication dropping from 8.2% to 4.4%
using the code-health heuristic. This measurement predates the new operational tools
and is not a production latency benchmark.

Pagination regressions verify two SELECTs for an unfiltered page and three for a
filtered page when fetchJoinCollection=false. Further cases cover HAVING/grouped
counts, select-only parameters, source-query preservation and the default page cap.

Doctor probes use random cache keys with a five-second TTL and delete only their
own keys. Tests verify failed writes, closed managers, redaction and preservation
of application cache entries. Warmup verifies cache access and never creates tables.

Composer update succeeds on the installed PHP 8.5.12-1 Windows build with security
advisories active. The build/php85.ini used locally is ignored and does not change
the global Laragon runtime. Redis responds on 127.0.0.1:6379. The official Redis
6.3.0 PHP 8.5 NTS x64 DLL is loaded from build/redis-php85/package for local tests.
Memcached 1.6.8 responds on 127.0.0.1:11211. The official Memcached 3.4.0 PHP 8.5
NTS x64 DLL and its auxiliary libraries are under build/memcached-php85/package.
The local build/php85-caches.ini loads both cache extensions; the Memcached package
directory is prepended to PATH for its auxiliary DLLs. CI requires both extensions.

## Candidates for later work

- Optional Doctrine Migrations wiring.
- Lifecycle management across multiple worker managers.
- Database-specific DQL function profiles and typed DataTables columns.
- Stampede protection and independent configurable metadata/query lifetimes.

## Final validation

- PHP 8.5.12 with highest dependencies, Redis and Memcached: 198 tests, 507 assertions, 2 skips.
- Redis-specific tests: 3 tests, 7 assertions, no skips.
- Memcached-specific tests: 3 tests, 6 assertions, no skips.
- PHP 8.5.12 with minimum non-vulnerable dependencies, Redis and Memcached in an isolated copy:
  198 tests, 507 assertions, 2 skips.
- The two remaining skips cover SSL/custom connection options absent from the local configuration.
- PHPStan level 6 and Psalm level 6: no errors; Psalm reports no informational issues.
- Rector dry run and PHP CS Fixer dry run: no changes.
- Composer strict validation and MkDocs strict build pass.
- `composer dump-autoload --optimize --strict-psr` generates the optimized local
  autoloader without PSR-4 warnings (7,532 mapped classes including development tools).
- Four independent review lenses (requirements, correctness, security and performance)
  closed without pending findings.

The minimum development dependency set emits three PHP deprecations from Amp's DNS
and socket autoload functions before PHPUnit starts. The highest set is clean.
These are transitive development-tool dependencies, not package runtime code.
No local coverage or remote GitHub Actions result is claimed.

The local vendor retains development tools for testing and analysis. In a consuming
application, production deployment should install its committed lockfile with
`composer install --no-dev --optimize-autoloader --no-interaction`. Regenerate the
optimized classmap after dependency changes; the package does not impose Composer
configuration on consuming applications.
