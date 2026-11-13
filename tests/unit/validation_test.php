<?php

declare(strict_types=1);

require_once REPOSITORY_ROOT . '/api/lib/validation.php';

function assertValidationError(callable $callback, string $label): void
{
    $error = assertThrows(ApiException::class, $callback, $label);
    assertSameValue(422, $error->status, $label . ' status');
}

test('validateCategoryPayload normalizes a valid payload', function (): void {
    assertSameValue(
        ['name' => 'Minuman', 'description' => 'Kopi dan teh'],
        validateCategoryPayload(['name' => '  Minuman ', 'description' => 'Kopi dan teh'])
    );
});

test('validateCategoryPayload makes description optional', function (): void {
    assertSameValue(['name' => 'A', 'description' => null], validateCategoryPayload(['name' => 'A']));
    assertSameValue(['name' => 'A', 'description' => null], validateCategoryPayload(['name' => 'A', 'description' => null]));
    assertSameValue(['name' => 'A', 'description' => null], validateCategoryPayload(['name' => 'A', 'description' => '   ']));
});

test('validateCategoryPayload rejects bad names with 422', function (): void {
    $bad = [[], ['name' => ''], ['name' => '   '], ['name' => 5], ['name' => ['x']], ['name' => str_repeat('a', 121)]];
    foreach ($bad as $input) {
        assertValidationError(fn () => validateCategoryPayload($input), 'category ' . json_encode($input));
    }
});

test('validateCategoryPayload accepts a name of exactly 120 characters', function (): void {
    $name = str_repeat('é', 120);
    assertSameValue($name, validateCategoryPayload(['name' => $name])['name']);
});

test('validateCategoryPayload rejects non-string descriptions with 422', function (): void {
    foreach ([['x'], 5, true, 1.5] as $description) {
        assertValidationError(
            fn () => validateCategoryPayload(['name' => 'A', 'description' => $description]),
            'description ' . var_export($description, true)
        );
    }
});

test('validateProductPayload normalizes a valid payload', function (): void {
    assertSameValue(
        ['name' => 'Kopi', 'price' => 72000.0, 'stock' => 15],
        validateProductPayload(['name' => ' Kopi ', 'price' => 72000, 'stock' => 15])
    );
    assertSameValue(
        ['name' => 'Kopi', 'price' => 12.5, 'stock' => 3],
        validateProductPayload(['name' => 'Kopi', 'price' => '12.5', 'stock' => '3'])
    );
});

test('validateProductPayload accepts zero price and zero stock', function (): void {
    assertSameValue(
        ['name' => 'Gratis', 'price' => 0.0, 'stock' => 0],
        validateProductPayload(['name' => 'Gratis', 'price' => 0, 'stock' => 0])
    );
});

test('validateProductPayload rejects bad names with 422', function (): void {
    foreach (['', '   ', 5, null, str_repeat('a', 121)] as $name) {
        assertValidationError(
            fn () => validateProductPayload(['name' => $name, 'price' => 1, 'stock' => 1]),
            'product name ' . var_export($name, true)
        );
    }
});

test('validateProductPayload rejects bad prices with 422', function (): void {
    foreach ([null, 'abc', -1, '-0.5', true, [], 10000000000.0, 'NAN', 'INF'] as $price) {
        assertValidationError(
            fn () => validateProductPayload(['name' => 'A', 'price' => $price, 'stock' => 1]),
            'price ' . var_export($price, true)
        );
    }
    assertValidationError(fn () => validateProductPayload(['name' => 'A', 'stock' => 1]), 'missing price');
});

test('validateProductPayload rejects bad stock with 422', function (): void {
    foreach ([null, 1.5, 5.0, 'abc', '1.5', -1, '-3', true, [], 2147483648] as $stock) {
        assertValidationError(
            fn () => validateProductPayload(['name' => 'A', 'price' => 1, 'stock' => $stock]),
            'stock ' . var_export($stock, true)
        );
    }
    assertValidationError(fn () => validateProductPayload(['name' => 'A', 'price' => 1]), 'missing stock');
});
