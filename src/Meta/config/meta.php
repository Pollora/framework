<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Input fields
    |--------------------------------------------------------------------------
    |
    | The UI driver that builds admin fields from the #[Meta] declarations, as
    | registered by its package with Meta::extend() (for instance 'acf'). With
    | null, no field is generated: the meta are still registered, readable and
    | writable through REST and code.
    |
    */
    'ui' => null,
];
