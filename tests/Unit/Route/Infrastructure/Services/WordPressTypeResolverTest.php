<?php

declare(strict_types=1);

use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;

describe('WordPressTypeResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new WordPressTypeResolver;
    });

    it('returns null for unsupported types', function (): void {
        $result = $this->resolver->resolve('UnsupportedType');

        expect($result)->toBeNull();
    });

    it('implements the correct interface', function (): void {
        expect($this->resolver)->toBeInstanceOf(WordPressTypeResolverInterface::class);
    });
});
