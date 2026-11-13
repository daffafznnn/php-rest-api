<?php

declare(strict_types=1);

require_once REPOSITORY_ROOT . '/api/config/database.php';

/**
 * @return array{status:int,headers:array<string,string>,body:array,raw:string}
 */
function request(string $method, string $path, ?array $json = null, ?string $rawBody = null): array
{
    $body = $rawBody ?? ($json === null ? null : json_encode($json, JSON_THROW_ON_ERROR));
    $headers = $body === null ? [] : ['Content-Type: application/json'];

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);

    $raw = (string) file_get_contents(rtrim((string) getenv('API_BASE_URL'), '/') . $path, false, $context);

    $status = 0;
    $responseHeaders = [];
    foreach (http_get_last_response_headers() ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
        } elseif (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
        }
    }

    $decoded = json_decode($raw, true);

    return ['status' => $status, 'headers' => $responseHeaders, 'body' => is_array($decoded) ? $decoded : [], 'raw' => $raw];
}

function resetFixture(): void
{
    $config = databaseConfigFromEnvironment();
    if ($config['name'] !== 'db_api') {
        throw new RuntimeException('Fixture reset is only allowed on the db_api database');
    }

    $pdo = database();
    $sql = (string) file_get_contents(REPOSITORY_ROOT . '/tests/fixtures/reset_test_data.sql');
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

function assertNumber(float $expected, mixed $actual, string $message = ''): void
{
    // JSON has one number type, so 85000 and 85000.0 are the same value to a client.
    assertTrueValue(is_int($actual) || is_float($actual), ($message === '' ? '' : $message . ': ') . 'price must be a JSON number, got ' . var_export($actual, true));
    assertTrueValue(abs($expected - (float) $actual) < 0.000001, ($message === '' ? '' : $message . ': ') . "expected {$expected}, got {$actual}");
}

function productNames(array $response): array
{
    return array_column($response['body']['data'] ?? [], 'name');
}

test('products: list defaults to page 1, limit 10, newest first', function (): void {
    resetFixture();
    $response = request('GET', '/products.php');
    assertSameValue(200, $response['status']);
    assertSameValue(true, $response['body']['success']);
    assertSameValue(10, count($response['body']['data']));
    assertSameValue('Keju Cheddar', $response['body']['data'][0]['name']);
    assertSameValue(['q' => null, 'page' => 1, 'limit' => 10, 'total' => 14, 'total_pages' => 2], $response['body']['meta']);
});

test('products: responses are JSON without PHP warnings', function (): void {
    resetFixture();
    foreach (['/products.php', '/products.php?id=1', '/products.php?id=abc', '/products.php?id=9999'] as $path) {
        $response = request('GET', $path);
        assertTrueValue(str_starts_with($response['headers']['content-type'] ?? '', 'application/json'), "content type for {$path}");
        assertTrueValue(str_starts_with($response['raw'], '{'), "body for {$path} must be a JSON object");
    }
});

test('products: detail returns typed fields', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?id=1');
    assertSameValue(200, $response['status']);
    $data = $response['body']['data'];
    assertSameValue(1, $data['id']);
    assertSameValue('Kopi Arabika', $data['name']);
    assertNumber(85000, $data['price']);
    assertSameValue(24, $data['stock']);
});

test('products: detail rejects invalid ids with 400', function (): void {
    foreach (['abc', '', '0', '-1', '1.5', '[]=1'] as $value) {
        $path = $value === '[]=1' ? '/products.php?id[]=1' : '/products.php?id=' . $value;
        assertSameValue(400, request('GET', $path)['status'], $path);
    }
});

test('products: detail of a missing product is 404', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?id=9999');
    assertSameValue(404, $response['status']);
    assertSameValue(false, $response['body']['success']);
});

test('products: POST creates a product and returns it with 201', function (): void {
    resetFixture();
    $response = request('POST', '/products.php', ['name' => 'Kopi Excelsa', 'price' => 78000, 'stock' => 9]);
    assertSameValue(201, $response['status']);
    assertSameValue(true, $response['body']['success']);
    $id = $response['body']['data']['id'];
    assertTrueValue(is_int($id), 'id must be an integer');
    $stored = request('GET', '/products.php?id=' . $id)['body']['data'];
    assertSameValue('Kopi Excelsa', $stored['name']);
    assertNumber(78000, $stored['price']);
    assertSameValue(9, $stored['stock']);
});

test('products: POST with invalid input is 422 and creates nothing', function (): void {
    resetFixture();
    foreach ([['name' => '', 'price' => 1, 'stock' => 1], ['name' => 'A', 'price' => -1, 'stock' => 1], ['name' => 'A', 'price' => 1, 'stock' => 1.5]] as $payload) {
        assertSameValue(422, request('POST', '/products.php', $payload)['status'], json_encode($payload));
    }
    assertSameValue(14, request('GET', '/products.php')['body']['meta']['total']);
});

test('products: POST with malformed, empty, or non-object body is 400', function (): void {
    resetFixture();
    foreach (['{bad', '', '[]', '"text"'] as $raw) {
        assertSameValue(400, request('POST', '/products.php', null, $raw)['status'], 'body ' . $raw);
    }
});

test('products: PUT replaces a product with 200', function (): void {
    resetFixture();
    $response = request('PUT', '/products.php?id=1', ['name' => 'Arabika Premium', 'price' => 95000, 'stock' => 20]);
    assertSameValue(200, $response['status']);
    $stored = request('GET', '/products.php?id=1')['body']['data'];
    assertSameValue('Arabika Premium', $stored['name']);
    assertNumber(95000, $stored['price']);
    assertSameValue(20, $stored['stock']);
});

test('products: PUT with identical data is still 200', function (): void {
    resetFixture();
    $response = request('PUT', '/products.php?id=1', ['name' => 'Kopi Arabika', 'price' => 85000, 'stock' => 24]);
    assertSameValue(200, $response['status']);
});

test('products: PUT checks the id, then the body, then existence', function (): void {
    resetFixture();
    $valid = ['name' => 'X', 'price' => 1, 'stock' => 1];
    assertSameValue(400, request('PUT', '/products.php', $valid)['status'], 'missing id');
    assertSameValue(400, request('PUT', '/products.php?id=abc', $valid)['status'], 'bad id');
    assertSameValue(400, request('PUT', '/products.php?id=1', null, '{bad')['status'], 'malformed body');
    assertSameValue(422, request('PUT', '/products.php?id=1', ['name' => '', 'price' => 1, 'stock' => 1])['status'], 'invalid body');
    assertSameValue(404, request('PUT', '/products.php?id=9999', $valid)['status'], 'missing product');
});

test('products: DELETE removes a product', function (): void {
    resetFixture();
    assertSameValue(200, request('DELETE', '/products.php?id=1')['status']);
    assertSameValue(404, request('GET', '/products.php?id=1')['status']);
});

test('products: DELETE rejects bad ids and missing products', function (): void {
    resetFixture();
    assertSameValue(400, request('DELETE', '/products.php')['status'], 'missing id');
    assertSameValue(400, request('DELETE', '/products.php?id=abc')['status'], 'bad id');
    assertSameValue(404, request('DELETE', '/products.php?id=9999')['status'], 'missing product');
});

test('products: unsupported method is 405 with an Allow header', function (): void {
    $response = request('PATCH', '/products.php?id=1', ['name' => 'X']);
    assertSameValue(405, $response['status']);
    assertSameValue('GET, POST, PUT, DELETE', $response['headers']['allow'] ?? '');
});

test('products: search matches part of the name, case-insensitively', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?q=kopi');
    assertSameValue(200, $response['status']);
    assertSameValue(3, $response['body']['meta']['total']);
    assertSameValue('kopi', $response['body']['meta']['q']);
    foreach (productNames($response) as $name) {
        assertTrueValue(str_contains($name, 'Kopi'), "{$name} should contain Kopi");
    }
});

test('products: search trims the query', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?q=%20%20Kopi%20');
    assertSameValue('Kopi', $response['body']['meta']['q']);
    assertSameValue(3, $response['body']['meta']['total']);
});

test('products: a valid search with no match is 200 with empty data', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?q=zzzz');
    assertSameValue(200, $response['status']);
    assertSameValue([], $response['body']['data']);
    assertSameValue(['q' => 'zzzz', 'page' => 1, 'limit' => 10, 'total' => 0, 'total_pages' => 0], $response['body']['meta']);
});

test('products: empty, blank, and array q are 400', function (): void {
    foreach (['/products.php?q=', '/products.php?q=%20%20', '/products.php?q[]=kopi'] as $path) {
        assertSameValue(400, request('GET', $path)['status'], $path);
    }
});

test('products: wildcard characters in q are matched literally', function (): void {
    resetFixture();
    assertSameValue(0, request('GET', '/products.php?q=%25')['body']['meta']['total'], 'percent');
    assertSameValue(0, request('GET', '/products.php?q=_')['body']['meta']['total'], 'underscore');
});

test('products: page and limit slice the list with accurate metadata', function (): void {
    resetFixture();
    $second = request('GET', '/products.php?page=2&limit=5');
    assertSameValue(200, $second['status']);
    assertSameValue(5, count($second['body']['data']));
    assertSameValue(['q' => null, 'page' => 2, 'limit' => 5, 'total' => 14, 'total_pages' => 3], $second['body']['meta']);
    assertSameValue(4, count(request('GET', '/products.php?page=3&limit=5')['body']['data']));
});

test('products: a page past the last one is 200 with empty data', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?page=99');
    assertSameValue(200, $response['status']);
    assertSameValue([], $response['body']['data']);
    assertSameValue(14, $response['body']['meta']['total']);
    assertSameValue(2, $response['body']['meta']['total_pages']);
});

test('products: invalid page or limit is 400', function (): void {
    $paths = [
        '/products.php?page=0', '/products.php?page=-1', '/products.php?page=abc', '/products.php?page=1.5', '/products.php?page[]=1',
        '/products.php?limit=0', '/products.php?limit=101', '/products.php?limit=abc', '/products.php?limit[]=5',
    ];
    foreach ($paths as $path) {
        assertSameValue(400, request('GET', $path)['status'], $path);
    }
});

test('products: limit 100 is accepted', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?limit=100');
    assertSameValue(200, $response['status']);
    assertSameValue(14, count($response['body']['data']));
});

test('products: search and pagination work together', function (): void {
    resetFixture();
    $response = request('GET', '/products.php?q=Kopi&limit=2&page=2');
    assertSameValue(200, $response['status']);
    assertSameValue(1, count($response['body']['data']));
    assertSameValue(['q' => 'Kopi', 'page' => 2, 'limit' => 2, 'total' => 3, 'total_pages' => 2], $response['body']['meta']);
});

test('products: a page number that overflows the offset is 200 with empty data', function (): void {
    resetFixture();
    foreach (['9223372036854775807', '1000000000000'] as $page) {
        $response = request('GET', "/products.php?page={$page}&limit=100");
        assertSameValue(200, $response['status'], "page {$page}");
        assertSameValue([], $response['body']['data'], "page {$page}");
        assertSameValue(14, $response['body']['meta']['total'], "page {$page}");
    }
});

/** @return list<int> HTTP status of each request, fired at the same moment. */
function parallelPost(string $path, array $payload, int $count): array
{
    $url = rtrim((string) getenv('API_BASE_URL'), '/') . $path;
    $running = [];
    for ($i = 0; $i < $count; $i++) {
        $command = ['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', '-X', 'POST', '-H', 'Content-Type: application/json', '-d', json_encode($payload), $url];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $running[] = [$process, $pipes];
    }

    $codes = [];
    foreach ($running as [$process, $pipes]) {
        $codes[] = (int) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
    sort($codes);

    return $codes;
}

test('categories: list returns every category without pagination', function (): void {
    resetFixture();
    $response = request('GET', '/categories.php?page=1&limit=1');
    assertSameValue(200, $response['status']);
    assertSameValue(true, $response['body']['success']);
    assertSameValue(4, count($response['body']['data']));
    assertSameValue('Minuman', $response['body']['data'][0]['name']);
    assertTrueValue(!array_key_exists('meta', $response['body']), 'categories have no pagination meta');
});

test('categories: detail returns the four assignment fields', function (): void {
    resetFixture();
    $data = request('GET', '/categories.php?id=1')['body']['data'];
    assertSameValue(['id', 'name', 'description', 'created_at'], array_keys($data));
    assertSameValue(1, $data['id']);
    assertSameValue('Minuman', $data['name']);
    assertSameValue('Kopi, teh, dan minuman siap seduh', $data['description']);
    assertSameValue(null, request('GET', '/categories.php?id=4')['body']['data']['description']);
});

test('categories: detail rejects invalid ids with 400 and missing ids with 404', function (): void {
    resetFixture();
    foreach (['/categories.php?id=abc', '/categories.php?id=', '/categories.php?id=0', '/categories.php?id=-1', '/categories.php?id[]=1'] as $path) {
        assertSameValue(400, request('GET', $path)['status'], $path);
    }
    $missing = request('GET', '/categories.php?id=9999');
    assertSameValue(404, $missing['status']);
    assertSameValue(false, $missing['body']['success']);
});

test('categories: POST creates a category with 201', function (): void {
    resetFixture();
    $response = request('POST', '/categories.php', ['name' => '  Frozen food ', 'description' => 'Makanan beku']);
    assertSameValue(201, $response['status']);
    $id = $response['body']['data']['id'];
    assertTrueValue(is_int($id), 'id must be an integer');
    $stored = request('GET', '/categories.php?id=' . $id)['body']['data'];
    assertSameValue('Frozen food', $stored['name']);
    assertSameValue('Makanan beku', $stored['description']);
});

test('categories: description is optional and blank becomes null', function (): void {
    resetFixture();
    foreach ([['name' => 'Tanpa deskripsi'], ['name' => 'Deskripsi kosong', 'description' => '   '], ['name' => 'Deskripsi null', 'description' => null]] as $payload) {
        $created = request('POST', '/categories.php', $payload);
        assertSameValue(201, $created['status'], json_encode($payload));
        assertSameValue(null, request('GET', '/categories.php?id=' . $created['body']['data']['id'])['body']['data']['description'], json_encode($payload));
    }
});

test('categories: POST with invalid input is 422 and creates nothing', function (): void {
    resetFixture();
    $payloads = [[], ['name' => ''], ['name' => '   '], ['name' => 5], ['name' => str_repeat('a', 121)], ['name' => 'Valid', 'description' => ['x']]];
    foreach ($payloads as $payload) {
        assertSameValue(422, request('POST', '/categories.php', $payload === [] ? null : $payload, $payload === [] ? '{}' : null)['status'], json_encode($payload));
    }
    assertSameValue(4, count(request('GET', '/categories.php')['body']['data']));
});

test('categories: POST with malformed, empty, or non-object body is 400', function (): void {
    resetFixture();
    foreach (['{bad', '', '[]', 'null'] as $raw) {
        assertSameValue(400, request('POST', '/categories.php', null, $raw)['status'], 'body ' . $raw);
    }
});

test('categories: a duplicate name is 422 without leaking SQL details', function (): void {
    resetFixture();
    foreach (['Minuman', 'minuman', '  Minuman  '] as $name) {
        $response = request('POST', '/categories.php', ['name' => $name]);
        assertSameValue(422, $response['status'], "name '{$name}'");
        assertSameValue(false, $response['body']['success']);
        assertTrueValue(preg_match('/SQLSTATE|Duplicate entry|uq_categories_name|PDOException/i', $response['raw']) === 0, 'response must not leak SQL details');
    }
    assertSameValue(4, count(request('GET', '/categories.php')['body']['data']));
});

test('categories: simultaneous creates of one name give one 201 and the rest 422', function (): void {
    resetFixture();
    $codes = parallelPost('/categories.php', ['name' => 'Balapan nama'], 8);
    assertSameValue([201, 422, 422, 422, 422, 422, 422, 422], $codes);
});

test('categories: PUT replaces a category with 200', function (): void {
    resetFixture();
    $response = request('PUT', '/categories.php?id=2', ['name' => 'Camilan', 'description' => 'Makanan ringan harian']);
    assertSameValue(200, $response['status']);
    $stored = request('GET', '/categories.php?id=2')['body']['data'];
    assertSameValue('Camilan', $stored['name']);
    assertSameValue('Makanan ringan harian', $stored['description']);
});

test('categories: PUT with identical data, or keeping its own name, is 200', function (): void {
    resetFixture();
    assertSameValue(200, request('PUT', '/categories.php?id=1', ['name' => 'Minuman', 'description' => 'Kopi, teh, dan minuman siap seduh'])['status'], 'identical');
    assertSameValue(200, request('PUT', '/categories.php?id=1', ['name' => 'Minuman', 'description' => 'Deskripsi baru'])['status'], 'same name, new description');
});

test('categories: PUT to another category name is 422', function (): void {
    resetFixture();
    $response = request('PUT', '/categories.php?id=2', ['name' => 'Minuman']);
    assertSameValue(422, $response['status']);
    assertSameValue('Makanan ringan', request('GET', '/categories.php?id=2')['body']['data']['name']);
});

test('categories: PUT checks the id, then the body, then existence', function (): void {
    resetFixture();
    $valid = ['name' => 'Baru'];
    assertSameValue(400, request('PUT', '/categories.php', $valid)['status'], 'missing id');
    assertSameValue(400, request('PUT', '/categories.php?id=abc', $valid)['status'], 'bad id');
    assertSameValue(400, request('PUT', '/categories.php?id=1', null, '{bad')['status'], 'malformed body');
    assertSameValue(422, request('PUT', '/categories.php?id=1', ['name' => ''])['status'], 'invalid body');
    assertSameValue(404, request('PUT', '/categories.php?id=9999', $valid)['status'], 'missing category');
});

test('categories: DELETE removes a category', function (): void {
    resetFixture();
    assertSameValue(200, request('DELETE', '/categories.php?id=1')['status']);
    assertSameValue(404, request('GET', '/categories.php?id=1')['status']);
});

test('categories: DELETE rejects bad ids and missing categories', function (): void {
    resetFixture();
    assertSameValue(400, request('DELETE', '/categories.php')['status'], 'missing id');
    assertSameValue(400, request('DELETE', '/categories.php?id=abc')['status'], 'bad id');
    assertSameValue(404, request('DELETE', '/categories.php?id=9999')['status'], 'missing category');
});

test('categories: unsupported method is 405 with an Allow header', function (): void {
    $response = request('PATCH', '/categories.php?id=1', ['name' => 'X']);
    assertSameValue(405, $response['status']);
    assertSameValue('GET, POST, PUT, DELETE', $response['headers']['allow'] ?? '');
});

test('categories: responses are JSON without PHP warnings', function (): void {
    resetFixture();
    foreach (['/categories.php', '/categories.php?id=1', '/categories.php?id=abc', '/categories.php?id=9999'] as $path) {
        $response = request('GET', $path);
        assertTrueValue(str_starts_with($response['headers']['content-type'] ?? '', 'application/json'), "content type for {$path}");
        assertTrueValue(str_starts_with($response['raw'], '{'), "body for {$path} must be a JSON object");
    }
});
