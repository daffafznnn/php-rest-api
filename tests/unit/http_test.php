<?php

declare(strict_types=1);

require_once REPOSITORY_ROOT . '/api/lib/http.php';

function assertApiError(int $status, callable $callback, string $label): void
{
    $error = assertThrows(ApiException::class, $callback, $label);
    assertSameValue($status, $error->status, $label . ' status');
}

test('parsePositiveInt uses the default when the value is absent', function (): void {
    assertSameValue(1, parsePositiveInt(null, 'page', 1));
    assertSameValue(10, parsePositiveInt(null, 'limit', 10, 100));
});

test('parsePositiveInt accepts plain digit strings and ints', function (): void {
    assertSameValue(2, parsePositiveInt('2', 'page', 1));
    assertSameValue(100, parsePositiveInt('100', 'limit', 10, 100));
    assertSameValue(7, parsePositiveInt(7, 'page', 1));
});

test('parsePositiveInt rejects every malformed value with 400', function (): void {
    $bad = ['0', '-1', 'abc', '1.5', ' ', '', ' 2', '2 ', "2\n", '1e2', '+3', '007', ['1'], [], true, 1.5, '99999999999999999999'];
    foreach ($bad as $value) {
        assertApiError(400, fn () => parsePositiveInt($value, 'page', 1), 'value ' . var_export($value, true));
    }
});

test('parsePositiveInt enforces the maximum', function (): void {
    assertApiError(400, fn () => parsePositiveInt('101', 'limit', 10, 100), 'limit 101');
});

test('parsePositiveInt without a default requires the value', function (): void {
    assertApiError(400, fn () => parsePositiveInt(null, 'page'), 'missing value');
});

test('parseResourceId returns positive integer ids', function (): void {
    assertSameValue(12, parseResourceId('12'));
});

test('parseResourceId rejects invalid ids with 400', function (): void {
    foreach ([null, '', 'abc', '0', '-4', ['1'], '1.0'] as $value) {
        assertApiError(400, fn () => parseResourceId($value), 'id ' . var_export($value, true));
    }
});

test('normalizeSearchQuery returns null when q was not provided', function (): void {
    assertSameValue(null, normalizeSearchQuery(null, false));
});

test('normalizeSearchQuery trims valid queries and keeps zero', function (): void {
    assertSameValue('kopi', normalizeSearchQuery('  kopi  ', true));
    assertSameValue('0', normalizeSearchQuery('0', true));
});

test('normalizeSearchQuery rejects empty, blank, and array queries with 400', function (): void {
    foreach (['', '   ', ['kopi'], []] as $value) {
        assertApiError(400, fn () => normalizeSearchQuery($value, true), 'q ' . var_export($value, true));
    }
});

test('escapeLikePattern neutralizes wildcard characters', function (): void {
    assertSameValue('100!%', escapeLikePattern('100%'));
    assertSameValue('a!_b', escapeLikePattern('a_b'));
    assertSameValue('wow!!', escapeLikePattern('wow!'));
});

test('readJsonObject returns an associative array for a JSON object', function (): void {
    assertSameValue(['name' => 'Kopi', 'stock' => 3], readJsonObject('{"name":"Kopi","stock":3}'));
});

test('readJsonObject treats an empty object as an empty array', function (): void {
    assertSameValue([], readJsonObject('{}'));
});

test('readJsonObject rejects malformed, empty, and non-object bodies with 400', function (): void {
    foreach (['', '   ', '{bad', 'null', '"text"', '12', '[]', '[1,2]', 'true'] as $raw) {
        assertApiError(400, fn () => readJsonObject($raw), 'body ' . var_export($raw, true));
    }
});

test('encodeJson keeps unicode and slashes readable', function (): void {
    assertSameValue('{"a":"é/x"}', encodeJson(['a' => 'é/x']));
});

test('ApiException carries its HTTP status', function (): void {
    $error = new ApiException(404, 'Tidak ditemukan');
    assertSameValue(404, $error->status);
    assertSameValue('Tidak ditemukan', $error->getMessage());
});
