<?php

declare(strict_types=1);

require_once __DIR__ . '/http.php';

const NAME_MAX_LENGTH = 120;
const PRICE_MAX = 9999999999.99;
const STOCK_MAX = 2147483647;
const DESCRIPTION_MAX_BYTES = 65535;

function validateName(mixed $value, string $label): string
{
    if (!is_string($value)) {
        throw new ApiException(422, "{$label} wajib berupa teks");
    }

    $name = trim($value);
    if ($name === '') {
        throw new ApiException(422, "{$label} wajib diisi");
    }

    if (mb_strlen($name, 'UTF-8') > NAME_MAX_LENGTH) {
        throw new ApiException(422, "{$label} maksimal " . NAME_MAX_LENGTH . ' karakter');
    }

    return $name;
}

function validatePrice(mixed $value): float
{
    $numeric = is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    if (!$numeric) {
        throw new ApiException(422, 'Harga wajib berupa angka');
    }

    $price = (float) $value;
    if (!is_finite($price) || $price < 0 || $price > PRICE_MAX) {
        throw new ApiException(422, 'Harga harus antara 0 dan ' . number_format(PRICE_MAX, 2, '.', ''));
    }

    return $price;
}

function validateStock(mixed $value): int
{
    $stock = match (true) {
        is_int($value) => $value,
        is_string($value) => filter_var($value, FILTER_VALIDATE_INT),
        default => false,
    };

    if ($stock === false || $stock < 0 || $stock > STOCK_MAX) {
        throw new ApiException(422, 'Stok harus berupa bilangan bulat antara 0 dan ' . STOCK_MAX);
    }

    return $stock;
}

/**
 * @return array{name:string,price:float,stock:int}
 */
function validateProductPayload(array $input): array
{
    return [
        'name' => validateName($input['name'] ?? null, 'Nama produk'),
        'price' => validatePrice($input['price'] ?? null),
        'stock' => validateStock($input['stock'] ?? null),
    ];
}

/**
 * @return array{name:string,description:?string}
 */
function validateCategoryPayload(array $input): array
{
    $description = $input['description'] ?? null;

    if ($description !== null) {
        if (!is_string($description)) {
            throw new ApiException(422, 'Deskripsi harus berupa teks');
        }

        $description = trim($description);
        if ($description === '') {
            $description = null;
        } elseif (strlen($description) > DESCRIPTION_MAX_BYTES) {
            throw new ApiException(422, 'Deskripsi terlalu panjang');
        }
    }

    return [
        'name' => validateName($input['name'] ?? null, 'Nama kategori'),
        'description' => $description,
    ];
}
