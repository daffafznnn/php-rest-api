<?php

declare(strict_types=1);

const COLLECTION_FILE = REPOSITORY_ROOT . '/collection-api/REST-API-PHP.postman_collection.json';
const EXPECT_PATTERN = '/\(expect (\d{3})\)$/';

/** @return array<string, mixed> */
function loadCollection(): array
{
    assertTrueValue(is_file(COLLECTION_FILE), 'collection file is missing');
    $decoded = json_decode((string) file_get_contents(COLLECTION_FILE), true);
    assertTrueValue(is_array($decoded), 'collection is not valid JSON');
    return $decoded;
}

/** @return list<array<string, mixed>> */
function folderRequests(array $collection, string $folder): array
{
    foreach ($collection['item'] ?? [] as $item) {
        if (($item['name'] ?? null) === $folder) {
            return $item['item'] ?? [];
        }
    }
    return [];
}

test('collection uses the Postman v2.1 schema and a base_url variable', function (): void {
    $collection = loadCollection();
    assertSameValue('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema'] ?? null);
    $variables = array_column($collection['variable'] ?? [], 'value', 'key');
    assertSameValue('http://127.0.0.1:8000/api', $variables['base_url'] ?? null);
});

test('collection has the four agreed folders in order', function (): void {
    $names = array_column(loadCollection()['item'] ?? [], 'name');
    assertSameValue(['Products', 'Categories', 'Success scenarios', 'Failed scenarios'], $names);
});

test('collection holds scenarios S-01 to S-07 and F-01 to F-07 exactly once', function (): void {
    $collection = loadCollection();
    $ids = static fn (string $folder): array => array_map(
        static fn (array $item): string => substr((string) $item['name'], 0, 4),
        folderRequests($collection, $folder)
    );
    assertSameValue(['S-01', 'S-02', 'S-03', 'S-04', 'S-05', 'S-06', 'S-07'], $ids('Success scenarios'));
    assertSameValue(['F-01', 'F-02', 'F-03', 'F-04', 'F-05', 'F-06', 'F-07'], $ids('Failed scenarios'));
});

test('every request names its expected status and targets a known endpoint', function (): void {
    $collection = loadCollection();
    foreach (['Products', 'Categories', 'Success scenarios', 'Failed scenarios'] as $folder) {
        $requests = folderRequests($collection, $folder);
        assertTrueValue(count($requests) > 0, "{$folder} is empty");
        foreach ($requests as $item) {
            $label = "{$folder} / {$item['name']}";
            assertTrueValue(preg_match(EXPECT_PATTERN, (string) $item['name']) === 1, "{$label}: name must end with (expect NNN)");
            assertTrueValue(in_array($item['request']['method'] ?? '', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true), "{$label}: method");
            $raw = (string) ($item['request']['url']['raw'] ?? '');
            assertTrueValue(preg_match('#^\{\{base_url\}\}/(products|categories)\.php(\?.*)?$#', $raw) === 1, "{$label}: url {$raw}");
        }
    }
});

test('request bodies are valid JSON objects and carry a JSON content type', function (): void {
    $collection = loadCollection();
    foreach (['Products', 'Categories', 'Success scenarios', 'Failed scenarios'] as $folder) {
        foreach (folderRequests($collection, $folder) as $item) {
            $request = $item['request'];
            $label = "{$folder} / {$item['name']}";
            if (($request['body']['mode'] ?? null) === null) {
                assertTrueValue(in_array($request['method'], ['GET', 'DELETE'], true), "{$label}: write request needs a body");
                continue;
            }
            assertSameValue('raw', $request['body']['mode'], "{$label}: body mode");
            assertTrueValue(json_decode($request['body']['raw'], false) instanceof stdClass, "{$label}: body must be a JSON object");
            $headers = array_column($request['header'] ?? [], 'value', 'key');
            assertSameValue('application/json', $headers['Content-Type'] ?? null, "{$label}: Content-Type");
        }
    }
});

test('collection stores no credentials or tokens', function (): void {
    assertTrueValue(is_file(COLLECTION_FILE), 'collection file is missing');
    $content = strtolower((string) file_get_contents(COLLECTION_FILE));
    foreach (['password', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'bearer'] as $word) {
        assertTrueValue(!str_contains($content, $word), "found '{$word}' in the collection");
    }
});
