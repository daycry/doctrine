<?php

declare(strict_types=1);

// Build consumer projects that install only published/runtime package contracts.
$root = dirname(__DIR__);
$mode = $argv[1] ?? 'core';
if (! in_array($mode, ['core', 'adapter'], true)) {
    throw new InvalidArgumentException('Expected core or adapter.');
}
$directory = $root . '/build/isolation-' . $mode;
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$package = $mode === 'core' ? 'daycry/doctrine' : 'daycry/doctrine-codeigniter4';
$manifest = [
    'name' => 'daycry/isolation-' . $mode,
    'license' => 'MIT',
    'require' => [$package => '^6.0'],
    'require-dev' => ['phpunit/phpunit' => '^11.5', 'roave/security-advisories' => 'dev-latest'],
    'repositories' => [
        ['type' => 'path', 'url' => str_replace('\\', '/', $root),
            'options' => ['symlink' => true, 'versions' => ['daycry/doctrine' => '6.0.0']]],
        ['type' => 'path', 'url' => str_replace('\\', '/', $root . '/packages/codeigniter4'),
            'options' => ['symlink' => true, 'versions' => ['daycry/doctrine-codeigniter4' => '6.0.0']]],
    ],
    'config' => ['allow-plugins' => false],
];
file_put_contents($directory . '/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo $directory, PHP_EOL;
