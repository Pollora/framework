<?php

declare(strict_types=1);

use Pollora\Option\Infrastructure\Repositories\WordPressOptionRepository;
use Pollora\Option\Option;

describe('WordPressOptionRepository', function (): void {
    beforeEach(function (): void {
        $this->repository = new WordPressOptionRepository;
    });

    it('returns option when exists', function (): void {
        Brain\Monkey\Functions\when('get_option')->alias(fn ($key, $default) => $key === 'test_key' ? 'test_value' : $default);

        $result = $this->repository->get('test_key');

        expect($result)->toBeInstanceOf(Option::class);
        expect($result->key)->toBe('test_key');
        expect($result->value)->toBe('test_value');
    });

    it('returns null when not exists', function (): void {
        Brain\Monkey\Functions\when('get_option')->alias(fn ($key, $default) => $key === 'non_existent_key' ? null : $default);

        expect($this->repository->get('non_existent_key'))->toBeNull();
    });

    it('store returns true', function (): void {
        Brain\Monkey\Functions\when('add_option')->justReturn(true);

        expect($this->repository->store(new Option('test_key', 'test_value', true)))->toBeTrue();
    });

    it('update returns true', function (): void {
        Brain\Monkey\Functions\when('update_option')->justReturn(true);

        expect($this->repository->update(new Option('test_key', 'updated_value', false)))->toBeTrue();
    });

    it('delete returns true', function (): void {
        Brain\Monkey\Functions\when('delete_option')->justReturn(true);

        expect($this->repository->delete('test_key'))->toBeTrue();
    });

    it('exists returns true when option has value', function (): void {
        Brain\Monkey\Functions\when('get_option')->alias(fn ($key, $default) => $key === 'existing_key' ? 'some_value' : $default);

        expect($this->repository->exists('existing_key'))->toBeTrue();
    });

    it('exists returns false when option is null', function (): void {
        Brain\Monkey\Functions\when('get_option')->alias(fn ($key, $default) => $key === 'non_existent_key' ? null : $default);

        expect($this->repository->exists('non_existent_key'))->toBeFalse();
    });
});
