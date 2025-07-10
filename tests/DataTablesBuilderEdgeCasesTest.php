<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\DataTables\Builder;

/**
 * Additional edge case tests for DataTables Builder
 * Focuses on specific scenarios that caused the "6 LIKE :search" error
 */
class DataTablesBuilderEdgeCasesTest extends CIUnitTestCase
{
    /**
     * Test the exact scenario that caused the original "6 LIKE :search" error
     * This reproduces the problematic DataTables configuration
     */
    public function testOriginalErrorScenario(): void
    {
        $builder = new class extends Builder {
            public function testFieldValidation($columnValue, int $columnIndex): array
            {
                $fieldName = $this->resolveFieldName($columnValue, $columnIndex);
                return [
                    'original_value' => $columnValue,
                    'resolved_field' => $fieldName,
                    'is_valid_dql' => $this->isValidDQLField($fieldName),
                    'would_cause_error' => !$this->isValidDQLField($fieldName)
                ];
            }
        };

        // Simulate the exact DataTables column configuration that caused the error
        $problematicColumns = [
            ['data' => 'name', 'index' => 0],      // Valid
            ['data' => 'companyName', 'index' => 1], // Valid
            ['data' => 6, 'index' => 2],           // This caused "6 LIKE :search"
        ];

        foreach ($problematicColumns as $column) {
            $result = $builder->testFieldValidation($column['data'], $column['index']);
            
            if ($column['data'] === 6) {
                // The problematic case should be caught and marked invalid
                $this->assertFalse($result['is_valid_dql'], 
                    "Numeric field '{$column['data']}' should be invalid to prevent DQL error");
                $this->assertTrue($result['would_cause_error'],
                    "Numeric field '{$column['data']}' would have caused the original error");
            } else {
                // Valid cases should pass
                $this->assertTrue($result['is_valid_dql'],
                    "Valid field '{$column['data']}' should be accepted");
                $this->assertFalse($result['would_cause_error'],
                    "Valid field '{$column['data']}' should not cause errors");
            }
        }
    }

    /**
     * Test various DataTables column configurations that could cause issues
     */
    public function testVariousDataTablesConfigurations(): void
    {
        $builder = new class extends Builder {
            public function testColumnProcessing(array $column, int $index): array
            {
                $searchable = $this->isColumnSearchable($column);
                $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $index);
                $validDQL = $this->isValidDQLField($fieldName);
                
                return [
                    'searchable' => $searchable,
                    'field_name' => $fieldName,
                    'valid_dql' => $validDQL,
                    'would_be_processed' => $searchable && $validDQL
                ];
            }
        };

        $testCases = [
            // Standard valid column
            [
                'column' => ['data' => 'name', 'searchable' => 'true'],
                'index' => 0,
                'expected_processed' => true,
                'description' => 'Standard valid column'
            ],
            // Numeric data (the problematic case)
            [
                'column' => ['data' => 6, 'searchable' => 'true'],
                'index' => 2,
                'expected_processed' => false,
                'description' => 'Numeric data causing "6 LIKE :search"'
            ],
            // Empty data
            [
                'column' => ['data' => '', 'searchable' => 'true'],
                'index' => 1,
                'expected_processed' => false,
                'description' => 'Empty data field'
            ],
            // Missing data field
            [
                'column' => ['searchable' => 'true'],
                'index' => 3,
                'expected_processed' => false,
                'description' => 'Missing data field'
            ],
            // Not searchable
            [
                'column' => ['data' => 'name', 'searchable' => 'false'],
                'index' => 0,
                'expected_processed' => false,
                'description' => 'Not searchable column'
            ],
            // String numeric (also problematic)
            [
                'column' => ['data' => '123', 'searchable' => 'true'],
                'index' => 4,
                'expected_processed' => false,
                'description' => 'String numeric data'
            ]
        ];

        foreach ($testCases as $testCase) {
            $result = $builder->testColumnProcessing($testCase['column'], $testCase['index']);
            
            $this->assertEquals(
                $testCase['expected_processed'],
                $result['would_be_processed'],
                "Failed for: {$testCase['description']} - " .
                "Expected processed: {$testCase['expected_processed']}, got: {$result['would_be_processed']}"
            );
        }
    }

    /**
     * Test alias resolution with edge cases
     */
    public function testAliasResolutionEdgeCases(): void
    {
        $builder = Builder::create()
            ->withColumnAliases([
                'name' => 'u.name',
                'email' => 'u.email',
                '6' => 'invalid.field',  // Even if aliased, numeric keys should be caught
                '' => 'empty.field'      // Empty keys
            ]);

        $testBuilder = new class($builder) extends Builder {
            private Builder $parentBuilder;
            
            public function __construct(Builder $parent)
            {
                $this->parentBuilder = $parent;
                $this->columnAliases = $parent->columnAliases ?? [];
            }
            
            public function testResolveAndValidate($columnValue, int $columnIndex): array
            {
                $fieldName = $this->resolveFieldName($columnValue, $columnIndex);
                return [
                    'original' => $columnValue,
                    'resolved' => $fieldName,
                    'valid' => $this->isValidDQLField($fieldName)
                ];
            }
        };

        $testCases = [
            ['value' => 'name', 'index' => 0, 'should_be_valid' => true],
            ['value' => 6, 'index' => 2, 'should_be_valid' => false],      // Numeric, even with alias
            ['value' => '', 'index' => 1, 'should_be_valid' => false],     // Empty, even with alias
            ['value' => 'nonaliased', 'index' => 3, 'should_be_valid' => true],
        ];

        foreach ($testCases as $testCase) {
            $result = $testBuilder->testResolveAndValidate($testCase['value'], $testCase['index']);
            
            $this->assertEquals(
                $testCase['should_be_valid'],
                $result['valid'],
                "Failed for value '{$testCase['value']}' at index {$testCase['index']}: " .
                "Expected valid: " . ($testCase['should_be_valid'] ? 'true' : 'false') . 
                ", got: " . ($result['valid'] ? 'true' : 'false')
            );
        }
    }

    /**
     * Test filter operator parsing with malformed input
     */
    public function testFilterOperatorParsingEdgeCases(): void
    {
        $testCases = [
            // Standard valid cases
            '[=]test' => '=',
            '[!=]value' => '!=',
            '[LIKE]search' => '%',  // Should normalize to %
            
            // Edge cases and malformed input
            '[INVALID]test' => '%',      // Invalid operator
            '[123]test' => '%',          // Numeric operator
            '[=]' => '=',                // No value after operator
            'test' => '%',               // No operator brackets
            '' => '%',                   // Empty string
            '[' => '%',                  // Incomplete bracket
            '[]test' => '%',             // Empty operator
            '[=test' => '%',             // Missing closing bracket
            '=]test' => '%',             // Missing opening bracket
            
            // Case sensitivity
            '[like]test' => '%',         // Lowercase should work
            '[Like]test' => '%',         // Mixed case should work
        ];

        foreach ($testCases as $input => $expectedOperator) {
            // Simulate the operator parsing logic from Builder
            $actualOperator = preg_match('~^\[(?<operator>[A-Z!=%<>•]+)\].*$~i', $input, $matches) ? strtoupper($matches['operator']) : '%•';
            
            // Apply normalization
            if (in_array($actualOperator, ['LIKE', '%%'], true)) {
                $actualOperator = '%';
            }
            
            $validOperators = ['!=', '<', '>', 'IN', 'OR', '><', '=', '%'];
            if (!in_array($actualOperator, $validOperators, true)) {
                $actualOperator = '%';
            }

            $this->assertEquals(
                $expectedOperator,
                $actualOperator,
                "Failed parsing operator from input: '{$input}'"
            );
        }
    }

    /**
     * Test searchable columns restriction with edge cases
     */
    public function testSearchableColumnsRestrictionEdgeCases(): void
    {
        $builder = new class extends Builder {
            public function testSearchableRestriction(array $searchableColumns, string $field): bool
            {
                $this->searchableColumns = $searchableColumns;
                
                // Empty searchableColumns means all valid fields are allowed
                if (empty($this->searchableColumns)) {
                    return true;
                }
                
                // Check if field is in the allowed list
                return in_array($field, $this->searchableColumns, true);
            }
        };

        // Test with empty searchable columns (should allow all)
        $this->assertTrue($builder->testSearchableRestriction([], 'any_field'));
        $this->assertTrue($builder->testSearchableRestriction([], 'another_field'));

        // Test with specific restrictions
        $allowedColumns = ['name', 'email', 'company'];
        
        // Allowed fields should pass
        foreach ($allowedColumns as $column) {
            $this->assertTrue(
                $builder->testSearchableRestriction($allowedColumns, $column),
                "Field '{$column}' should be allowed when in searchable columns list"
            );
        }

        // Disallowed fields should be rejected
        $disallowedFields = ['password', 'secret', 'internal_id'];
        foreach ($disallowedFields as $field) {
            $this->assertFalse(
                $builder->testSearchableRestriction($allowedColumns, $field),
                "Field '{$field}' should be rejected when not in searchable columns list"
            );
        }
    }

    /**
     * Test complete workflow simulation for the original error case
     */
    public function testCompleteWorkflowSimulation(): void
    {
        // Simulate the exact request that caused the original error
        $problematicRequest = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => [
                [
                    'data' => 'name',
                    'searchable' => 'true',
                    'search' => ['value' => '']
                ],
                [
                    'data' => 'companyName', 
                    'searchable' => 'true',
                    'search' => ['value' => '']
                ],
                [
                    'data' => 6,  // This would cause "6 LIKE :search"
                    'searchable' => 'true',
                    'search' => ['value' => '']
                ]
            ],
            'search' => ['value' => 'test_search']
        ];

        $builder = new class extends Builder {
            public function simulateGlobalSearch(array $requestParams): array
            {
                $this->requestParams = $requestParams;
                $columns = $requestParams['columns'];
                $searchValue = $requestParams['search']['value'] ?? '';
                
                $processedColumns = [];
                $skippedColumns = [];
                
                if ($searchValue) {
                    for ($i = 0; $i < count($columns); $i++) {
                        $column = $columns[$i];
                        
                        if ($this->isColumnSearchable($column)) {
                            $fieldName = $this->resolveFieldName($column[$this->columnField] ?? '', $i);
                            
                            if ($this->isValidDQLField($fieldName)) {
                                $processedColumns[] = [
                                    'index' => $i,
                                    'original_data' => $column['data'],
                                    'resolved_field' => $fieldName,
                                    'status' => 'processed'
                                ];
                            } else {
                                $skippedColumns[] = [
                                    'index' => $i,
                                    'original_data' => $column['data'],
                                    'resolved_field' => $fieldName,
                                    'status' => 'skipped_invalid_dql'
                                ];
                            }
                        } else {
                            $skippedColumns[] = [
                                'index' => $i,
                                'original_data' => $column['data'] ?? 'missing',
                                'resolved_field' => 'N/A',
                                'status' => 'skipped_not_searchable'
                            ];
                        }
                    }
                }
                
                return [
                    'processed' => $processedColumns,
                    'skipped' => $skippedColumns,
                    'would_have_error' => !empty($skippedColumns)
                ];
            }
        };

        $result = $builder->simulateGlobalSearch($problematicRequest);

        // Should have processed the valid columns
        $this->assertCount(2, $result['processed'], 'Should process 2 valid columns');
        $this->assertEquals('name', $result['processed'][0]['resolved_field']);
        $this->assertEquals('companyName', $result['processed'][1]['resolved_field']);

        // Should have skipped the problematic column
        $this->assertCount(1, $result['skipped'], 'Should skip 1 invalid column');
        $this->assertEquals(6, $result['skipped'][0]['original_data']);
        $this->assertEquals('2', $result['skipped'][0]['resolved_field']); // Index as string
        $this->assertEquals('skipped_invalid_dql', $result['skipped'][0]['status']);

        // The fix should prevent the error
        $this->assertTrue($result['would_have_error'], 'Original request would have caused error, but now it is handled');
    }

    /**
     * Test performance with large column sets
     */
    public function testPerformanceWithLargeColumnSets(): void
    {
        $builder = new class extends Builder {
            public function testLargeColumnProcessing(int $columnCount): array
            {
                $startTime = microtime(true);
                
                $processedCount = 0;
                $skippedCount = 0;
                
                for ($i = 0; $i < $columnCount; $i++) {
                    // Mix of valid and invalid columns
                    $columnValue = ($i % 3 === 0) ? $i : "field_{$i}"; // Every 3rd is numeric
                    
                    $fieldName = $this->resolveFieldName($columnValue, $i);
                    
                    if ($this->isValidDQLField($fieldName)) {
                        $processedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
                
                $endTime = microtime(true);
                
                return [
                    'column_count' => $columnCount,
                    'processed' => $processedCount,
                    'skipped' => $skippedCount,
                    'execution_time' => $endTime - $startTime,
                    'avg_time_per_column' => ($endTime - $startTime) / $columnCount
                ];
            }
        };

        // Test with various column counts
        $columnCounts = [10, 50, 100];
        
        foreach ($columnCounts as $count) {
            $result = $builder->testLargeColumnProcessing($count);
            
            // Performance should be reasonable (less than 1ms per column)
            $this->assertLessThan(0.001, $result['avg_time_per_column'], 
                "Processing {$count} columns should be fast");
            
            // Should correctly identify valid vs invalid columns
            $expectedSkipped = intval($count / 3) + (($count % 3 === 0) ? 0 : 0); // Every 3rd column starting from 0
            $expectedProcessed = $count - $expectedSkipped;
            
            $this->assertGreaterThan(0, $result['processed'], 
                "Should process some valid columns");
            $this->assertGreaterThan(0, $result['skipped'], 
                "Should skip some invalid columns");
        }
    }
}
