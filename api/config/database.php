<?php

declare(strict_types=1);

/**
 * @return array{host:string,port:int,name:string,user:string,password:string}
 */
function databaseConfigFromEnvironment(): array
{
    $read = static function (string $name, string $default): string {
        $value = getenv($name);
        return ($value === false || $value === '') ? $default : $value;
    };

    $port = filter_var($read('DB_PORT', '3306'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new InvalidArgumentException('DB_PORT must be an integer between 1 and 65535');
    }

    $password = getenv('DB_PASSWORD');

    return [
        'host' => $read('DB_HOST', '127.0.0.1'),
        'port' => $port,
        'name' => $read('DB_NAME', 'db_api'),
        'user' => $read('DB_USER', 'root'),
        'password' => $password === false ? '' : $password,
    ];
}

/**
 * @param array{host:string,port:int,name:string,user:string,password:string} $config
 */
function createDatabaseConnection(array $config): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['name']
    );

    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function database(): PDO
{
    static $connection = null;
    return $connection ??= createDatabaseConnection(databaseConfigFromEnvironment());
}
