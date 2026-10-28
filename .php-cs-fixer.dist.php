<?php

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src/',
        __DIR__ . '/packages/codeigniter4/src/',
        __DIR__ . '/tests/',
    ])
    ->exclude('build')
    ->append([
        __FILE__,
        __DIR__ . '/rector.php',
    ]);

$overrides = [
    // Match Rector's PHP 8.5 syntax: new expressions can be called directly.
    'new_expression_parentheses' => ['use_parentheses' => false],
];

$options = [
    'finder'    => $finder,
    'cacheFile' => 'build/.php-cs-fixer.cache',
];

return Factory::create(new CodeIgniter4(), $overrides, $options)->forProjects();
