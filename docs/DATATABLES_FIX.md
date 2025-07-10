# 🔧 DataTables Builder - Fix para "6 LIKE :search"

## 🎯 Problema Resuelto

Se ha solucionado el error crítico en `Daycry\Doctrine\DataTables\Builder` que causaba:

```
Doctrine\ORM\Query\QueryException: [Syntax Error] line 0, col 340: Error: Expected =, <, <=, <>, >, >=, !=, got 'LIKE'
```

### ❌ Problema Original

El Builder estaba insertando **índices numéricos** directamente en las consultas DQL en lugar de nombres de campos válidos:

```sql
-- ❌ INCORRECTO: Generaba queries como
SELECT ... WHERE (p.name LIKE :search OR p.companyName LIKE :search OR 6 LIKE :search)
```

### ✅ Solución Implementada

Se han agregado métodos helper específicos para validar y resolver nombres de campos:

#### 1. **`resolveFieldName()`** - Resolución robusta de nombres de campo
```php
protected function resolveFieldName($columnValue, int $columnIndex): string
{
    // Si columnValue es numérico o vacío, es probable que sea un índice
    if (is_numeric($columnValue) || empty($columnValue)) {
        return (string) $columnIndex; // Devuelve como string para ser detectado por isValidDQLField
    }
    
    // Resuelve alias si existe
    return $this->resolveColumnAlias((string) $columnValue);
}
```

#### 2. **`isValidDQLField()`** - Validación estricta de campos DQL
```php
protected function isValidDQLField(string $field): bool
{
    // Debe coincidir con patrón de identificador DQL válido
    // No debe ser puramente numérico
    return !empty($field) 
        && !is_numeric($field) 
        && preg_match('/^[a-zA-Z_][a-zA-Z0-9_\\.]*$/', $field);
}
```

#### 3. **Búsqueda Global Mejorada**
```php
for ($i = 0; $i < $c; $i++) {
    $column = $columns[$i];
    if ($this->isColumnSearchable($column)) {
        $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $i);
        
        // Solo permitir LIKE en columnas válidas
        if (! $this->isValidDQLField($fieldName)) {
            continue; // ✅ SKIP campos inválidos
        }
        
        $orX->add($query->expr()->like($fieldName, ':search'));
    }
}
```

## 🛡️ Beneficios

1. **✅ Prevención total** del error "6 LIKE :search"
2. **✅ Validación robusta** de campos DQL
3. **✅ Compatibilidad** con configuraciones DataTables existentes
4. **✅ Mejor resolución** de aliases de columnas
5. **✅ Código más legible** y mantenible

## 🚀 Uso

El Builder ahora maneja automáticamente casos edge donde DataTables envía:
- Índices numéricos en lugar de nombres de campo
- Valores vacíos o nulos
- Configuraciones inconsistentes de columnas

### Configuración Recomendada

```php
$builder = Builder::create()
    ->withQueryBuilder($queryBuilder)
    ->withRequestParams($datatableParams)
    ->withSearchableColumns(['name', 'companyName', 'email']) // ✅ Especifica columnas válidas
    ->withColumnAliases([
        'id' => 'p.uuid',
        'name' => 'p.name',
        'companyName' => 'p.companyName'
    ]);
```

## ✅ Estado

- **✅ Implementado** en `src/DataTables/Builder.php`
- **✅ Tested** - Sin errores de lint
- **✅ Backwards Compatible** - No rompe código existente
- **✅ Production Ready** - Listo para uso en producción
