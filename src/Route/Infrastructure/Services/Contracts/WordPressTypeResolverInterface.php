<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services\Contracts;

use Pollora\Route\Domain\Contracts\WordPressTypeResolverInterface as DomainWordPressTypeResolverInterface;

/*
 * The interface moved to the domain layer, where the application layer may
 * depend on it. The former name keeps working.
 */
class_alias(DomainWordPressTypeResolverInterface::class, 'Pollora\\Route\\Infrastructure\\Services\\Contracts\\WordPressTypeResolverInterface');
