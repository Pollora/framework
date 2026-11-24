<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pollora Object Cache: the Laravel side
|--------------------------------------------------------------------------
|
| Merged under `pollora.object-cache`, over the defaults of the pollora/object-cache
| package (prefix, serializer, groups, prefetch...), which fill what is not here.
| The environment variables are the ones of the first releases of the package.
| The Redis server is the connection of `config/database.php` that `connection` names;
| `redis.database` overrides its database so the cache has one of its own.
|
*/
return [
    // The connection of `database.redis` the cache connects to.
    'connection' => env('OBJECT_CACHE_REDIS_CONNECTION', 'default'),

    'redis' => [
        // Null keeps the database of the connection.
        'database' => env('OBJECT_CACHE_REDIS_DATABASE'),
        'connect_timeout' => (float) env('OBJECT_CACHE_CONNECT_TIMEOUT', 1.0),
        'read_timeout' => (float) env('OBJECT_CACHE_READ_TIMEOUT', 1.0),
        'persistent' => (bool) env('OBJECT_CACHE_PERSISTENT', false),
    ],

    // Null derives the prefix (see the package).
    'prefix' => env('OBJECT_CACHE_PREFIX'),

    'serializer' => env('OBJECT_CACHE_SERIALIZER', 'php'),

    'max_ttl' => (int) env('OBJECT_CACHE_MAX_TTL', 0),

    'prefetch' => [
        'enabled' => (bool) env('OBJECT_CACHE_PREFETCH', false),
        'max_keys' => (int) env('OBJECT_CACHE_PREFETCH_MAX_KEYS', 300),
        'ttl' => (int) env('OBJECT_CACHE_PREFETCH_TTL', 604800),
    ],

    'laravel' => [
        // The object cache group `Cache::store('wordpress')` writes to.
        'group' => env('OBJECT_CACHE_LARAVEL_GROUP', 'laravel'),

        // `php artisan cache:clear` also empties this site's object cache.
        'flush_with_cache_clear' => (bool) env('OBJECT_CACHE_FLUSH_WITH_CACHE_CLEAR', true),
    ],
];
