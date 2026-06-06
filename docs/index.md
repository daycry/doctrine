---
hide:
  - navigation
  - toc
---

<div class="hero" markdown>

# Daycry Doctrine

**Doctrine ORM 3** integration for **CodeIgniter 4** — a configured `EntityManager`, per-group multi-database support, query/result/metadata caching and **Second-Level Cache**, a safe server-side **DataTables Builder**, a debug-toolbar collector and **Spark / ORM CLI** tooling. Backward-compatible and production-ready.

[Get started :material-rocket-launch:](installation.md){ .md-button .md-button--primary }
[DataTables :material-table:](datatables.md){ .md-button }
[GitHub :material-github:](https://github.com/daycry/doctrine){ .md-button }

</div>

## Features

<div class="grid cards" markdown>

-   :material-database-cog:{ .lg .middle } __EntityManager, your way__

    ---

    Use it as a service, a helper or a plain object. `getEm()` with null-guards, `reOpen()` that actually reconnects a stale worker connection, and SSL / custom DBAL options passthrough.

    [:octicons-arrow-right-24: Usage](usage.md)

-   :material-table-search:{ .lg .middle } __Server-side DataTables__

    ---

    Filtering, ordering and pagination with safe operator parsing, whitelisted columns, page-size and filter-value caps, `fetchJoinCollection` opt-out and injectable totals.

    [:octicons-arrow-right-24: DataTables](datatables.md)

-   :material-magnify:{ .lg .middle } __Bracket search operators__

    ---

    Per-column `[%] [=] [!=] [>] [<] [IN] [OR] [><]` with case-insensitive support and strict, documented validation — `regex: true` is rejected by design.

    [:octicons-arrow-right-24: Search Modes](search_modes.md)

-   :material-layers-triple:{ .lg .middle } __Caching & Second-Level Cache__

    ---

    Query / result / metadata caches backed by `Config\Cache`, per-group namespacing, the `getFromCacheOrQuery()` cache-aside helper, and Doctrine SLC with an optional stats badge.

    [:octicons-arrow-right-24: Second-Level Cache](second_level_cache.md)

-   :material-database-multiple:{ .lg .middle } __Multi-database groups__

    ---

    Every `Config\Database` group resolves to its own cached Doctrine instance and isolated cache namespaces — and the ORM CLI can target any group with `--em=<group>`.

    [:octicons-arrow-right-24: CLI Commands](cli_commands.md)

-   :material-puzzle:{ .lg .middle } __Extensible by config__

    ---

    Register custom DBAL Types, SQL Filters (soft-delete / multi-tenant), event listeners/subscribers, DBAL middlewares and a default repository class — all re-applied on `reOpen()`.

    [:octicons-arrow-right-24: Configuration](configuration.md)

-   :material-bug-check:{ .lg .middle } __Debug toolbar & logging__

    ---

    A toolbar collector captures every DBAL query (debug only), with an SLC hit/miss/put badge — plus opt-in **PSR-3 production query logging** with a slow-query threshold.

    [:octicons-arrow-right-24: Debug Toolbar](debug_toolbar.md)

-   :material-console:{ .lg .middle } __Spark & ORM CLI__

    ---

    `doctrine:publish`, `doctrine:cache:clear`, `doctrine:validate`, `doctrine:info`, `doctrine:schema:update`, plus the full Doctrine ORM console via `cli-config.php`.

    [:octicons-arrow-right-24: CLI Commands](cli_commands.md)

</div>

## Quick start

```bash
composer require daycry/doctrine
php spark doctrine:publish
```

```php
$doctrine = \Config\Services::doctrine();
$user     = $doctrine->em->getRepository(\App\Models\Entity\User::class)->find(1);
```

See **[Installation](installation.md)** and **[Configuration](configuration.md)** to get going, or jump to the **[DataTables Builder](datatables.md)**.

## Requirements

- PHP **≥ 8.2**
- CodeIgniter **^4**
- Doctrine ORM **^3**, DBAL **^4**
- Symfony Cache **^7**
