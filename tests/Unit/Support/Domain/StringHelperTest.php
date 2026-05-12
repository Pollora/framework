<?php

declare(strict_types=1);

use Pollora\Support\Domain\StringHelper;

describe('StringHelper', function (): void {
    describe('studly', function (): void {
        it('converts kebab-case', function (): void {
            expect(StringHelper::studly('foo-bar-baz'))->toBe('FooBarBaz');
        });

        it('converts snake_case', function (): void {
            expect(StringHelper::studly('foo_bar_baz'))->toBe('FooBarBaz');
        });

        it('converts space-separated', function (): void {
            expect(StringHelper::studly('foo bar baz'))->toBe('FooBarBaz');
        });

        it('converts mixed separators', function (): void {
            expect(StringHelper::studly('foo-bar_baz qux'))->toBe('FooBarBazQux');
        });

        it('handles single word', function (): void {
            expect(StringHelper::studly('hello'))->toBe('Hello');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::studly(''))->toBe('');
        });
    });

    describe('kebab', function (): void {
        it('converts PascalCase', function (): void {
            expect(StringHelper::kebab('FooBarBaz'))->toBe('foo-bar-baz');
        });

        it('converts camelCase', function (): void {
            expect(StringHelper::kebab('fooBarBaz'))->toBe('foo-bar-baz');
        });

        it('handles single word', function (): void {
            expect(StringHelper::kebab('Foo'))->toBe('foo');
        });

        it('handles already kebab-case', function (): void {
            expect(StringHelper::kebab('already-kebab'))->toBe('already-kebab');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::kebab(''))->toBe('');
        });
    });

    describe('snake', function (): void {
        it('converts PascalCase', function (): void {
            expect(StringHelper::snake('FooBarBaz'))->toBe('foo_bar_baz');
        });

        it('converts camelCase', function (): void {
            expect(StringHelper::snake('fooBarBaz'))->toBe('foo_bar_baz');
        });

        it('handles single word', function (): void {
            expect(StringHelper::snake('Foo'))->toBe('foo');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::snake(''))->toBe('');
        });
    });

    describe('headline', function (): void {
        it('converts snake_case', function (): void {
            expect(StringHelper::headline('foo_bar_baz'))->toBe('Foo Bar Baz');
        });

        it('converts kebab-case', function (): void {
            expect(StringHelper::headline('foo-bar-baz'))->toBe('Foo Bar Baz');
        });

        it('converts camelCase', function (): void {
            expect(StringHelper::headline('fooBarBaz'))->toBe('Foo Bar Baz');
        });

        it('handles single word', function (): void {
            expect(StringHelper::headline('hello'))->toBe('Hello');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::headline(''))->toBe('');
        });
    });

    describe('singular', function (): void {
        it('singularizes regular -s plurals', function (): void {
            expect(StringHelper::singular('Posts'))->toBe('Post');
            expect(StringHelper::singular('Users'))->toBe('User');
        });

        it('singularizes -ies to -y', function (): void {
            expect(StringHelper::singular('Categories'))->toBe('Category');
            expect(StringHelper::singular('Taxonomies'))->toBe('Taxonomy');
        });

        it('singularizes -es endings', function (): void {
            expect(StringHelper::singular('Addresses'))->toBe('Address');
            expect(StringHelper::singular('Boxes'))->toBe('Box');
            expect(StringHelper::singular('Matches'))->toBe('Match');
        });

        it('handles irregular plurals', function (): void {
            expect(StringHelper::singular('People'))->toBe('Person');
            expect(StringHelper::singular('Children'))->toBe('Child');
            expect(StringHelper::singular('Men'))->toBe('Man');
            expect(StringHelper::singular('Women'))->toBe('Woman');
        });

        it('handles uncountable words', function (): void {
            expect(StringHelper::singular('media'))->toBe('media');
            expect(StringHelper::singular('news'))->toBe('news');
            expect(StringHelper::singular('series'))->toBe('series');
        });

        it('handles already singular words', function (): void {
            expect(StringHelper::singular('Post'))->toBe('Post');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::singular(''))->toBe('');
        });

        it('preserves case of irregular words', function (): void {
            expect(StringHelper::singular('People'))->toBe('Person');
            expect(StringHelper::singular('people'))->toBe('person');
        });
    });

    describe('plural', function (): void {
        it('pluralizes regular words', function (): void {
            expect(StringHelper::plural('Post'))->toBe('Posts');
            expect(StringHelper::plural('User'))->toBe('Users');
        });

        it('pluralizes -y to -ies', function (): void {
            expect(StringHelper::plural('Category'))->toBe('Categories');
            expect(StringHelper::plural('Taxonomy'))->toBe('Taxonomies');
        });

        it('pluralizes -s/-x/-ch/-sh with -es', function (): void {
            expect(StringHelper::plural('Box'))->toBe('Boxes');
            expect(StringHelper::plural('Match'))->toBe('Matches');
            expect(StringHelper::plural('Bush'))->toBe('Bushes');
        });

        it('handles irregular plurals', function (): void {
            expect(StringHelper::plural('Person'))->toBe('People');
            expect(StringHelper::plural('Child'))->toBe('Children');
            expect(StringHelper::plural('Man'))->toBe('Men');
            expect(StringHelper::plural('Woman'))->toBe('Women');
        });

        it('handles uncountable words', function (): void {
            expect(StringHelper::plural('media'))->toBe('media');
            expect(StringHelper::plural('news'))->toBe('news');
        });

        it('handles empty string', function (): void {
            expect(StringHelper::plural(''))->toBe('');
        });

        it('preserves case of irregular words', function (): void {
            expect(StringHelper::plural('Person'))->toBe('People');
            expect(StringHelper::plural('person'))->toBe('people');
        });
    });
});
