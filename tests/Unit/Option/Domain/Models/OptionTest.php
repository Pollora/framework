<?php

declare(strict_types=1);

use Pollora\Option\Domain\Models\Option;

describe('Option', function (): void {
    it('can create option with default autoload', function (): void {
        $option = new Option('test_key', 'test_value');

        expect($option->key)->toBe('test_key');
        expect($option->value)->toBe('test_value');
        expect($option->autoload)->toBeTrue();
    });

    it('can create option with custom autoload', function (): void {
        $option = new Option('test_key', 'test_value', false);

        expect($option->key)->toBe('test_key');
        expect($option->value)->toBe('test_value');
        expect($option->autoload)->toBeFalse();
    });

    it('can create option with different value types', function (): void {
        $stringOption = new Option('string_key', 'test');
        $intOption = new Option('int_key', 42);
        $arrayOption = new Option('array_key', ['foo' => 'bar']);
        $boolOption = new Option('bool_key', true);
        $nullOption = new Option('null_key', null);

        expect($stringOption->value)->toBe('test');
        expect($intOption->value)->toBe(42);
        expect($arrayOption->value)->toEqual(['foo' => 'bar']);
        expect($boolOption->value)->toBeTrue();
        expect($nullOption->value)->toBeNull();
    });

    it('withValue returns new instance', function (): void {
        $original = new Option('test_key', 'original_value');
        $updated = $original->withValue('new_value');

        expect($updated)->not->toBe($original);
        expect($original->value)->toBe('original_value');
        expect($updated->value)->toBe('new_value');
        expect($updated->key)->toBe('test_key');
        expect($updated->autoload)->toBe($original->autoload);
    });

    it('withAutoload returns new instance', function (): void {
        $original = new Option('test_key', 'test_value', true);
        $updated = $original->withAutoload(false);

        expect($updated)->not->toBe($original);
        expect($original->autoload)->toBeTrue();
        expect($updated->autoload)->toBeFalse();
        expect($updated->key)->toBe($original->key);
        expect($updated->value)->toBe($original->value);
    });

    it('supports chaining with methods', function (): void {
        $original = new Option('test_key', 'original_value', true);
        $updated = $original
            ->withValue('new_value')
            ->withAutoload(false);

        expect($updated->key)->toBe('test_key');
        expect($updated->value)->toBe('new_value');
        expect($updated->autoload)->toBeFalse();

        expect($original->value)->toBe('original_value');
        expect($original->autoload)->toBeTrue();
    });
});
