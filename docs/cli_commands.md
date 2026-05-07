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
# Map an existing database to entity classes
php cli-config.php orm:convert-mapping --namespace="App\Models\Entity\\" --force --from-database annotation .

# Generate getters and setters
php cli-config.php orm:generate-entities .

# Generate proxy classes
php cli-config.php orm:generate-proxies app/Models/Proxies
```

## Troubleshooting

If you receive:

```
[Semantical Error] The annotation "@JMS\Serializer\Annotation\ExclusionPolicy"
in class App\Models\Entity\Secret was never imported. Did you maybe forget to
add a "use" statement for this annotation?
```

run:

```bash
composer dump-autoload
```

to refresh the annotation registry.
