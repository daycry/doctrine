# Auditoría exhaustiva — `daycry/doctrine`

**Fecha:** 2026-06-06 · **Rama:** `refactor/audit-and-docs` · **Alcance:** capacidades, rendimiento, optimización, correctitud, seguridad, calidad, dependencias, tests y docs.

**Método:** 13 auditores en paralelo (uno por dimensión, leyendo el código real) → verificación adversarial *finding-por-finding* contra el código citado → crítico de completitud → síntesis. 112 agentes, 97 hallazgos, **95 verificados**, 2 rechazados. Cada hallazgo cita `archivo:línea` real.

---

## Resumen ejecutivo

El núcleo de la librería es **sólido, está bien tipado y bien probado (~90,5 %)**. La verificación adversarial degradó muchos "falta la capacidad X" a "falta una *superficie de configuración* de primera clase" — porque el *escape hatch* de Doctrine ya existe vía `getEm()->getConfiguration()`. Es decir: la mayor parte del trabajo de mejora es **aditivo y retrocompatible**, no corrección de fallos.

Dicho esto, hay un punto ciego sistemático: el **comportamiento operacional en producción y procesos largos** (workers CLI/cola, multi-grupo de BD), que es justo el caso de uso que la librería promociona. Ahí están los defectos reales de mayor valor.

**Lo más accionable, en una frase:**

1. **`reOpen()` no reconecta** — reutiliza la misma conexión DBAL muerta, contradiciendo lo que vende su documentación para workers. *(el defecto headline)*
2. El **middleware de captura de queries se monta siempre** (también en producción sin toolbar) y, a la vez, **no hay ningún logging de queries en producción** (las dos caras del mismo diseño).
3. Tres bugs de bajo esfuerzo y alto valor: **`[OR]` ignora `caseInsensitive`**, **DataTables no acota `length`** (`length=-1` → hidrata la tabla entera) y los **docs citan comandos `orm:convert-mapping`/`orm:generate-entities` eliminados en ORM 3**.
4. **`doctrine/dbal` sin fijar** en `composer.json` (el código usa APIs solo-DBAL-4) y **`jms/serializer-bundle` es dependencia dura sin un solo uso** en `src/`.
5. El mayor multiplicador de **capacidades** (tu objetivo #1): exponer como config los puntos de extensión que Doctrine ya ofrece — **Types personalizados, SQL Filters, event listeners, middlewares DBAL, repository factory, comandos `spark`, `cli-config` multi-grupo, logging de queries lentas, migraciones**.

---

## Veredicto por área

| Área | Estado | Acción de mayor valor |
|---|---|---|
| Núcleo (EM, conexión, multi-DB) | 🟢 Sólido | Arreglar `reOpen()` (reconexión real) |
| DataTables Builder | 🟡 Correcto pero con bordes | Acotar `length`; `[OR]` case-insensitive; cachear `count` |
| Caché (query/result/metadata/SLC) | 🟡 Funciona, sub-óptimo | Claves por grupo de BD; TTL persistente de metadata; región SLC con lock-dir |
| Capacidades ORM | 🟠 Mínimas superficies de config | Exponer Types/Filters/listeners/middlewares/repos |
| Tooling / CLI | 🟠 Solo `doctrine:publish` | Wrappers `spark` + `cli-config` multi-grupo |
| Seguridad | 🟢 Buena base (params, escaping, caps) | Cap de `length` y de nº de columnas |
| Observabilidad producción | 🔴 Inexistente | Logging PSR-3 de queries/slow-queries |
| Análisis estático | 🟡 Nivel 6 + muchas supresiones | Subir a nivel 8 arreglando 2-3 tipos raíz |
| Dependencias | 🟠 1 sin fijar, 1 muerta | Fijar `dbal ^4`; quitar `jms/serializer-bundle` |
| Documentación | 🟡 Drift puntual | Quitar comandos ORM 2; corregir `[OR]` |

---

## 1 · Correcciones prioritarias (P0) — fallos reales, esfuerzo bajo

> Todas retrocompatibles salvo donde se indica. Verificadas contra el código.

### 1.1 · `reOpen()` no reconecta una conexión muerta — **HIGH (correctitud)**
[src/Doctrine.php:220-225](src/Doctrine.php#L220-L225)

```php
public function reOpen(): void
{
    $em       = $this->getEm();
    $this->em = new EntityManager($em->getConnection(), $em->getConfiguration());
    $this->registerTypeMappings(config('Doctrine'));
}
```

Crea un nuevo `EntityManager` sobre **la misma `Connection` DBAL**. Nunca llama `close()`/`isConnected()`, así que tras un `wait_timeout` del servidor (*"MySQL server has gone away"*) devuelve un EM que **vuelve a fallar en la siguiente query**. Y [docs/usage.md:54](docs/usage.md#L54) lo vende exactamente como el remedio para "workers que acaban con un EntityManager cerrado".

**Fix:** cerrar la conexión para forzar la reconexión perezosa de DBAL antes de reconstruir el EM:
```php
public function reOpen(): void
{
    $connection = $this->getEm()->getConnection();
    if ($connection->isConnected()) {
        $connection->close(); // DBAL reconecta perezosamente en el próximo uso
    }
    $this->em = new EntityManager($connection, $this->getEm()->getConfiguration());
    $this->registerTypeMappings(config('Doctrine'));
}
```
Y corregir el docblock invertido de [docs/usage.md:62-64](docs/usage.md#L62-L64) (`getEm()` nunca devuelve null).

### 1.2 · DataTables no acota `length` → hidratación de tabla completa — **MEDIUM (seguridad/DoS)**
[src/DataTables/Builder.php:527-537](src/DataTables/Builder.php#L527-L537)

`applyPagination()` solo fija `setMaxResults` cuando `length > 0`. DataTables envía `length=-1` para *"All"*: el endpoint hidrata **toda la tabla filtrada como entidades gestionadas**. Es el vector de agotamiento de memoria más directo de la feature estrella (hay caps para `maxFilterValues` y para el término de búsqueda, pero no para el tamaño de página).

**Fix:** `withMaxPageLength(int)` con tope por defecto razonable; cuando `length <= 0` o supera el tope, aplicar el tope en vez de "sin límite".

### 1.3 · `[OR]` ignora `withCaseInsensitive(true)` — **MEDIUM (correctitud, confirmado)**
[src/DataTables/Builder.php:264-283](src/DataTables/Builder.php#L264-L283)

Las ramas `=`, `!=`, `<`, `>`, `%` envuelven en `lower(...)`; **`IN`, `OR` y `[><]` usan el campo crudo**. [docs/search_modes.md:76-77](docs/search_modes.md#L76-L77) documenta `[OR]` como case-insensitive. Envolver campo y placeholder en `lower()` cuando el flag está activo (igual que la búsqueda global).

### 1.4 · `doctrine/dbal` sin fijar en `composer.json` — **MEDIUM (dependencia)**
[composer.json:18-25](composer.json#L18-L25)

El código usa APIs **solo-DBAL-4** (`ServerVersionProvider`, firmas `Driver/Statement/Connection` de DBAL 4) pero `dbal` solo entra transitivamente; `orm ^3` permite resolver DBAL 3.8.x → fallo fatal latente. Añadir `"doctrine/dbal": "^4"`. (Trivial, y ya coincide con lo que [README.md:31](README.md#L31) afirma.)

### 1.5 · Docs citan comandos eliminados en ORM 3 — **HIGH (docs, confirmado)**
[README.md:126-127](README.md#L126-L127) · [docs/cli_commands.md:35-39](docs/cli_commands.md#L35-L39)

`orm:convert-mapping` y `orm:generate-entities` **no existen en Doctrine ORM 3**: los ejemplos fallan. Sustituir por los que sí envía ORM 3 (`orm:schema-tool:*`, `orm:validate-schema`, `orm:info`, `orm:mapping:describe`, `orm:run-dql`, `dbal:run-sql`).

### 1.6 · `getFromCacheOrQuery`: claves sin validar + footgun de entidades — **LOW→MEDIUM**
[src/Helpers/getFromCacheOrQuery.php:26-47](src/Helpers/getFromCacheOrQuery.php#L26-L47)

(a) `$cacheKey` se pasa a PSR-6 sin validar → `InvalidArgumentException` en runtime con caracteres reservados `{}()/\@:`. (b) Cachea el retorno *tal cual*: si el llamante devuelve entidades gestionadas/proxies, se serializan **detached** y revientan al rehidratar. Validar/normalizar la clave y documentar "solo escalares/arrays, nunca entidades".

---

## 2 · Expansión de capacidades (objetivo #1) — aditivo, retrocompatible

Patrón unificado: **una entrada en `Config\Doctrine` + un bucle en el constructor**, igual que ya se hace con las funciones DQL y `customTypeMappings`. Todo esto hoy solo es accesible "por debajo" vía `getEm()->getConfiguration()`; la mejora es hacerlo **first-class y documentado**.

| Capacidad | Valor | Boceto | Hallazgo |
|---|---|---|---|
| **Custom DBAL Types** (`Type::addType`) | Claves UUID/ULID, value objects | `public array $customTypes = []` + `if (!Type::hasType($n)) Type::addType($n,$c)` (guard obligatorio: estado global) | `cap-orm-features-3` |
| **SQL Filters** | Soft-delete, multi-tenant | `$sqlFilters`/`$enabledFilters` → `$config->addFilter()` + `getFilters()->enable()` (re-aplicar en `reOpen()`) | `cap-orm-features-4` |
| **Event listeners/subscribers** | `onFlush`, auditoría, hooks | `EventManager` construido desde config y pasado como 3er arg del EM (y en `reOpen()`) | `cap-orm-features-2` |
| **Middlewares DBAL del usuario** | Retry, logging, métricas | `$dbalMiddlewares` → `setMiddlewares([...$user, $toolbar])` (no sobrescribir) | `cap-orm-features-7` |
| **Repository factory / default repo** | DX de repos | `setDefaultRepositoryClassName` / `setRepositoryFactory` desde config | `cap-orm-features-5` |
| **Helpers de batch** | Jobs largos sin fugas | `toIterable()` + `clear()` periódico envuelto | `cap-orm-features-6` |
| **Binding `EntityManagerInterface`** | Inyección por constructor | Registrar el servicio para DI directa del EM | `cap-orm-features-1` |
| **Comandos `spark doctrine:*`** | `schema:update`, `cache:clear`, `info`, `validate`, `proxies` | `BaseCommand` que resuelve `service('doctrine')->getEm()` → `SingleManagerProvider` → comando ORM 3 vía `ArrayInput` | `cap-tooling-1` |
| **`cli-config` multi-grupo** | DDL contra `reporting`/`legacy` | `MultipleManagerProvider` + flag `--em` (hoy `SingleManagerProvider` solo default → **riesgo de DDL contra la BD equivocada**) | crítico |
| **Logging de queries en producción** | Observabilidad/slow-query | `Doctrine\DBAL\Logging\Middleware` (PSR-3) + `slowQueryThreshold` en config | crítico |
| **`doctrine/migrations`** | Esquema versionado | En `suggest` + registro condicional (`class_exists`) en el hook `$commands` ya vacío de `cli-config` | `cap-tooling-3` |

**Observabilidad multi-grupo (crítico):** el colector es **un singleton global** ([src/Config/Services.php:67-77](src/Config/Services.php#L67-L77)) compartido por todos los EM; las queries de `default`+`reporting`+`legacy` se mezclan en un panel sin atribución de conexión ([DoctrineQueryMiddleware.php:68-75](src/Debug/Toolbar/Collectors/DoctrineQueryMiddleware.php#L68-L75) no guarda identidad de grupo). Añadir la clave de grupo a cada query capturada.

---

## 3 · Rendimiento y optimización (objetivos #2 y #3)

### Producción (hot path)
- **Gatear el middleware/colector por `CI_DEBUG`** — [src/Doctrine.php:185-200](src/Doctrine.php#L185-L200) los monta siempre. En producción se pagan 3 asignaciones de clase anónima por *statement* + 2 `microtime` + push de array por query, para un panel que nunca se renderiza. *(Nuance del verificador: el buffer está acotado a 1000 con FIFO; no es una fuga, es overhead muerto.)* `perf-runtime-1/2`
- **Caché de metadata/query persistente** — [src/Doctrine.php:83,91,99](src/Doctrine.php#L83) usan el TTL volátil del framework (default 60 s) para metadata/DQL, que son inmutables entre despliegues. Pasar `defaultLifetime = 0` para esos pools (invalidación por clear explícito). `caching-arch-5`
- **Handler de caché desconocido → `ArrayAdapter`** — [src/Doctrine.php:102-103](src/Doctrine.php#L102-L103): cualquier handler distinto de file/redis/memcached (incluido el default `dummy` de CI4) deja metadata/query **sin persistencia entre requests**. Avisar o mapear explícitamente. `perf-runtime-3`

### DataTables
- **Cachear/inyectar `count`** — `recordsTotal`/`recordsFiltered` se recalculan en **cada draw** ([Builder.php:363-371,400-409](src/DataTables/Builder.php#L363-L371)). `withRecordsTotal(int|Closure)` y/o cache-aside opcional. `perf-datatables-4`
- **`fetchJoinCollection` opcional** — hard-coded `true` ([Builder.php:130](src/DataTables/Builder.php#L130)) fuerza fetch en 2 queries aunque la query no tenga colección. `withFetchJoinCollection(bool)` (default true por BC). `perf-datatables-3`
- **Hidratación read-only + `clear()`** en el hot path de DataTables (entidades gestionadas y change-tracked por página, sin tope de memoria). Exponer `HINT_READ_ONLY`. crítico

### Helper cache-aside
- `getFromCacheOrQuery` **construye el servicio Doctrine completo** solo para leer el pool ([helper:28-29](src/Helpers/getFromCacheOrQuery.php#L28)); además **sin protección anti-stampede**. Aligerar el acceso al pool y opcionalmente añadir lock. `caching-arch-7`

---

## 4 · Calidad, análisis estático y CI

- **Subir PHPStan a nivel 8** arreglando **dos tipos raíz** que generan la mayoría de errores: el parámetro `object` de `convertDbConfig` ([Doctrine.php:248](src/Doctrine.php#L248), ~23 de 43 errores nivel 8) y la unión `ORMQueryBuilder|QueryBuilder|null` del Builder. `static-analysis-2/1`
- **Reducir la lista de supresiones de Psalm** atacando su causa: el `?array $requestParams` nullable del Builder (familia `PossiblyNull*`) con un accesor privado tipado. `static-analysis-3`
- **`rector.php`** omite `TYPE_DECLARATION`, `CODE_QUALITY` y `STRICT_BOOLEANS`. `static-analysis-7`
- **Infection (mutation testing)** ausente pese al ~90 %: validaría la calidad de las aserciones. `testing-ci-3`
- **Gates de CI** faltantes: `composer validate`, `composer-normalize --dry-run`, y *security audit* de dependencias. `testing-ci-4`
- **Tests faltantes de alto valor:** mapeos de driver Postgres/SQLSRV/OCI8 de `convertDbConfig`, `reOpen`+`registerTypeMappings`, claves reservadas de `getFromCacheOrQuery`, rama null de `Memcached::getInstance()`. `testing-ci-2/6/7/8`

---

## 5 · Dependencias

- **Quitar `jms/serializer-bundle`** ([composer.json:23](composer.json#L23)) — **0 referencias en `src/`** (confirmado), pero arrastra `symfony/framework-bundle` y su árbol. Riesgo extra (crítico): un **segundo árbol Symfony** con restricciones independientes de `symfony/cache ^7` → superficie de conflicto del resolutor. La anotación que mencionan los docs vive en `jms/serializer` (ligero), no en el bundle.
- **Fijar `doctrine/dbal ^4`** (ver §1.4).
- `ext-redis`/`ext-memcached` en `suggest`; revisar `minimum-stability: dev`. `deps-compat-3/4`

---

## 6 · Documentación

Drift puntual a corregir: comandos ORM 2 (§1.5), `[OR]` case-insensitive (§1.3), "Requirements" dice DBAL ^4 sin requerirlo, docblock invertido de `reOpen()`, y documentar la **precondición no obvia**: el Paginator de DataTables **exige un SELECT de entidad** (un `SELECT u.id, u.name` escalar o con `GROUP BY` rompe de forma opaca). `docs-accuracy-*`, crítico

---

## Roadmap secuenciado

**Sprint 1 — Quick wins (trivial/small, mayoría BC):**
`reOpen()` reconexión · cap de `length` · `[OR]` case-insensitive · fijar `dbal ^4` · quitar `jms/serializer-bundle` · docs comandos ORM 3 · gatear middleware por `CI_DEBUG` · `cli-config` `__DIR__` · bounds-check en `applyOrdering` · TTL persistente de metadata.

**Sprint 2 — Capacidades (aditivo):**
`customTypes` · `sqlFilters` · `eventListeners` · `dbalMiddlewares` · logging de queries/slow-query en producción · comandos `spark doctrine:*`.

**Sprint 3 — Profundidad:**
`cli-config` multi-grupo (`--em`) · caché de `count` + `fetchJoinCollection` opcional + read-only en DataTables · claves de caché por grupo de BD · región SLC con lock-dir · PHPStan 8 + Infection + gates CI · `doctrine/migrations` (suggest) · tests faltantes.

---

## Apéndice A — 95 hallazgos verificados

> `*(nuance)*` = el verificador confirmó el hallazgo pero corrigió/calibró su alcance o severidad.
### Performance — hot path

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | performance | DBAL query-capturing middleware is wired unconditionally on every request, even in production with no toolbar *(nuance)* | `src/Doctrine.php:185-200` | small |
| low | performance | Collector accumulates every query in memory even when the toolbar will never render *(nuance)* | `src/Debug/Toolbar/Collectors/DoctrineCollector.php:53-60` | small |
| low | performance | Unrecognized cache handler silently falls back to ArrayAdapter, rebuilding metadata/query cache every request *(nuance)* | `src/Doctrine.php:102-103` | medium |
| low | performance | Full EntityManager + Configuration + cache adapters + DQL functions rebuilt on every cold service resolve *(nuance)* | `src/Doctrine.php:60-203` | medium |
| low | performance | Proxy autogeneration/native-lazy choice keyed to ENVIRONMENT can force per-request proxy regen in non-standard envs *(nuance)* | `src/Config/Doctrine.php:101, src/Doctrine.php:108-118` | small |
| low | performance | PhpFilesAdapter for the result cache makes result caching effectively unusable for dynamic data *(nuance)* | `src/Doctrine.php:80-84, 313-319` | small |
| low | performance | doctrineCollector and reset filter trigger full Doctrine service construction even when SLC stats disabled *(nuance)* | `src/Debug/Filters/DoctrineSlcReset.php:30-39` | trivial |
| info | performance | is_dir() filesystem stat over every entity path on every construction *(nuance)* | `src/Doctrine.php:72-76` | trivial |

### Performance — DataTables

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | performance | Count queries never cached; recordsTotal/recordsFiltered recomputed on every draw | `src/DataTables/Builder.php:363-371,400-409` | medium |
| low | performance | getResponse() re-clones/re-parses the filtered query for the data Paginator + redundant filtered-count Paginator *(nuance)* | `src/DataTables/Builder.php:378-411` | small |
| low | performance | fetchJoinCollection hard-coded true → two-query page fetch even when the query joins no collection *(nuance)* | `src/DataTables/Builder.php:130,388` | medium |
| low | correctness | DBAL QueryBuilder accepted by the union/docs but the Paginator path can only execute ORM QueryBuilder *(nuance)* | `src/DataTables/Builder.php:52,468,130` | large |
| info | performance | getData()+getRecordsFiltered() called separately re-run getFilteredQuery() twice *(nuance)* | `src/DataTables/Builder.php:123-139,351-358` | medium |
| info | capability | No way to pass Query hints (HINT_ENABLE_DISTINCT / read-only / fetch mode) to paginated queries *(nuance)* | `src/DataTables/Builder.php:130-138` | medium |
| info | correctness | getRecordsFiltered() omits the validate() every other entry point calls *(nuance)* | `src/DataTables/Builder.php:351-358` | trivial |

### Caching architecture

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | correctness | Result/query/SLC cache namespaces not keyed per DB group → cross-database key collisions return wrong data *(nuance)* | `src/Doctrine.php:81-99` | small |
| medium | correctness | SLC READ_WRITE entities throw LogicException — file-lock region directory never configured *(nuance)* | `src/Doctrine.php:150` | small |
| low | correctness | getFromCacheOrQuery passes $cacheKey to PSR-6 unvalidated — runtime exception on reserved chars *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:35` | trivial |
| low | correctness | getFromCacheOrQuery docblock claims 'ttl 0 = no expiration' but pool defaultLifetime overrides it *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:21,42-44` | trivial |
| low | performance | Metadata cache uses the volatile framework TTL (default 60s) instead of being persistent *(nuance)* | `src/Doctrine.php:83,91,99` | trivial |
| low | quality | createSecondLevelCachePool duplicates the adapter-construction switch already in __construct *(nuance)* | `src/Doctrine.php:78-104 vs 313-345` | medium |
| low | performance | Result-cache cache-aside helper has no stampede protection; concurrent misses all hit the DB *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:35-45` | small |
| info | quality | SLC region default lock lifetime hardcoded to 60 and not configurable *(nuance)* | `src/Doctrine.php:141-144` | trivial |

### Capabilities — ORM

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | capability | Custom DBAL Type registration (Type::addType) missing — only type *mappings* supported *(nuance)* | `src/Doctrine.php:230-237` | small |
| medium | capability | No SQL Filter registration — soft-delete and multi-tenant scoping cannot be wired first-class *(nuance)* | `src/Config/Doctrine.php (no filter property)` | small |
| low | capability | No EntityManagerInterface binding for constructor DI *(nuance)* | `src/Config/Services.php:17-33` | trivial |
| low | capability | Lifecycle event subscribers/listeners cannot be registered — EM built with an empty EventManager *(nuance)* | `src/Doctrine.php:200` | small |
| low | capability | No custom repository / repository factory wiring *(nuance)* | `src/Doctrine.php:106-203` | medium |
| low | capability | No batch-processing helpers (toIterable + periodic clear) for long jobs *(nuance)* | `src/Doctrine.php:217-225` | small |
| low | capability | User-defined DBAL middlewares cannot be composed — toolbar middleware hardcoded as the only entry *(nuance)* | `src/Doctrine.php:187-198` | small |
| low | capability | No naming/quoting strategy, default query hints, or custom hydrators configuration | `src/Doctrine.php:106-165` | small |

### Capabilities — tooling/CLI

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | capability | All ORM tasks require manual `php cli-config.php`; no spark wrappers exist *(nuance)* | `src/cli-config.php:71-74` | medium |
| medium | docs | Documented ORM CLI commands orm:convert-mapping / orm:generate-entities were REMOVED in ORM 3 | `docs/cli_commands.md:35-38, README.md:126-127` | trivial |
| low | capability | No doctrine/migrations integration; schema management limited to non-versioned schema-tool *(nuance)* | `src/cli-config.php:66-69` | large |
| low | dx | cli-config.php requires 'vendor/autoload.php' via CWD-relative path, breaking invocation from other dirs *(nuance)* | `src/cli-config.php:11` | trivial |
| low | dx | No health-check / diagnostics command *(nuance)* | `src/Config/Services.php:40-46` | medium |
| low | capability | No data-fixtures support tied to the Doctrine EntityManager *(nuance)* | `composer.json:18-36` | medium |
| low | dx | DoctrinePublish hardcodes cli-config destination to project root; no path/dbGroup options *(nuance)* | `src/Commands/DoctrinePublish.php:64` | small |

### Correctness — DataTables

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | correctness | [OR] operator ignores withCaseInsensitive(true), contradicting the docs | `src/DataTables/Builder.php:273-282` | small |
| low | correctness | applyOrdering indexes columns by client-supplied order.column with no bounds check *(nuance)* | `src/DataTables/Builder.php:510-512` | trivial |
| low | docs | Global search term silently truncated to 255 characters with no documentation *(nuance)* | `src/DataTables/Builder.php:170` | trivial |
| low | correctness | Per-column filter ignores searchableColumns whitelist (only global search is constrained) | `src/DataTables/Builder.php:180-182,205-313` | small |
| info | correctness | Per-column filter value has no length cap while global search truncates to 255 *(nuance)* | `src/DataTables/Builder.php:170,208` | small |
| info | correctness | parseFilterOperator fallback re-attaches the raw bracket prefix when preg_replace returns null *(nuance)* | `src/DataTables/Builder.php:332,337,345` | trivial |

### Correctness — core

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| low | correctness | dbGroup defaulting hardcodes 'tests' and overrides the consumer's defaultGroup in testing *(nuance)* | `src/Doctrine.php:193-195` | trivial |
| low | correctness | SQLite DSN detection is a case-sensitive substring match; whole-DSN lowercasing can corrupt file paths *(nuance)* | `src/Doctrine.php:253-254` | trivial |
| low | correctness | Postgres uses different DBAL drivers (charset-dropping 'pgsql' vs 'pdo_pgsql') for DSN vs discrete config *(nuance)* | `src/Doctrine.php:252,275-289` | small |
| low | correctness | Services singleton key lowercased but dbGroup passed to EM build is not — case-mismatch wrong-group instance *(nuance)* | `src/Config/Services.php:17-33` | trivial |
| low | quality | Boot::bootDoctrine duplicates the framework boot sequence and runs full HTTP init for a CLI bootstrap *(nuance)* | `src/Boot.php:12-33` | small |
| low | correctness | cli-config.php requires vendor/autoload.php via cwd-relative path before establishing FCPATH | `src/cli-config.php:11,20-25` | trivial |
| info | correctness | convertDbConfig sends 'port' => null and a charset for drivers that may not want them *(nuance)* | `src/Doctrine.php:281-289` | trivial |
| info | correctness | reOpen() correctly preserves middleware and type mappings — confirmed NOT a defect (idempotent) *(nuance)* | `src/Doctrine.php:220-237` | trivial |

### Security

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| low | security | Per-column filter/search loops have no upper bound on column count (request-amplification DoS) *(nuance)* | `src/DataTables/Builder.php:151,161,174,205` | small |
| low | security | applyOrdering() indexes the columns array with an unvalidated, attacker-controlled order index *(nuance)* | `src/DataTables/Builder.php:510-512` | trivial |
| low | security | Filter operators do not require the searchableColumns allow-list — relies solely on Doctrine to reject unmapped fields *(nuance)* | `src/DataTables/Builder.php:180-187` | small |
| info | security | getFromCacheOrQuery passes caller key verbatim to PSR-6 — reserved-char DoS and no tenant namespacing *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:26,35` | small |
| info | security | convertDbConfig copies $db->options and SSL options verbatim into DBAL params (config-trust boundary) *(nuance)* | `src/Doctrine.php:291-303` | small |
| info | security | Debug toolbar SQL/param rendering is correctly escaped (no XSS) — verified, no change needed *(nuance)* | `src/Debug/Toolbar/Collectors/DoctrineCollector.php:231-239,315` | trivial |
| info | security | DQL identifier regex is sufficient against injection but accepts unbounded dotted association paths | `src/DataTables/Builder.php:588-595` | trivial |

### Static analysis / quality

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | quality | Builder's ORMQueryBuilder\|QueryBuilder\|null union is the largest driver of Psalm's blanket suppressions; the DBAL member is non-functional *(nuance)* | `src/DataTables/Builder.php:52,146` | small |
| medium | quality | convertDbConfig() types its parameter as bare `object`, producing 23 of the 43 level-8 errors in Doctrine.php | `src/Doctrine.php:248,250-299` | medium |
| low | quality | Nullable $requestParams drives the PossiblyNull* suppression family; a private typed accessor eliminates it *(nuance)* | `src/DataTables/Builder.php:59,127` | small |
| low | correctness | Native cache-client return types are nullable, forcing per-call ArgumentTypeCoercion suppressions *(nuance)* | `src/Doctrine.php:88-91,322-339` | small |
| low | correctness | DoctrinePublish::$sourcePath declared string but assigned realpath() (string\|false) *(nuance)* | `src/Commands/DoctrinePublish.php:23,43` | small |
| low | quality | Two @var suppressions in Services.php caused by getSharedInstance() returning a too-narrow inferred type *(nuance)* | `src/Config/Services.php:27,71` | small |
| low | quality | rector.php omits TYPE_DECLARATION, CODE_QUALITY sets and STRICT_BOOLEANS *(nuance)* | `rector.php:31,68-86` | medium |
| info | quality | DQL custom-function registration loses the FunctionNode subtype, forcing argument.type at higher levels *(nuance)* | `src/Doctrine.php:168-183` | trivial |

### Dependencies

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | dependency | doctrine/dbal not required directly, yet code hard-depends on DBAL 4-only APIs *(nuance)* | `composer.json:18-25` | trivial |
| medium | dependency | jms/serializer-bundle is a hard runtime require but never used in src/ and drags in the Symfony framework stack *(nuance)* | `composer.json:23` | small |
| medium | dependency | Mandatory doctrine/dbal unpinned, exposing hard DBAL-internal coupling to silent major upgrades | `composer.json:18-25` | trivial |
| low | dependency | minimum-stability: dev set with no dev-stability dependency declared *(nuance)* | `composer.json:72-73` | trivial |
| low | dependency | ext-redis / ext-memcached runtime-required for those backends but absent from composer suggest *(nuance)* | `composer.json:18-36` | trivial |
| low | dependency | ~100 custom DQL functions from two third-party packages registered unconditionally, hard-coupling both *(nuance)* | `src/Config/Doctrine.php:161-280` | medium |
| info | dependency | PHP constraint uses open-ended >=8.2 while CI also tests an unreleased PHP 8.5 *(nuance)* | `composer.json:19` | trivial |

### DX / API

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | dx | doctrine:publish ships a 293-line config that re-declares everything the new base class already provides *(nuance)* | `src/Commands/DoctrinePublish.php:80-82` | small |
| low | dx | README/quick-start steer users to the public mutable `$em` property instead of getEm() *(nuance)* | `src/Doctrine.php:42, README.md:70,90,147` | trivial |
| low | dx | Builder fluent API mixes withX()/setX() naming; create() builds an unusable object until validate() throws *(nuance)* | `src/DataTables/Builder.php:82-85,490-498` | small |
| low | dx | Public exceptions span three unrelated hierarchies with no package marker interface *(nuance)* | `src/Doctrine.php:74,211` | medium |
| low | dx | getFromCacheOrQuery needs `use function` while doctrine_instance is a true global — inconsistent access models *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:5,26` | small |
| info | correctness | getFromCacheOrQuery silently degrades to no-cache via an inconsistent-null EM path that bypasses the null guard *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:28-33` | trivial |

### Testing & CI

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| medium | testing | convertDbConfig Postgres/SQLSRV/OCI8 driver mappings are entirely untested *(nuance)* | `src/Doctrine.php:248-308` | small |
| low | testing | Filter-operator unit tests reimplement a divergent regex instead of calling the real parseFilterOperator() *(nuance)* | `tests/DataTablesBuilderTest.php:286` | small |
| low | testing | No mutation testing (Infection) despite ~90% line coverage *(nuance)* | `composer.json:53-71` | medium |
| low | dependency | CI lacks composer-validate, composer-normalize enforcement, and dependency security audit gates *(nuance)* | `.github/workflows/*.yml` | small |
| low | testing | DataTables operator integration tests are MySQL-only; the SQLite leg never exercises the operator switch | `tests/DataTableTest.php` | medium |
| low | testing | registerTypeMappings / customTypeMappings effect never asserted, and reOpen re-mapping untested *(nuance)* | `src/Doctrine.php:230-237` | small |
| low | testing | getFromCacheOrQuery not tested with PSR-6 reserved-character keys *(nuance)* | `src/Helpers/getFromCacheOrQuery.php:35` | small |
| low | testing | Memcached::getInstance() null-narrowing (Memcache fallback) branch has no test | `src/Libraries/Memcached.php:36-40` | trivial |

### Docs

| Sev | Cat | Finding | Location | Effort |
|---|---|---|---|---|
| high | docs | CLI docs reference orm:convert-mapping and orm:generate-entities — both removed in Doctrine ORM 3 | `README.md:126-127, docs/cli_commands.md:35-39` | small |
| medium | docs | [OR] operator documented as case-insensitive but implementation ignores withCaseInsensitive() | `docs/search_modes.md:31,76-77` | trivial |
| low | docs | README/installation 'Requirements' list DBAL ^4 but composer.json does not require doctrine/dbal *(nuance)* | `README.md:31` | trivial |
| low | docs | No documentation for schema management / migrations workflow under ORM 3 | `docs/cli_commands.md:28-42` | small |
| info | docs | search_modes matrix omits that [IN] values are exact, not wildcarded *(nuance)* | `docs/search_modes.md:30` | trivial |
| info | docs | reOpen() doc says it 'throws if getEm() is null' — getEm() never returns null, wording inverts the contract *(nuance)* | `docs/usage.md:62-64` | trivial |

### Rechazados por la verificación (2)

- **`setUseOutputWalkers(true)` por defecto fuerza el walker caro en todo count/page** — El código existe (Builder.php:131,355,389…), pero el default `true` es la opción *segura* de Doctrine (sin él, queries con joins/HAVING devuelven counts erróneos). Es un trade-off defendible, no un defecto; ya es override-able.
- **`[IN]` construye la lista de placeholders como string con `implode(',', …)` pasado a `Expr::in()`** — El código existe (Builder.php:257) pero `Expr::in()` acepta `string|array` por contrato; produce DQL válido y los params se enlazan. Frágil/sin documentar, no incorrecto.

## Apéndice B — Metodología y trazabilidad

- Resultado completo del workflow (JSON, 357 KB) con `description`, `evidence`, `recommendation`, `corrections` y `verdict`/`confidence` por hallazgo: disponible en el output de la tarea `ww3z98kia`.
- 2 hallazgos **rechazados** por la verificación (al final del Apéndice A) y varios **no-defectos confirmados** (p. ej. el render del toolbar está correctamente escapado — sin XSS; `reOpen` sí preserva middleware y type-mappings; el default `OutputWalkers=true` es defendible).
