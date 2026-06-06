# CLI Commands

The package exposes one CodeIgniter Spark command and ships a Doctrine ORM
CLI bootstrap (`cli-config.php`).

## Package Commands

### `doctrine:publish`

```bash
php spark doctrine:publish
```

Copies two files into your application:

- `Config/Doctrine.php` → `app/Config/Doctrine.php` (extends
  `Daycry\Doctrine\Config\Doctrine`).
- `cli-config.php` → project root (used by the Doctrine ORM CLI below).

Re-running the command prompts before overwriting an existing file. Type
`n` at the prompt to abort safely; the command exits with status `1` to make
this detectable in CI scripts.

If you see *"APP_NAMESPACE not found in autoload psr4 configuration"*, ensure
`composer.json`'s `autoload.psr-4` declares your `App\` namespace correctly,
then run `composer dump-autoload`.

## Doctrine ORM CLI

These commands operate on the `cli-config.php` file generated above. Run them
from the project root (where `cli-config.php` lives):

```bash
# Validate that the mapping matches the database schema
php cli-config.php orm:validate-schema

# Preview the DDL needed to sync the schema (non-destructive)
php cli-config.php orm:schema-tool:update --dump-sql

# Apply the schema changes
php cli-config.php orm:schema-tool:update --force

# Create / drop the full schema
php cli-config.php orm:schema-tool:create
php cli-config.php orm:schema-tool:drop --force

# Generate proxy classes
php cli-config.php orm:generate-proxies app/Models/Proxies

# Inspect mappings and run ad-hoc DQL
php cli-config.php orm:info
php cli-config.php orm:mapping:describe "App\Models\Entity\User"
php cli-config.php orm:run-dql "SELECT u FROM App\Models\Entity\User u"
```

> **Doctrine ORM 3 note.** `orm:convert-mapping` and `orm:generate-entities`
> were removed in ORM 3 and are no longer available. Reverse-engineering a
> database into entity classes is not part of the ORM 3 toolchain — define your
> entities with PHP attributes (the default) or XML mapping instead.

For versioned schema migrations, install the optional
[`doctrine/migrations`](https://www.doctrine-project.org/projects/migrations.html)
package (listed under `suggest`) or use CodeIgniter's own migration system.
