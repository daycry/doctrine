<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Tests;

use CodeIgniter\Exceptions\InvalidArgumentException;
use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\DataTables\Builder;

/**
 * Comprehensive tests for DataTables Builder
 * Tests the fix for "6 LIKE :search" error and all edge cases
 */
class DataTablesBuilderTest extends CIUnitTestCase
{
    private Builder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = Builder::create();
    }

    /**
     * Test static factory method
     */
    public function testStaticCreate(): void
    {
        $builder = Builder::create();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    /**
     * Test resolveFieldName with valid field name
     */
    public function testResolveFieldNameWithValidField(): void
    {
        $builder = new class extends Builder {
            public function testResolveFieldName($columnValue, int $columnIndex): string
            {
                return $this->resolveFieldName($columnValue, $columnIndex);
            }
        };

        $result = $builder->testResolveFieldName('name', 0);
        $this->assertEquals('name', $result);
    }

    /**
     * Test resolveFieldName with numeric value returns index as string
     * This is the key fix for "6 LIKE :search" error
     */
    public function testResolveFieldNameWithNumericValue(): void
    {
        $builder = new class extends Builder {
            public function testResolveFieldName($columnValue, int $columnIndex): string
            {
                return $this->resolveFieldName($columnValue, $columnIndex);
            }
        };

        // Numeric values should return the column index as string
        $result = $builder->testResolveFieldName(6, 2);
        $this->assertEquals('2', $result);
        
        $result = $builder->testResolveFieldName('123', 1);
        $this->assertEquals('1', $result);
    }

    /**
     * Test resolveFieldName with empty value returns index as string
     */
    public function testResolveFieldNameWithEmptyValue(): void
    {
        $builder = new class extends Builder {
            public function testResolveFieldName($columnValue, int $columnIndex): string
            {
                return $this->resolveFieldName($columnValue, $columnIndex);
            }
        };

        $result = $builder->testResolveFieldName('', 3);
        $this->assertEquals('3', $result);
        
        $result = $builder->testResolveFieldName(null, 4);
        $this->assertEquals('4', $result);
    }

    /**
     * Test resolveFieldName with alias resolution
     */
    public function testResolveFieldNameWithAlias(): void
    {
        $builder = new class extends Builder {
            public function testResolveFieldName($columnValue, int $columnIndex): string
            {
                return $this->resolveFieldName($columnValue, $columnIndex);
            }
        };

        $builder->withColumnAliases(['name' => 'p.name', 'email' => 'u.email']);
        
        $result = $builder->testResolveFieldName('name', 0);
        $this->assertEquals('p.name', $result);
        
        $result = $builder->testResolveFieldName('email', 1);
        $this->assertEquals('u.email', $result);
        
        // Non-aliased field should remain unchanged
        $result = $builder->testResolveFieldName('company', 2);
        $this->assertEquals('company', $result);
    }

    /**
     * Test isValidDQLField with valid field names
     */
    public function testIsValidDQLFieldWithValidFields(): void
    {
        $builder = new class extends Builder {
            public function testIsValidDQLField(string $field): bool
            {
                return $this->isValidDQLField($field);
            }
        };

        // Valid field names
        $this->assertTrue($builder->testIsValidDQLField('name'));
        $this->assertTrue($builder->testIsValidDQLField('p.name'));
        $this->assertTrue($builder->testIsValidDQLField('_underscore'));
        $this->assertTrue($builder->testIsValidDQLField('field123'));
        $this->assertTrue($builder->testIsValidDQLField('entity.field_name'));
        $this->assertTrue($builder->testIsValidDQLField('user.profile.name'));
    }

    /**
     * Test isValidDQLField with invalid field names (prevents "6 LIKE :search" error)
     */
    public function testIsValidDQLFieldWithInvalidFields(): void
    {
        $builder = new class extends Builder {
            public function testIsValidDQLField(string $field): bool
            {
                return $this->isValidDQLField($field);
            }
        };

        // Invalid field names that would cause DQL errors
        $this->assertFalse($builder->testIsValidDQLField(''));
        $this->assertFalse($builder->testIsValidDQLField('6'));        // The problematic case
        $this->assertFalse($builder->testIsValidDQLField('123'));
        $this->assertFalse($builder->testIsValidDQLField('0'));
        $this->assertFalse($builder->testIsValidDQLField('6field'));   // Starts with number
        $this->assertFalse($builder->testIsValidDQLField('field-name')); // Contains dash
        $this->assertFalse($builder->testIsValidDQLField('field name')); // Contains space
        $this->assertFalse($builder->testIsValidDQLField('field@name')); // Contains @
    }

    /**
     * Test isColumnSearchable with various column configurations
     */
    public function testIsColumnSearchable(): void
    {
        $builder = new class extends Builder {
            public function testIsColumnSearchable(array $column): bool
            {
                return $this->isColumnSearchable($column);
            }
        };

        // Valid searchable column with boolean true
        $this->assertTrue($builder->testIsColumnSearchable([
            'searchable' => true,
            'data' => 'name'
        ]));

        // Valid searchable column with string 'true' (DataTables sends this)
        $this->assertTrue($builder->testIsColumnSearchable([
            'searchable' => 'true',
            'data' => 'name'
        ]));

        // Not searchable (boolean false)
        $this->assertFalse($builder->testIsColumnSearchable([
            'searchable' => false,
            'data' => 'name'
        ]));

        // Not searchable (string 'false')
        $this->assertFalse($builder->testIsColumnSearchable([
            'searchable' => 'false',
            'data' => 'name'
        ]));

        // Missing searchable field
        $this->assertFalse($builder->testIsColumnSearchable([
            'data' => 'name'
        ]));

        // Missing data field
        $this->assertFalse($builder->testIsColumnSearchable([
            'searchable' => true
        ]));

        // Empty data field
        $this->assertFalse($builder->testIsColumnSearchable([
            'searchable' => true,
            'data' => ''
        ]));
    }

    /**
     * Test fluent configuration methods
     */
    public function testFluentConfiguration(): void
    {
        $result = $this->builder
            ->withSearchableColumns(['name', 'email'])
            ->withColumnAliases(['name' => 'u.name'])
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withIndexColumn('id')
            ->setUseOutputWalkers(false);
        
        $this->assertSame($this->builder, $result);
    }

    /**
     * Test validation error cases
     */
    public function testValidationErrors(): void
    {
        // Test missing QueryBuilder
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('QueryBuilder is not set.');
        
        $this->builder
            ->withRequestParams(['columns' => [['data' => 'name']]])
            ->getData();
    }

    /**
     * Test validation with missing columns
     */
    public function testValidationMissingColumns(): void
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request parameters or columns are not set.');
        
        $this->builder
            ->withQueryBuilder($queryBuilder)
            ->withRequestParams([])
            ->getData();
    }

    /**
     * Test validation with empty columns array
     */
    public function testValidationEmptyColumns(): void
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request parameters or columns are not set.');
        
        $this->builder
            ->withQueryBuilder($queryBuilder)
            ->withRequestParams(['columns' => []])
            ->getData();
    }

    /**
     * Test filter operator parsing and normalization
     */
    public function testFilterOperatorParsing(): void
    {
        $builder = new class extends Builder {
            public function testParseOperator(string $value): string
            {
                // Simulate the operator parsing logic from getFilteredQuery
                $operator = preg_match('~^\[(?<operator>[A-Z!=%<>•]+)\].*$~i', $value, $matches) ? strtoupper($matches['operator']) : '%•';
                
                // Normalize operator
                if (in_array($operator, ['LIKE', '%%'], true)) {
                    $operator = '%';
                }
                
                $validOperators = ['!=', '<', '>', 'IN', 'OR', '><', '=', '%'];
                if (!in_array($operator, $validOperators, true)) {
                    $operator = '%';
                }
                
                return $operator;
            }
        };

        // Test valid operators
        $this->assertEquals('=', $builder->testParseOperator('[=]test'));
        $this->assertEquals('!=', $builder->testParseOperator('[!=]test'));
        $this->assertEquals('<', $builder->testParseOperator('[<]10'));
        $this->assertEquals('>', $builder->testParseOperator('[>]10'));
        $this->assertEquals('%', $builder->testParseOperator('[%]test'));
        $this->assertEquals('IN', $builder->testParseOperator('[IN]val1,val2'));
        $this->assertEquals('OR', $builder->testParseOperator('[OR]val1,val2'));
        $this->assertEquals('><', $builder->testParseOperator('[><]1,10'));

        // Test operator normalization
        $this->assertEquals('%', $builder->testParseOperator('[LIKE]test'));
        $this->assertEquals('%', $builder->testParseOperator('[%%]test'));
        
        // Test invalid operators default to %
        $this->assertEquals('%', $builder->testParseOperator('[INVALID]test'));
        $this->assertEquals('%', $builder->testParseOperator('[XYZ]test'));
        
        // Test no operator defaults to %
        $this->assertEquals('%', $builder->testParseOperator('test'));
    }

    /**
     * Test searchable columns restriction functionality
     */
    public function testSearchableColumnsRestriction(): void
    {
        $builder = new class extends Builder {
            public function testSearchableRestriction(array $searchableColumns, string $field): bool
            {
                $this->searchableColumns = $searchableColumns;
                
                // Simulate the logic from getFilteredQuery
                if (!empty($this->searchableColumns) && !in_array($field, $this->searchableColumns, true)) {
                    return false; // Should be skipped
                }
                
                return true; // Should be included
            }
        };

        // When searchableColumns is empty, all fields should be allowed
        $this->assertTrue($builder->testSearchableRestriction([], 'name'));
        $this->assertTrue($builder->testSearchableRestriction([], 'email'));

        // When searchableColumns is set, only listed fields should be allowed
        $this->assertTrue($builder->testSearchableRestriction(['name', 'email'], 'name'));
        $this->assertTrue($builder->testSearchableRestriction(['name', 'email'], 'email'));
        $this->assertFalse($builder->testSearchableRestriction(['name', 'email'], 'phone'));
        $this->assertFalse($builder->testSearchableRestriction(['name'], 'email'));
    }

    /**
     * Test complete field validation pipeline (the core fix)
     */
    public function testCompleteFieldValidationPipeline(): void
    {
        $builder = new class extends Builder {
            public function testFieldValidationPipeline($columnValue, int $columnIndex, array $searchableColumns = []): bool
            {
                $this->searchableColumns = $searchableColumns;
                
                // Step 1: Resolve field name
                $fieldName = $this->resolveFieldName($columnValue, $columnIndex);
                
                // Step 2: Check searchable columns restriction
                if (!empty($this->searchableColumns) && !in_array($fieldName, $this->searchableColumns, true)) {
                    return false;
                }
                
                // Step 3: Validate DQL field (this prevents "6 LIKE :search")
                if (!$this->isValidDQLField($fieldName)) {
                    return false;
                }
                
                return true;
            }
        };

        // Valid fields should pass
        $this->assertTrue($builder->testFieldValidationPipeline('name', 0));
        $this->assertTrue($builder->testFieldValidationPipeline('p.name', 0));

        // Numeric fields should be rejected (prevents "6 LIKE :search")
        $this->assertFalse($builder->testFieldValidationPipeline(6, 0));
        $this->assertFalse($builder->testFieldValidationPipeline('123', 0));
        $this->assertFalse($builder->testFieldValidationPipeline('', 0)); // Empty becomes index "0"

        // Searchable columns restriction should work
        $this->assertTrue($builder->testFieldValidationPipeline('name', 0, ['name', 'email']));
        $this->assertFalse($builder->testFieldValidationPipeline('phone', 0, ['name', 'email']));

        // Combined: numeric field with searchable restriction (double protection)
        $this->assertFalse($builder->testFieldValidationPipeline(6, 0, ['name', 'email']));
    }

    /**
     * Test edge cases that could cause the original error
     */
    public function testEdgeCasesThatCausedOriginalError(): void
    {
        $builder = new class extends Builder {
            public function testIsValidDQLField(string $field): bool
            {
                return $this->isValidDQLField($field);
            }
        };

        // These are the exact patterns that caused "6 LIKE :search" error
        $problematicValues = [
            '6',      // The original problem case
            '0',      // First column index
            '1',      // Second column index  
            '10',     // Double digit
            '123',    // Multi-digit
        ];

        foreach ($problematicValues as $value) {
            $this->assertFalse(
                $builder->testIsValidDQLField($value),
                "Field '{$value}' should be invalid to prevent DQL syntax errors"
            );
        }
    }
}
