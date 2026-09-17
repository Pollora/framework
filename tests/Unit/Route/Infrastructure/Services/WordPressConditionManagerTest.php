<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;

describe('WordPressConditionManager', function (): void {
    beforeEach(function (): void {
        $this->manager = new WordPressConditionManager(new Container);
    });

    it('loads default conditions', function (): void {
        $conditions = $this->manager->getConditions();

        expect($conditions)->toBeArray();
        expect($conditions)->toHaveKey('home');
        expect($conditions['home'])->toBe('is_home');
        expect($conditions)->toHaveKey('single');
        expect($conditions['single'])->toBe('is_single');
        expect($conditions)->toHaveKey('archive');
        expect($conditions['archive'])->toBe('is_archive');
    });

    it('can resolve known conditions', function (): void {
        expect($this->manager->resolveCondition('home'))->toBe('is_home');
        expect($this->manager->resolveCondition('single'))->toBe('is_single');
        expect($this->manager->resolveCondition('archive'))->toBe('is_archive');
    });

    it('returns original condition for unknown aliases', function (): void {
        expect($this->manager->resolveCondition('unknown_condition'))->toBe('unknown_condition');
    });

    it('can add custom conditions', function (): void {
        $this->manager->addCondition('custom', 'is_custom');

        expect($this->manager->resolveCondition('custom'))->toBe('is_custom');

        $conditions = $this->manager->getConditions();
        expect($conditions)->toHaveKey('custom');
        expect($conditions['custom'])->toBe('is_custom');
    });

    it('resolves WordPress function names as passthrough', function (): void {
        expect($this->manager->resolveCondition('is_home'))->toBe('is_home');
        expect($this->manager->resolveCondition('is_single'))->toBe('is_single');
        expect($this->manager->resolveCondition('is_404'))->toBe('is_404');
    });

    it('resolves multiple aliases for same condition', function (): void {
        // is_tax has aliases ['tax', 'taxonomy']
        expect($this->manager->resolveCondition('tax'))->toBe('is_tax');
        expect($this->manager->resolveCondition('taxonomy'))->toBe('is_tax');
    });

    it('resolves 404 alias correctly', function (): void {
        expect($this->manager->resolveCondition('404'))->toBe('is_404');
    });

    it('addCondition updates reverse index immediately', function (): void {
        $this->manager->addCondition('woo_shop', 'is_shop');

        // Should resolve immediately without reloading
        expect($this->manager->resolveCondition('woo_shop'))->toBe('is_shop');
        // The function name itself should also passthrough
        expect($this->manager->resolveCondition('is_shop'))->toBe('is_shop');
    });
});
