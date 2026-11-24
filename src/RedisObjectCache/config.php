<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pollora Object Cache: Laravel side
|--------------------------------------------------------------------------
|
| Merged under `pollora.object-cache` with the defaults of the pollora/object-cache
| package (redis, prefix, serializer, prefetch...). Only what Laravel adds is here.
| The Redis server is the connection of `config/database.php` that `connection` names;
| `redis.database` below overrides its database so the cache has one of its own.
|
*/
return [
    // The connection of `database.redis` the cache connects to.
    'connection' => 'default',

    'laravel' => [
        // The object cache group `Cache::store('wordpress')` writes to.
        'group' => 'laravel',

        // `php artisan cache:clear` also empties this site's object cache.
        'flush_with_cache_clear' => true,
    ],
];
