# Daycry Doctrine for CodeIgniter 4

Modern integration of Doctrine ORM/DBAL into CodeIgniter 4, featuring:

- Robust query logging via Debug Toolbar (DoctrineCollector)
- Cache adapters: file, Redis, Memcached, array (with runtime checks)
- DataTables server-side builder with secure filtering and operators
- Helpers for manual result caching and convenient service access
- Comprehensive tests and docs

## Documentation Index

- `installation.md` — Install and enable the library
- `configuration.md` — Publish and configure settings
- `usage.md` — Services, helpers, and caching examples
- `debug_toolbar.md` — Query logging and toolbar integration
- `datatables.md` — DataTables integration, troubleshooting
- `search_modes.md` — Per-column operator reference
- `DATATABLES_FIX.md` — Details of the LIKE operator fix
- `TEST_COVERAGE.md` — Testing and coverage overview

## Quick Start

```php
$doctrine = \Config\Services::doctrine();
$em       = $doctrine->em;

$repo = $em->getRepository(App\Models\Entity\WebProjects::class);
$item = $repo->findOneBy(['uuid' => $uuid]);
```

For advanced usage, see the docs linked above.
