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
});
