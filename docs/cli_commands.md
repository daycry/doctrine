# Console commands

The core provides `vendor/bin/daycry-doctrine`. The CI4 adapter adds Spark commands
and publishes an application-level `cli-config.php`.

## Choose an entry point

| Entry point | Configuration source | Named connection option |
|---|---|---|
| `php vendor/bin/daycry-doctrine doctrine-bootstrap.php ...` | PHP file returning Doctrine or ManagerRegistry | `--em` when using named managers |
| `php spark doctrine:* ...` | CI4 config and shared services | `--group` |
| `php cli-config.php orm:* ...` | Published CI4 bootstrap and database groups | `--em` |

`doctrine:publish`, `doctrine:info`, `doctrine:validate`,
`doctrine:schema:update` and `doctrine:cache:clear` are Spark commands.
The standalone console uses Doctrine's native `orm:*` commands for ORM operations.
Both consoles provide `doctrine:doctor` and `doctrine:cache:warmup`.

## Standalone bootstrap

Save as `doctrine-bootstrap.php` in the application:

```php
<?php

use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Doctrine;

$config = new Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'path' => __DIR__ . '/var/app.sqlite'];
$config->entities = [__DIR__ . '/src/Entity'];

return new Doctrine($config);
```

Create the database parent and entity directories first. The console loads
Composer before requiring this bootstrap. For multiple connections, return a
`ManagerRegistry` with factories as shown in the [standalone guide](standalone.md#named-managers-and-datatables).

```bash
php vendor/bin/daycry-doctrine doctrine-bootstrap.php list
php vendor/bin/daycry-doctrine doctrine-bootstrap.php orm:info
php vendor/bin/daycry-doctrine doctrine-bootstrap.php orm:validate-schema
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:doctor --json
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:cache:warmup --em reporting
```

`--em reporting` requires a registry containing that exact name. With a single
Doctrine instance, the console uses the name `default`.

## CodeIgniter publishing

After installing the adapter:

```bash
php spark doctrine:publish
```

The publisher writes `app/Config/Doctrine.php` extending
`Daycry\Doctrine\CodeIgniter4\Config\Doctrine` and `cli-config.php` in the project
root. It prompts before replacing existing files; choosing `n` exits with status 1.

## Spark commands

```bash
php spark doctrine:info --group reporting
php spark doctrine:validate --group reporting
php spark doctrine:schema:update --group reporting
php spark doctrine:cache:clear --group reporting
php spark doctrine:doctor --group reporting --json
php spark doctrine:cache:warmup --group reporting
```

Omitting `--group` selects the effective default group: `tests` under CI4's testing
environment, otherwise `Config\Database::$defaultGroup`.

`doctrine:schema:update` defaults to displaying SQL. Applying it requires `--force`.
`doctrine:cache:clear` clears query/result/metadata pools for the selected group;
it does not evict SLC regions.

## Doctor checks and output

Doctor checks whether the EntityManager is open, database connectivity, mapping
consistency and read/write/delete access to enabled query/result/metadata pools.
Cache probes use private random keys with a five-second TTL and delete only those
keys. They do not clear application caches or change database tables.

JSON reports contain `healthy` and `checks`. Spark additionally includes the resolved
`group`. Each check has `status` (`ok`, `warning`, `disabled` or `error`) and `detail`.
Exit 0 means no failed checks; exit 1 means initialization or a probe failed.
Disabled caches are valid, and process-local ArrayAdapters produce a warning.
Reports omit exception messages, connection strings and credentials.

SLC reports configuration only. Doctor does not verify region contents or database
schema synchronization; use `orm:validate-schema` or Spark `doctrine:validate`.

## Metadata warmup

Warmup validates mappings and loads metadata into the enabled metadata pool after
a cache access probe. Native lazy objects require no proxy generation.
Use a persistent pool for warmup across processes; disabled/array pools produce a
warning. Configuration, mapping or cache-access failures return exit 1.

Warmup does not populate query/results/SLC data and does not create or migrate
tables. Those operations need application-specific queries and migrations.

## Published CI4 ORM console

From the project root:

```bash
php cli-config.php orm:info --em reporting
php cli-config.php orm:validate-schema --em reporting
php cli-config.php orm:schema-tool:update --dump-sql --em reporting
```

The standalone console accepts the same native ORM commands after its bootstrap
argument. Use `list` and a command's `--help` to inspect available options.
ORM 3 does not provide `orm:convert-mapping` or `orm:generate-entities`.

Versioned migrations remain an application integration choice; installing the
suggested migrations package does not automatically wire migration commands into
these entry points.
