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

];
