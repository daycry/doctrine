# Configuration

## Key Concepts
- Config file: `app/Config/Doctrine.php` holds Doctrine integration settings.
- Publish step: copies the config and CLI file into your app namespace.
- Cache wiring: Query/Results/Metadata caches use the framework cache backend.
- Second-Level Cache (SLC): toggled via a simple boolean; shares backend and TTL with `Config\Cache`.

## Publish Configuration

After installation, publish the configuration files by running:

```
php spark doctrine:publish
```

This command copies the config file to your app namespace and a `cli-config.php` file for Doctrine CLI usage. By default, the config file is placed at `app/Config/Doctrine.php`.

Adjust settings as needed for your project.

## Options overview

- `setAutoGenerateProxyClasses` (bool): Auto-generate proxies in development.
- `entities` (array): Paths to your entity classes.
- `proxies` (string): Directory for generated proxies.
- `proxiesNamespace` (string): Namespace for generated proxies.
- `queryCache` / `resultsCache` / `metadataCache` (bool): Enable Doctrine caches backed by the framework cache.
- `queryCacheNamespace` / `resultsCacheNamespace` / `metadataCacheNamespace` (string): Namespaces used by caches.
- `metadataConfigurationMethod` (`attribute`|`xml`): Metadata driver to use.
- `isXsdValidationEnabled` (bool): Enable XML schema validation when using XML mapping.
- `secondLevelCache` (bool): Enable Doctrine Second-Level Cache. When true, SLC uses the same cache backend and `ttl` from `Config\\Cache`.

### Notes
- No adapter needs to be configured for SLC in `Config\\Doctrine`; the library wires SLC to the framework cache backend automatically.
- Ensure `entities` paths exist and are readable; misconfigured paths are validated early.
- For Redis/Memcached, PHP extensions must be present; the service throws descriptive errors if missing.

## Quick example

Enable SLC in your `app/Config/Doctrine.php`:

```php
<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
	public bool $setAutoGenerateProxyClasses = ENVIRONMENT === 'development';
	public array $entities = [APPPATH . 'Models/Entity'];
	public string $proxies = APPPATH . 'Models/Proxies';
	public string $proxiesNamespace = 'DoctrineProxies';

	public bool $queryCache = true;
	public string $queryCacheNamespace = 'doctrine_queries';
	public bool $resultsCache = true;
	public string $resultsCacheNamespace = 'doctrine_results';
	public bool $metadataCache = true;
	public string $metadataCacheNamespace = 'doctrine_metadata';

	public string $metadataConfigurationMethod = 'attribute';
	public bool $isXsdValidationEnabled = false;

	// Turn on Second-Level Cache; uses Config\Cache backend/ttl
	public bool $secondLevelCache = true;
}
```
