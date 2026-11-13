<?php

declare(strict_types=1);

require __DIR__ . '/lib/validation.php';
require __DIR__ . '/config/database.php';

bootstrapApi();

const CATEGORY_COLUMNS = 'id, name, description, created_at';
const MYSQL_DUPLICATE_ENTRY = 1062;

/**
 * @return array{id:int,name:string,description:?string,created_at:string}
 */
function formatCategory(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'created_at' => $row['created_at'],
    ];
}

function fetchCategory(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . CATEGORY_COLUMNS . ' FROM categories WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();

    return $row === false ? null : formatCategory($row);
}

// The unique index decides, so two simultaneous requests cannot both insert the same name.
function executeCategoryWrite(PDOStatement $statement, array $values): void
{
    try {
        $statement->execute($values);
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? null) === MYSQL_DUPLICATE_ENTRY) {
            throw new ApiException(422, 'Nama kategori sudah digunakan');
        }
        throw $error;
    }
}

function listCategories(PDO $pdo): never
{
    $rows = $pdo->query('SELECT ' . CATEGORY_COLUMNS . ' FROM categories ORDER BY id ASC')->fetchAll();

    jsonResponse(200, ['success' => true, 'data' => array_map(formatCategory(...), $rows)]);
}

function showCategory(PDO $pdo): never
{
    $category = fetchCategory($pdo, parseResourceId($_GET['id'] ?? null))
        ?? throw new ApiException(404, 'Kategori tidak ditemukan');

    jsonResponse(200, ['success' => true, 'data' => $category]);
}

function createCategory(PDO $pdo): never
{
    $input = validateCategoryPayload(readJsonObject());

    $statement = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
    executeCategoryWrite($statement, [$input['name'], $input['description']]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Kategori dibuat',
        'data' => fetchCategory($pdo, (int) $pdo->lastInsertId()),
    ]);
}

function updateCategory(PDO $pdo): never
{
    $id = parseResourceId($_GET['id'] ?? null);
    $input = validateCategoryPayload(readJsonObject());

    $statement = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
    executeCategoryWrite($statement, [$input['name'], $input['description'], $id]);

    // MySQL reports zero affected rows when the new values equal the old ones.
    if ($statement->rowCount() === 0 && fetchCategory($pdo, $id) === null) {
        throw new ApiException(404, 'Kategori tidak ditemukan');
    }

    jsonResponse(200, ['success' => true, 'message' => 'Kategori diperbarui', 'data' => fetchCategory($pdo, $id)]);
}

function deleteCategory(PDO $pdo): never
{
    $id = parseResourceId($_GET['id'] ?? null);

    $statement = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $statement->execute([$id]);

    if ($statement->rowCount() === 0) {
        throw new ApiException(404, 'Kategori tidak ditemukan');
    }

    jsonResponse(200, ['success' => true, 'message' => 'Kategori dihapus']);
}

match ($_SERVER['REQUEST_METHOD']) {
    'GET' => array_key_exists('id', $_GET) ? showCategory(database()) : listCategories(database()),
    'POST' => createCategory(database()),
    'PUT' => updateCategory(database()),
    'DELETE' => deleteCategory(database()),
    default => methodNotAllowed(['GET', 'POST', 'PUT', 'DELETE']),
};
