<?php

declare(strict_types=1);

use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Block\Infrastructure\Providers\BlockServiceProvider;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\BlockCategory\Infrastructure\Registrars\BlockCategoryRegistrar;
use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;

beforeEach(function (): void {
    // Only test register(), not boot() - boot has deep WordPress dependencies
    $this->provider = new BlockServiceProvider($this->app);
    $this->provider->register();
});

describe('BlockServiceProvider', function (): void {
    it('registers BlockRegistrar as singleton', function (): void {
        expect($this->app->bound(BlockRegistrar::class))->toBeTrue();
    });

    it('aliases BlockRegistrar to BlockRegistrarInterface', function (): void {
        expect($this->app->isAlias(BlockRegistrarInterface::class))->toBeTrue();
    });

    it('binds BlockCategoryRegistrarInterface', function (): void {
        expect($this->app->bound(BlockCategoryRegistrarInterface::class))->toBeTrue();
    });

    it('resolves BlockCategoryRegistrarInterface to BlockCategoryRegistrar', function (): void {
        $registrar = $this->app->make(BlockCategoryRegistrarInterface::class);

        expect($registrar)->toBeInstanceOf(BlockCategoryRegistrar::class);
    });

    it('binds PatternDataExtractorInterface', function (): void {
        expect($this->app->bound(PatternDataExtractorInterface::class))->toBeTrue();
    });

    it('binds PatternCategoryRegistrarInterface', function (): void {
        expect($this->app->bound(PatternCategoryRegistrarInterface::class))->toBeTrue();
    });

    it('binds PatternRegistrarInterface', function (): void {
        expect($this->app->bound(PatternRegistrarInterface::class))->toBeTrue();
    });

    it('binds PatternServiceInterface', function (): void {
        expect($this->app->bound(PatternServiceInterface::class))->toBeTrue();
    });
});
