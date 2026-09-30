<?php

declare(strict_types=1);

namespace Pollora\Doctor\Domain\Enums;

/**
 * The verdict of one doctor check.
 *
 * Skipped is a verdict of its own, never folded into Ok: a check that could not
 * run says so, rather than passing a site it never looked at.
 */
enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';
    case Skipped = 'skipped';
}
