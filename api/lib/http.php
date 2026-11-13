<?php

declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function encodeJson(array $body): string
{
    return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function jsonResponse(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo encodeJson($body);
    exit;
}

function respondWithError(Throwable $error): never
{
    if ($error instanceof ApiException) {
        jsonResponse($error->status, ['success' => false, 'message' => $error->getMessage()]);
    }

    // The message can carry connection details, so only the origin is logged.
    error_log(sprintf('Unhandled %s at %s:%d', $error::class, $error->getFile(), $error->getLine()));
    jsonResponse(500, ['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}

function bootstrapApi(): void
{
    ini_set('display_errors', '0');
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    set_exception_handler(respondWithError(...));
}

function methodNotAllowed(array $allowedMethods): never
{
    header('Allow: ' . implode(', ', $allowedMethods));
    jsonResponse(405, ['success' => false, 'message' => 'HTTP method tidak didukung']);
}

function readJsonObject(?string $raw = null): array
{
    $raw ??= (string) file_get_contents('php://input');
    if (trim($raw) === '') {
        throw new ApiException(400, 'Body JSON wajib diisi');
    }

    try {
        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException(400, 'Body bukan JSON yang valid');
    }

    // Decoding as objects keeps {} distinguishable from [].
    if (!($decoded instanceof stdClass)) {
        throw new ApiException(400, 'Body harus berupa objek JSON');
    }

    return (array) $decoded;
}

function parsePositiveInt(mixed $value, string $field, ?int $default = null, ?int $max = null): int
{
    if ($value === null) {
        if ($default === null) {
            throw new ApiException(400, "Parameter {$field} wajib diisi");
        }
        return $default;
    }

    if (is_int($value)) {
        $value = (string) $value;
    }

    if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
        throw new ApiException(400, "Parameter {$field} harus berupa bilangan bulat positif");
    }

    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) {
        throw new ApiException(400, "Parameter {$field} terlalu besar");
    }

    if ($max !== null && $number > $max) {
        throw new ApiException(400, "Parameter {$field} maksimal {$max}");
    }

    return $number;
}

function parseResourceId(mixed $value): int
{
    try {
        return parsePositiveInt($value, 'id');
    } catch (ApiException) {
        throw new ApiException(400, 'ID tidak valid');
    }
}

function normalizeSearchQuery(mixed $value, bool $wasProvided): ?string
{
    if (!$wasProvided) {
        return null;
    }

    if (!is_string($value) || trim($value) === '') {
        throw new ApiException(400, 'Parameter q tidak boleh kosong');
    }

    return trim($value);
}

// Pairs with `ESCAPE '!'` in the LIKE clause so user input cannot act as a wildcard.
function escapeLikePattern(string $value): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
}
