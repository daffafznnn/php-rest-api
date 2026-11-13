<?php

declare(strict_types=1);

require_once REPOSITORY_ROOT . '/api/config/database.php';

test('PDO connects to db_api over TCP with utf8mb4', function (): void {
    $pdo = database();
    assertSameValue(1, (int) $pdo->query('SELECT 1')->fetchColumn());
    assertSameValue('db_api', $pdo->query('SELECT DATABASE()')->fetchColumn());
    assertSameValue('utf8mb4', $pdo->query('SELECT @@character_set_connection')->fetchColumn());
});
