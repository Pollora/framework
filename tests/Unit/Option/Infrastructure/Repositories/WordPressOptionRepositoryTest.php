<?php

declare(strict_types=1);

use Pollora\Option\Domain\Models\Option;
use Pollora\Option\Infrastructure\Repositories\WordPressOptionRepository;

require_once dirname(__DIR__, 3).'/helpers.php';

describe('WordPressOptionRepository', function (): void {
    beforeEach(function (): void {
        setupWordPressMocks();
        $this->repository = new WordPressOptionRepository;
    });

    it('returns option when exists', function (): void {
        setWordPressFunction('get_option', fn ($key, $default) => $key === 'test_key' ? 'test_value' : $default);

        $result = $this->repository->get('test_key');

        expect($result)->toBeInstanceOf(Option::class);
        expect($result->key)->toBe('test_key');
        expect($result->value)->toBe('test_value');
    });

    it('returns null when not exists', function (): void {
        setWordPressFunction('get_option', fn ($key, $default) => $key === 'non_existent_key' ? null : $default);

        expect($this->repository->get('non_existent_key'))->toBeNull();
    });

    it('store returns true', function (): void {
        setWordPressFunction('add_option', fn (): true => true);

        expect($this->repository->store(new Option('test_key', 'test_value', true)))->toBeTrue();
    });

    it('update returns true', function (): void {
        setWordPressFunction('update_option', fn (): true => true);

        expect($this->repository->update(new Option('test_key', 'updated_value', false)))->toBeTrue();
    });

    it('delete returns true', function (): void {
        setWordPressFunction('delete_option', fn (): true => true);

        expect($this->repository->delete('test_key'))->toBeTrue();
    });

    it('exists returns true when option has value', function (): void {
        setWordPressFunction('get_option', fn ($key, $default) => $key === 'existing_key' ? 'some_value' : $default);

        expect($this->repository->exists('existing_key'))->toBeTrue();
    });

    it('exists returns false when option is null', function (): void {
        setWordPressFunction('get_option', fn ($key, $default) => $key === 'non_existent_key' ? null : $default);

        expect($this->repository->exists('non_existent_key'))->toBeFalse();
    });
});
