<?php

declare(strict_types=1);

const README_FILE = REPOSITORY_ROOT . '/README.md';

function readmeText(): string
{
    assertTrueValue(is_file(README_FILE), 'README.md is missing');
    return (string) file_get_contents(README_FILE);
}

function assertReadmeMentions(array $needles, string $label): void
{
    $text = readmeText();
    foreach ($needles as $needle) {
        assertTrueValue(str_contains($text, $needle), "{$label}: README must mention '{$needle}'");
    }
}

test('README states the runtime and database requirements', function (): void {
    assertReadmeMentions(['PHP 8.5', 'pdo_mysql', 'MySQL', 'php -v'], 'requirements');
});

test('README documents the environment variables without a password value', function (): void {
    assertReadmeMentions(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', '.env.example', 'source .env'], 'environment');
    assertTrueValue(preg_match('/DB_PASSWORD=\S/', readmeText()) === 0, 'README must not show a DB_PASSWORD value');
});

test('README explains the database import and server start', function (): void {
    assertReadmeMentions(['database/db_api.sql', 'php -S 127.0.0.1:8000', 'reset_test_data.sql'], 'setup');
    assertTrueValue(!str_contains(strtoupper(readmeText()), 'DROP DATABASE'), 'README must not tell people to drop a database');
});

test('README lists every endpoint and the query parameters', function (): void {
    assertReadmeMentions(['/products.php', '/categories.php', 'GET', 'POST', 'PUT', 'DELETE', '?q=', 'page', 'limit', '100'], 'endpoints');
});

test('README covers the rules, responses, and status codes', function (): void {
    assertReadmeMentions(['unik', 'description', 'total_pages', '"success"'], 'rules');
    foreach (['200', '201', '400', '404', '405', '422', '500'] as $code) {
        assertTrueValue((bool) preg_match('/\|\s*' . $code . '\s*\|/', readmeText()), "status table must list {$code}");
    }
});

test('README lists all 14 scenarios and the collection import', function (): void {
    $ids = [];
    foreach (['S', 'F'] as $prefix) {
        foreach (range(1, 7) as $number) {
            $ids[] = sprintf('%s-%02d', $prefix, $number);
        }
    }
    assertReadmeMentions($ids, 'scenarios');
    assertReadmeMentions(['collection-api', 'REST-API-PHP.postman_collection.json', 'Bruno', 'Postman', 'tests/run.php'], 'testing');
});

test('README states scope, out of scope, and what is not in the repository', function (): void {
    assertReadmeMentions(['Ruang lingkup', 'Di luar ruang lingkup', 'laporan', 'screenshot'], 'scope');
});

test('README contains no em dash', function (): void {
    assertTrueValue(!str_contains(readmeText(), "\u{2014}"), 'em dash found');
});
