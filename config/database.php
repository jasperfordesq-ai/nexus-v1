<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

$mysqlSslVerifyServerCert = getenv('DB_SSL_VERIFY_SERVER_CERT');
$mysqlSslVerifyServerCert = $mysqlSslVerifyServerCert === false
    ? ($_ENV['DB_SSL_VERIFY_SERVER_CERT'] ?? $_SERVER['DB_SSL_VERIFY_SERVER_CERT'] ?? null)
    : $mysqlSslVerifyServerCert;
$mysqlSslVerifyServerCert = $mysqlSslVerifyServerCert === null
    ? null
    : filter_var($mysqlSslVerifyServerCert, FILTER_VALIDATE_BOOLEAN);

$appEnv = getenv('APP_ENV');
$appEnv = $appEnv === false ? ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null) : $appEnv;

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            // DB_DATABASE is checked first so PHPUnit's force="true" override works.
            // DB_NAME is the legacy env var from Docker Compose.
            'database' => env('DB_DATABASE', env('DB_NAME', 'nexus')),
            'username' => env('DB_USER', env('DB_USERNAME', 'nexus')),
            'password' => env('DB_PASS', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
            // Laravel's schema loader shells out to mysql/mysqldump. In the
            // Docker dev/test environment, the MariaDB client can inherit SSL
            // defaults that the local DB service does not support; keep this
            // away from production unless explicitly configured.
            'options' => $mysqlSslVerifyServerCert !== null
                ? [1014 => $mysqlSslVerifyServerCert]
                : (in_array($appEnv, ['local', 'development', 'testing'], true) ? [1014 => false] : []),
        ],
    ],
    'migrations' => [
        'table' => 'laravel_migrations',
        'update_date_on_publish' => true,
    ],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
    ],
];
