# Migrating to the next major version

## Runtime requirements

Use PHP 8.5, CodeIgniter 4.7+, Doctrine ORM 3.7+, DBAL 4.5+, Symfony Cache/Console 8,
and doctrineextensions 1.5+.
Composer enforces these requirements. Security advisories remain enabled for the
development environment; use a supported, patched PHP build for Composer updates.

Doctrine always uses native lazy objects. Remove `proxyFactory`, `proxies`,
`proxiesNamespace`, and `setAutoGenerateProxyClasses` from app/Config/Doctrine.php.
Do not generate proxy classes during deployment. The published config is now a
minimal subclass of the package config, containing only application overrides.

## DataTables API changes

| Removed API/behavior | Replacement |
|---|---|
| `getData()` | `$page = $builder->getPage(); $rows = $page->getItems();` |
| `getRecordsFiltered()` | `$page->getTotalCount()` on the same page |
| `setUseOutputWalkers(bool)` | `withOutputWalkers(bool)` |
| `withIndexColumn()` | Remove the call; identifiers come from ORM metadata |
| DBAL QueryBuilder input | Pass an ORM QueryBuilder; invalid types raise TypeError |
| Unlimited page size / `withMaxPageLength(0)` | A positive cap; default 200 |
| Zero page length or negative start | Send a positive length and non-negative start |

`getPage()` returns Doctrine's immutable WindowPage with rows, filtered total and
window metadata. Read its items and total from the same instance to avoid repeated
queries. `getRecordsTotal()` remains available for a standalone unfiltered count or
an injected `withRecordsTotal()` value. `getResponse()` returns the DataTables JSON
shape and casts draw to a non-negative integer.

`length=-1` is clamped to the page cap, and an omitted length defaults to 10.
For large exports, use an application-level batch/streaming endpoint.

The default output-walker behavior remains safe for entity queries and HAVING.
For scalar-select queries, use `withOutputWalkers(false)` when required. Queries
with to-many fetch joins should retain `withFetchJoinCollection(true)`.

## Caches, groups, and reopened managers

Result-cache pools use a zero default lifetime. Set an explicit lifetime on results
that must expire. `getFromCacheOrQuery(..., ttl: 0, ...)` persists until invalidation.
The file backend uses FilesystemAdapter for dynamic results/SLC and PhpFilesAdapter
for query/metadata caches.

Group names are case-sensitive. Implicit and explicit calls for the same effective
group share one instance. Under testing the effective implicit group is tests.
Service/cache keys encode exact group names; named groups may start with cold caches.

`reOpen()` uses the constructor configuration. It creates a fresh EntityManager and
reapplies configured listeners, types and enabled filters. Reapply runtime filter
parameters before issuing queries, and reload previously managed entities.

READ_WRITE SLC uses file locks. Processes sharing cached regions must share the
configured secondLevelCacheLockPath or supply a suitable concurrent region.

## Deployment sequence

1. Update dependencies using PHP 8.5 and apply the API/config changes above. In the
   consuming application, commit its lockfile and deploy with
   `composer install --no-dev --optimize-autoloader --no-interaction`.
2. Clear the package query/result/metadata pools for each configured group:
   `php spark doctrine:cache:clear --group <group>`.
3. Invalidate the application's SLC entity, collection and query regions when their
   mappings/data layout change. The cache:clear command does not evict SLC regions.
4. Run `php spark doctrine:cache:warmup --group <group>` to load metadata.
5. Run `php spark doctrine:doctor --group <group> --json` and require exit code zero.
6. Validate or migrate the schema with the application's migration process before
   routing traffic to the new release. Warmup and doctor do not modify the schema.

An ArrayAdapter warning means metadata is process-local. Use a persistent backend
for deployment warmup. Doctor validates mappings/connectivity/cache access, not
schema synchronization; use doctrine:validate for the latter.
