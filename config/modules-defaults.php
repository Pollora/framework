<?php

declare(strict_types=1);

/*
| Pollora's defaults for keys nwidart/laravel-modules does not have, merged
| into `modules` when the project has not set them. Unlike the activator and
| connector (config/modules.php), they are read after every provider registered.
*/

return [
    // The Plugins › Modules view: switches, and who may use them
    'admin' => [
        'toggle' => env('MODULES_ADMIN_TOGGLE', true),
        'capability' => 'activate_plugins',
    ],
];
