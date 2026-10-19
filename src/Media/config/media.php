<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk (config/filesystems.php) that holds the media library:
    | local, S3, R2, Scaleway… Left null, WordPress keeps its own uploads folder
    | (public/content/uploads) and this module does nothing.
    |
    */

    'disk' => env('MEDIA_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Public URL
    |--------------------------------------------------------------------------
    |
    | The URL the media are served from: a media URL is this and the relative
    | path of the file, built and never asked to the disk. Null takes the 'url'
    | of the disk.
    |
    */

    'url' => env('MEDIA_URL'),

    /*
    |--------------------------------------------------------------------------
    | Legacy URLs
    |--------------------------------------------------------------------------
    |
    | Old base URLs of the library, replaced by the public URL in the content
    | and in rendered blocks while a transition lasts.
    |
    */

    'legacy_urls' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache and private media
    |--------------------------------------------------------------------------
    */

    'cache_control' => 'public, max-age=31536000, immutable',

    'private' => ['expiry' => '+6 hours'],

    'async_thumbnails' => false,

    /*
    |--------------------------------------------------------------------------
    | Sizes made on demand
    |--------------------------------------------------------------------------
    |
    | A declared image size that is not eager() is made the first time its URL is
    | asked for, then served as a file. These limits keep that from costing the
    | server too much: sizes made at the same time, and the biggest original that
    | gets a size (a bigger one stays downloadable). A request that waits more than
    | 'wait' seconds for a place is handed a size that already exists.
    |
    */

    'thumbnails' => [
        'max_pixels' => 50_000_000,
        'max_bytes' => 30 * 1024 * 1024,
        'concurrency' => 4,
        'wait' => 10,

        /*
        |----------------------------------------------------------------------
        | Who makes the sizes
        |----------------------------------------------------------------------
        |
        | 'pollora' makes the sizes itself: files of the library, made on demand.
        | 'bunny' and 'imgproxy' leave it to a service: the URL of a size changes
        | shape, no file is made. Define the classes or presets at the service
        | from the declared sizes with `php artisan pollora:media:thumbs:export`.
        |
        */

        'driver' => env('MEDIA_THUMBNAILS_DRIVER', 'pollora'),

        // Bunny Optimizer: media.url must be the pull zone. The pull zone id and
        // the access key are only used to export the image classes.
        'bunny' => [
            'pull_zone' => env('BUNNY_PULL_ZONE'),
            'api_key' => env('BUNNY_API_KEY'),
        ],

        // imgproxy 4.x in presets-only mode. The key and the salt are the
        // hexadecimal IMGPROXY_KEY and IMGPROXY_SALT of the server, the signature
        // size its IMGPROXY_SIGNATURE_SIZE. The source is 'url' (the URL of the
        // library) or 's3' (the object of the bucket of the media disk).
        'imgproxy' => [
            'url' => env('IMGPROXY_URL'),
            'key' => env('IMGPROXY_KEY'),
            'salt' => env('IMGPROXY_SALT'),
            'signature_size' => (int) env('IMGPROXY_SIGNATURE_SIZE', 32),
            'source' => env('IMGPROXY_SOURCE', 'url'),
        ],
    ],

];
