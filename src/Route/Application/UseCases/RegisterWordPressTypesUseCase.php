<?php

declare(strict_types=1);

namespace Pollora\Route\Application\UseCases;

use Illuminate\Contracts\Container\Container;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

/**
 * Use case for registering WordPress types in the DI container.
 *
 * Binds WP_Post, WP_Term, WP_User, WP_Query, and WP as resolvable types,
 * enabling type-hinted injection in controller methods and closures.
 */
class RegisterWordPressTypesUseCase
{
    public function __construct(
        private readonly WordPressTypeResolverInterface $typeResolver,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Execute the use case to register WordPress types in the container.
     */
    public function execute(Container $container): void
    {
        $typesToRegister = [
            'WP_Post' => $this->typeResolver->resolvePost(...),
            'WP_Term' => $this->typeResolver->resolveTerm(...),
            'WP_User' => $this->typeResolver->resolveUser(...),
            'WP_Query' => $this->typeResolver->resolveQuery(...),
            'WP' => $this->typeResolver->resolveWP(...),
        ];

        foreach ($typesToRegister as $type => $resolver) {
            $container->bind($type, $this->createSafeResolver($resolver));
        }
    }

    /**
     * Wrap a resolver callable to safely catch exceptions.
     */
    private function createSafeResolver(callable $resolver): \Closure
    {
        return function () use ($resolver) {
            try {
                return $resolver();
            } catch (\Throwable $throwable) {
                $this->logger?->error('WordPress type resolution failed', [
                    'exception' => $throwable,
                ]);

                return null;
            }
        };
    }
}
