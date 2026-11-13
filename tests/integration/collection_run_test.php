<?php

declare(strict_types=1);

require_once __DIR__ . '/api_test.php';
require_once REPOSITORY_ROOT . '/tests/unit/collection_contract_test.php';

/**
 * Sends each request of one collection folder to the live API and compares the status with "(expect NNN)".
 *
 * @return list<array<string, mixed>>
 */
function runCollectionFolder(string $folder): array
{
    resetFixture();
    $results = [];

    foreach (folderRequests(loadCollection(), $folder) as $item) {
        $request = $item['request'];
        preg_match(EXPECT_PATTERN, (string) $item['name'], $expected);
        $path = substr((string) $request['url']['raw'], strlen('{{base_url}}'));
        $body = isset($request['body']['raw']) ? (string) $request['body']['raw'] : null;
        $response = request($request['method'], $path, null, $body);

        $results[] = [
            'name' => $item['name'],
            'method' => $request['method'],
            'path' => $path,
            'expected_status' => (int) $expected[1],
            'actual_status' => $response['status'],
            'allow' => $response['headers']['allow'] ?? null,
            'body' => $response['body'],
        ];
    }

    return $results;
}

function recordCollectionResults(string $folder, array $results): void
{
    $path = getenv('COLLECTION_RESULTS_PATH');
    if ($path === false || $path === '') {
        return;
    }
    $existing = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    $existing[$folder] = $results;
    file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}

foreach (['Products', 'Categories', 'Success scenarios', 'Failed scenarios'] as $folder) {
    test("collection run: {$folder} returns the expected statuses", function () use ($folder): void {
        $results = runCollectionFolder($folder);
        recordCollectionResults($folder, $results);
        foreach ($results as $result) {
            assertSameValue($result['expected_status'], $result['actual_status'], $result['name']);
        }
    });
}
