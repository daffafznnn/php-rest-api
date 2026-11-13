<?php

declare(strict_types=1);

require __DIR__ . '/lib/validation.php';
require __DIR__ . '/config/database.php';

bootstrapApi();

const PRODUCT_COLUMNS = 'id, name, price, stock, created_at, updated_at';

/**
 * @return array{id:int,name:string,price:float,stock:int,created_at:string,updated_at:string}
 */
function formatProduct(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'price' => (float) $row['price'],
        'stock' => (int) $row['stock'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

function fetchProduct(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . PRODUCT_COLUMNS . ' FROM products WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();

    return $row === false ? null : formatProduct($row);
}

function listProducts(PDO $pdo): never
{
    $page = parsePositiveInt($_GET['page'] ?? null, 'page', 1);
    $limit = parsePositiveInt($_GET['limit'] ?? null, 'limit', 10, 100);
    $query = normalizeSearchQuery($_GET['q'] ?? null, array_key_exists('q', $_GET));

    $where = $query === null ? '' : " WHERE name LIKE :q ESCAPE '!'";
    $pattern = $query === null ? null : '%' . escapeLikePattern($query) . '%';

    $count = $pdo->prepare("SELECT COUNT(*) FROM products{$where}");
    if ($pattern !== null) {
        $count->bindValue(':q', $pattern);
    }
    $count->execute();
    $total = (int) $count->fetchColumn();

    $items = [];
    // A page whose offset does not fit in an integer is always past the last row.
    if ($page - 1 <= intdiv(PHP_INT_MAX, $limit)) {
        $rows = $pdo->prepare('SELECT ' . PRODUCT_COLUMNS . " FROM products{$where} ORDER BY id DESC LIMIT :limit OFFSET :offset");
        if ($pattern !== null) {
            $rows->bindValue(':q', $pattern);
        }
        $rows->bindValue(':limit', $limit, PDO::PARAM_INT);
        $rows->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
        $rows->execute();
        $items = array_map(formatProduct(...), $rows->fetchAll());
    }

    jsonResponse(200, [
        'success' => true,
        'data' => $items,
        'meta' => [
            'q' => $query,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);
}

function showProduct(PDO $pdo): never
{
    $product = fetchProduct($pdo, parseResourceId($_GET['id'] ?? null))
        ?? throw new ApiException(404, 'Produk tidak ditemukan');

    jsonResponse(200, ['success' => true, 'data' => $product]);
}

function createProduct(PDO $pdo): never
{
    $input = validateProductPayload(readJsonObject());

    $statement = $pdo->prepare('INSERT INTO products (name, price, stock) VALUES (?, ?, ?)');
    $statement->execute([$input['name'], $input['price'], $input['stock']]);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Produk dibuat',
        'data' => fetchProduct($pdo, (int) $pdo->lastInsertId()),
    ]);
}

function updateProduct(PDO $pdo): never
{
    $id = parseResourceId($_GET['id'] ?? null);
    $input = validateProductPayload(readJsonObject());

    $statement = $pdo->prepare('UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?');
    $statement->execute([$input['name'], $input['price'], $input['stock'], $id]);

    // MySQL reports zero affected rows when the new values equal the old ones.
    if ($statement->rowCount() === 0 && fetchProduct($pdo, $id) === null) {
        throw new ApiException(404, 'Produk tidak ditemukan');
    }

    jsonResponse(200, ['success' => true, 'message' => 'Produk diperbarui', 'data' => fetchProduct($pdo, $id)]);
}

function deleteProduct(PDO $pdo): never
{
    $id = parseResourceId($_GET['id'] ?? null);

    $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $statement->execute([$id]);

    if ($statement->rowCount() === 0) {
        throw new ApiException(404, 'Produk tidak ditemukan');
    }

    jsonResponse(200, ['success' => true, 'message' => 'Produk dihapus']);
}

match ($_SERVER['REQUEST_METHOD']) {
    'GET' => array_key_exists('id', $_GET) ? showProduct(database()) : listProducts(database()),
    'POST' => createProduct(database()),
    'PUT' => updateProduct(database()),
    'DELETE' => deleteProduct(database()),
    default => methodNotAllowed(['GET', 'POST', 'PUT', 'DELETE']),
};
