<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Enums;

/**
 * How the template hierarchy ended up answering a request.
 */
enum TemplateOutcome: string
{
    /** A Blade view rendered the page. */
    case View = 'view';

    /** A PHP file was included, as a block theme's template-canvas.php is. */
    case File = 'file';

    /** Nothing in the hierarchy could answer, so the 404 page did. */
    case NotFound = 'not_found';
}
