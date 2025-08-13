<?php

use Illuminate\Support\Str;

// Database configuration constants
if (! defined('DEFAULT_HOST')) {
    define('DEFAULT_HOST', '127.0.0.1');
}

// Helper functions for database configuration
if (! function_exists('createDatabaseConnection')) {
    function createDatabaseConnection(string $driver, array $overrides = []): array
    {
        $portMap = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        $charsetMap = [
            'pgsql' => 'utf8',
        ];

        $defaults = [
            'driver' => $driver,
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', DEFAULT_HOST),
            'port' => env('DB_PORT', $portMap[$driver] ?? '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', $charsetMap[$driver] ?? 'utf8mb4'),
            'prefix' => '',
            'prefix_indexes' => true,
        ];

        return array_merge($defaults, $overrides);
    }
}

if (! function_exists('createRedisConnection')) {
    function createRedisConnection(string $database = '0'): array
    {
        return [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', DEFAULT_HOST),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env($database === '0' ? 'REDIS_DB' : 'REDIS_CACHE_DB', $database),
        ];
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => createDatabaseConnection('mysql', [
            'unix_socket' => env('DB_SOCKET', ''),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]),

        'mariadb' => createDatabaseConnection('mariadb', [
            'unix_socket' => env('DB_SOCKET', ''),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]),

        'pgsql' => createDatabaseConnection('pgsql', [
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]),

        'sqlsrv' => createDatabaseConnection('sqlsrv', [
            'host' => env('DB_HOST', 'localhost'),
            'charset' => env('DB_CHARSET', 'utf8'),
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ]),

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => createRedisConnection('0'),

        'cache' => createRedisConnection('1'),

    ],

];
