# Viewing Doctrine Queries in the Debug Toolbar

This library allows you to view all SQL queries executed by Doctrine directly in the CodeIgniter 4 Debug Toolbar, making it easier to analyze and debug your database interactions.

## How does it work?
- A custom Collector (`DoctrineCollector`) and Middleware (`DoctrineQueryMiddleware`) are included to capture all queries executed by Doctrine.
- The Middleware is automatically integrated when you instantiate `\Daycry\Doctrine\Doctrine`.
- The Collector exposes the query information to the Toolbar, letting you see queries in real time.

## Integration steps

1. **Register the Collector in the Toolbar**

   Open your `app/Config/Toolbar.php` file and add the Doctrine Collector to the `$collectors` array:

   ```php
   public $collectors = [
       // ...other collectors...
       \Daycry\Doctrine\Collectors\DoctrineCollector::class,
   ];
   ```

2. **Use the Doctrine class as usual**

   When you instantiate `\Daycry\Doctrine\Doctrine` (as a service, helper, or manually), the Middleware is automatically enabled and queries will be captured.

3. **View queries in the Toolbar**

   Whenever you execute any query with Doctrine, you will see a new "Doctrine" tab in the CodeIgniter 4 Debug Toolbar, showing all SQL queries executed during the current request.

## Notes and troubleshooting
- You do not need to call any method manually to enable the Collector or Middleware.
- If you do not see the "Doctrine" tab in the Toolbar, make sure the Collector is registered in `Toolbar.php` and that the Toolbar is enabled in your environment.
- Fully compatible with advanced connections (SQLite3, SSL, custom options) and Doctrine DBAL 4+.
