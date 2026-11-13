<?php

declare(strict_types=1);

final class AssertionFailure extends RuntimeException
{
}

/** @var array<int, array{name:string,callback:callable}> */
$GLOBALS['__tests'] = [];

function test(string $name, callable $callback): void
{
    $GLOBALS['__tests'][] = ['name' => $name, 'callback' => $callback];
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = sprintf(
            'expected %s, got %s',
            var_export($expected, true),
            var_export($actual, true)
        );
        throw new AssertionFailure($message === '' ? $detail : $message . ': ' . $detail);
    }
}

function assertTrueValue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new AssertionFailure($message === '' ? 'condition is false' : $message);
    }
}

function assertArrayHasKeyValue(string|int $key, array $array, string $message = ''): void
{
    if (!array_key_exists($key, $array)) {
        throw new AssertionFailure($message === '' ? "missing key {$key}" : $message);
    }
}

function assertThrows(string $class, callable $callback, string $message = ''): Throwable
{
    try {
        $callback();
    } catch (Throwable $caught) {
        if (!($caught instanceof $class)) {
            throw new AssertionFailure(
                ($message === '' ? '' : $message . ': ') . "expected {$class}, got " . $caught::class
            );
        }
        return $caught;
    }
    throw new AssertionFailure(($message === '' ? '' : $message . ': ') . "expected {$class}, nothing thrown");
}

function finishTests(): never
{
    $passed = 0;
    $failed = 0;

    foreach ($GLOBALS['__tests'] as $entry) {
        try {
            ($entry['callback'])();
            $passed++;
            echo 'PASS ' . $entry['name'] . PHP_EOL;
        } catch (Throwable $error) {
            $failed++;
            echo 'FAIL ' . $entry['name'] . ' :: ' . $error->getMessage() . PHP_EOL;
        }
    }

    echo sprintf('Total: %d, passed: %d, failed: %d', $passed + $failed, $passed, $failed) . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
