## Viewing Doctrine Queries in the Debug Toolbar

This feature shows all SQL queries executed by Doctrine in the CodeIgniter 4 Debug Toolbar.

### Key Concepts
- Collector: `DoctrineCollector` renders queries in the toolbar.
- Middleware: `DoctrineQueryMiddleware` captures DBAL queries automatically.
- Activation: enabled when you instantiate `\Daycry\Doctrine\Doctrine`.

### Setup
1. Register the collector in `app/Config/Toolbar.php`:
   ```php
   public $collectors = [
       // ... other collectors ...
       \Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::class,
   ];
   ```
2. Instantiate Doctrine (service, helper, or direct). Middleware auto-registers.

### Usage
- Execute any Doctrine queries; a "Doctrine" tab appears showing the captured SQL.

### Notes
- No extra method calls are required.
- If the tab is missing, confirm the collector registration and that the toolbar is enabled.
- Works with advanced connections (SQLite3, SSL, custom options) and Doctrine DBAL 4+.
- SQL display includes parameter bindings and timing; hover tooltips provide extra context if enabled.
- Queries executed by DataTables and cache lookups are also collected.
