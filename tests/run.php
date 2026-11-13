<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

define('REPOSITORY_ROOT', dirname(__DIR__));

foreach (glob(__DIR__ . '/unit/*_test.php') ?: [] as $file) {
    require_once $file;
}

// Integration tests need a running server, so they only load when a base URL is given.
if (getenv('API_BASE_URL') !== false && getenv('API_BASE_URL') !== '') {
    foreach (glob(__DIR__ . '/integration/*_test.php') ?: [] as $file) {
        require_once $file;
    }
}

finishTests();
