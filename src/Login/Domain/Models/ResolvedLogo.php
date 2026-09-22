<?php

declare(strict_types=1);

namespace Pollora\Login\Domain\Models;

/**
 * A logo the login screen can actually draw: a CSS url() value and a size.
 *
 * The size matters as much as the source. WordPress draws its logo as a
 * background image on an 84×84 anchor with the text pushed off-screen; a wide
 * wordmark dropped in there is squeezed into a square. Carrying the intrinsic
 * ratio through lets the emitted CSS give the anchor the shape the file
 * actually has.
 */
final readonly class ResolvedLogo
{
    /**
     * @param  string  $cssUrl  A complete CSS url(…) value, ready to assign
     * @param  int  $width  Rendered width, in pixels
     * @param  int  $height  Rendered height, in pixels
     */
    public function __construct(
        public string $cssUrl,
        public int $width,
        public int $height,
    ) {}
}
