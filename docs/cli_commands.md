# CLI Commands

Doctrine provides several CLI commands for working with your entities and database.

## Common Commands

- **Mapping the database to entity classes:**

  ```sh
  php cli-config.php orm:convert-mapping --namespace="App\Models\Entity\\" --force --from-database annotation .
  ```

- **Generate getters & setters:**

  ```sh
  php cli-config.php orm:generate-entities .
  ```

- **Generate proxy classes:**

  ```sh
  php cli-config.php orm:generate-proxies app/Models/Proxies
  ```

If you receive the following error:

```
[Semantical Error] The annotation "@JMS\Serializer\Annotation\ExclusionPolicy" in class App\Models\Entity\Secret was never imported. Did you maybe forget to add a "use" statement for this annotation?
```

Run:

```
composer dump-autoload
```
