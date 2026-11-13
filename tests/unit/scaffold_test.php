<?php

declare(strict_types=1);

$directories = [
    'api/config',
    'api/lib',
    'database',
    'collection-api',
    'tests/unit',
    'tests/integration',
    'tests/fixtures',
];

foreach ($directories as $directory) {
    test("scaffold directory exists: {$directory}", function () use ($directory): void {
        assertTrueValue(is_dir(REPOSITORY_ROOT . '/' . $directory), "missing {$directory}");
    });
}

test('scaffold has .gitignore that excludes env files', function (): void {
    $path = REPOSITORY_ROOT . '/.gitignore';
    assertTrueValue(is_file($path), 'missing .gitignore');
    $content = (string) file_get_contents($path);
    assertTrueValue(str_contains($content, '.env'), '.gitignore must exclude .env');
});
