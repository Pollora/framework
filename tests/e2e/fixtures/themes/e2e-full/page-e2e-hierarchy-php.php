<?php

declare(strict_types=1);

// A classic PHP template with the same name as resources/views/page-e2e-hierarchy-php.blade.php:
// the Blade view must win. If this renders, the hierarchy picked PHP over Blade.
echo '<!doctype html><html><head></head><body><main data-e2e-view="php:page-e2e-hierarchy-php">php</main></body></html>';
