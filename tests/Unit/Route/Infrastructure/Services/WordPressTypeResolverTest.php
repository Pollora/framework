<?php

declare(strict_types=1);

namespace Tests\Unit\Route\Infrastructure\Services;

use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Tests\TestCase;

/**
 * @covers \Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver
 */
class WordPressTypeResolverTest extends TestCase
{
    private WordPressTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new WordPressTypeResolver;
    }

    public function test_returns_null_for_unsupported_types(): void
    {
        $result = $this->resolver->resolve('UnsupportedType');
        $this->assertNull($result);
    }

    public function test_resolver_has_correct_interface(): void
    {
        // Test that the resolver implements the correct interface
        $this->assertInstanceOf(
            \Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface::class,
            $this->resolver
        );
    }
}
