<?php

declare(strict_types=1);

$schemaPath = REPOSITORY_ROOT . '/database/db_api.sql';
$fixturePath = REPOSITORY_ROOT . '/tests/fixtures/reset_test_data.sql';
$configPath = REPOSITORY_ROOT . '/api/config/database.php';

function readSqlFile(string $path): string
{
    assertTrueValue(is_file($path), "missing {$path}");
    return (string) file_get_contents($path);
}

function extractInsert(string $sql, string $table): string
{
    $found = preg_match('/INSERT INTO ' . $table . '\b[^;]*;/s', $sql, $match);
    assertTrueValue($found === 1, "no INSERT for {$table}");
    return $match[0];
}

function countInsertRows(string $insert): int
{
    return (int) preg_match_all('/^\s*\(/m', $insert);
}

const DB_ENV_NAMES = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

/** @return array<string, string|false> */
function snapshotEnvironment(): array
{
    $snapshot = [];
    foreach (DB_ENV_NAMES as $name) {
        $snapshot[$name] = getenv($name);
    }
    return $snapshot;
}

// Later integration tests in the same process need the real DB_* values back.
function restoreEnvironment(array $snapshot): void
{
    foreach ($snapshot as $name => $value) {
        $value === false ? putenv($name) : putenv("{$name}={$value}");
    }
}

test('schema creates db_api with utf8mb4', function () use ($schemaPath): void {
    $sql = readSqlFile($schemaPath);
    assertTrueValue(str_contains($sql, 'CREATE DATABASE db_api'), 'CREATE DATABASE db_api missing');
    assertTrueValue(str_contains($sql, 'utf8mb4'), 'utf8mb4 missing');
    assertTrueValue(!str_contains(strtoupper($sql), 'DROP DATABASE'), 'DROP DATABASE is forbidden');
});

test('products table has the six fields from the course material', function () use ($schemaPath): void {
    $sql = readSqlFile($schemaPath);
    $found = preg_match('/CREATE TABLE products \((.*?)\);/s', $sql, $match);
    assertTrueValue($found === 1, 'products table missing');
    foreach (['id', 'name', 'price', 'stock', 'created_at', 'updated_at'] as $column) {
        assertTrueValue((bool) preg_match('/^\s*' . $column . '\s/m', $match[1]), "products.{$column} missing");
    }
});

test('categories table has only the four fields from the assignment', function () use ($schemaPath): void {
    $sql = readSqlFile($schemaPath);
    $found = preg_match('/CREATE TABLE categories \((.*?)\);/s', $sql, $match);
    assertTrueValue($found === 1, 'categories table missing');
    $columns = [];
    foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
        if (preg_match('/^\s*([a-z_]+)\s+(INT|VARCHAR|TEXT|TIMESTAMP)\b/i', $line, $column)) {
            $columns[] = $column[1];
        }
    }
    assertSameValue(['id', 'name', 'description', 'created_at'], $columns);
});

test('categories.name has a unique index', function () use ($schemaPath): void {
    $sql = readSqlFile($schemaPath);
    assertTrueValue((bool) preg_match('/UNIQUE KEY\s+\w+\s*\(name\)/i', $sql), 'unique index on categories.name missing');
});

test('seed data supports pagination and search tests', function () use ($schemaPath): void {
    $sql = readSqlFile($schemaPath);
    $products = extractInsert($sql, 'products');
    assertTrueValue(countInsertRows($products) >= 12, 'need at least 12 seeded products');
    assertTrueValue(substr_count($products, 'Kopi') >= 3, 'need at least 3 products named Kopi');
    assertTrueValue(countInsertRows(extractInsert($sql, 'categories')) >= 3, 'need at least 3 seeded categories');
});

test('fixture reseeds the same rows as the schema file and never drops a database', function () use ($schemaPath, $fixturePath): void {
    $schema = readSqlFile($schemaPath);
    $fixture = readSqlFile($fixturePath);
    assertTrueValue(!str_contains(strtoupper($fixture), 'DROP DATABASE'), 'DROP DATABASE is forbidden');
    assertTrueValue(!str_contains(strtoupper($fixture), 'CREATE DATABASE'), 'fixture must not create databases');
    assertSameValue(extractInsert($schema, 'products'), extractInsert($fixture, 'products'), 'product seed drifted');
    assertSameValue(extractInsert($schema, 'categories'), extractInsert($fixture, 'categories'), 'category seed drifted');
});

test('database config falls back to local defaults', function () use ($configPath): void {
    require_once $configPath;
    $snapshot = snapshotEnvironment();
    try {
        foreach (DB_ENV_NAMES as $name) {
            putenv($name);
        }
        assertSameValue(
            ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'db_api', 'user' => 'root', 'password' => ''],
            databaseConfigFromEnvironment()
        );
    } finally {
        restoreEnvironment($snapshot);
    }
});

test('database config reads environment overrides', function () use ($configPath): void {
    require_once $configPath;
    $snapshot = snapshotEnvironment();
    try {
        putenv('DB_HOST=localhost');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=other_db');
        putenv('DB_USER=tester');
        putenv('DB_PASSWORD=secret-from-env');
        $config = databaseConfigFromEnvironment();
    } finally {
        restoreEnvironment($snapshot);
    }
    assertSameValue(['host' => 'localhost', 'port' => 3307, 'name' => 'other_db', 'user' => 'tester', 'password' => 'secret-from-env'], $config);
});

test('database config rejects a non-numeric port', function () use ($configPath): void {
    require_once $configPath;
    $snapshot = snapshotEnvironment();
    try {
        putenv('DB_PORT=abc');
        assertThrows(InvalidArgumentException::class, fn () => databaseConfigFromEnvironment());
    } finally {
        restoreEnvironment($snapshot);
    }
});

test('database config source holds no hardcoded password', function () use ($configPath): void {
    $source = (string) file_get_contents($configPath);
    assertTrueValue(!preg_match('/password[\'"]?\s*(=>|=)\s*[\'"][^\'"]+[\'"]/i', $source), 'hardcoded password literal found');
});
