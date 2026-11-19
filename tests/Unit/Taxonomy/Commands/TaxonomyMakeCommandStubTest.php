<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Pollora\Taxonomy\UI\Console\TaxonomyMakeCommand;
use Symfony\Component\Console\Input\ArrayInput;

function contentTaxonomyCommand(array $options = []): TaxonomyMakeCommand
{
    $command = new TaxonomyMakeCommand(new Filesystem);
    $input = new ArrayInput(['name' => 'ProductCategory', ...$options], $command->getDefinition());
    (new ReflectionProperty(Command::class, 'input'))->setValue($command, $input);

    return $command;
}

function contentGeneratedTaxonomy(TaxonomyMakeCommand $command, string $name = 'App\\Cms\\Taxonomies\\ProductCategory'): string
{
    $stub = file_get_contents((new ReflectionMethod($command, 'getStub'))->invoke($command));

    return (new ReflectionMethod($command, 'replaceClass'))->invoke($command, $stub, $name);
}

describe('TaxonomyMakeCommand generation', function (): void {
    it('takes the name of the class as its only argument', function (): void {
        $arguments = (new ReflectionMethod(TaxonomyMakeCommand::class, 'getArguments'))->invoke(new TaxonomyMakeCommand(new Filesystem));

        expect($arguments)->toHaveCount(1)
            ->and($arguments[0][0])->toBe('name');
    });

    it('offers the object type and the deprecated post type options', function (): void {
        $definition = (new TaxonomyMakeCommand(new Filesystem))->getDefinition();

        expect($definition->hasOption('object-type'))->toBeTrue()
            ->and($definition->hasOption('post-type'))->toBeTrue()
            ->and($definition->getOption('post-type')->getDefault())->toBe('post');
    });

    it('writes the taxonomy with the class, slug and names taken from the class name', function (): void {
        $code = contentGeneratedTaxonomy(contentTaxonomyCommand());

        expect($code)->toContain('class ProductCategory')
            ->and($code)->toContain('Product category taxonomy.')
            ->and($code)->not->toContain('Dummy');
    });

    it('attaches the taxonomy to posts when no object type is given', function (): void {
        expect(contentGeneratedTaxonomy(contentTaxonomyCommand()))->toContain("#[ObjectType(['post'])]");
    });

    it('attaches the taxonomy to the deprecated post type option when it is given alone', function (): void {
        expect(contentGeneratedTaxonomy(contentTaxonomyCommand(['--post-type' => 'book'])))->toContain("#[ObjectType(['book'])]");
    });

    it('lets the object types override the post type', function (): void {
        $code = contentGeneratedTaxonomy(contentTaxonomyCommand(['--post-type' => 'book', '--object-type' => 'movie, show ,page']));

        expect($code)->toContain("#[ObjectType(['movie', 'show', 'page'])]")
            ->and($code)->not->toContain("'book'");
    });

    it('formats a single object type as a one item array', function (): void {
        $command = contentTaxonomyCommand();

        expect((new ReflectionMethod($command, 'formatAsPhpArray'))->invoke($command, ['book']))->toBe("['book']")
            ->and((new ReflectionMethod($command, 'formatAsPhpArray'))->invoke($command, ['book', 'movie']))->toBe("['book', 'movie']");
    });

    it('builds the plural name of a class name ending in y with ies and the others with s', function (): void {
        $command = contentTaxonomyCommand();
        $plural = new ReflectionMethod($command, 'getPluralNameFromClassName');

        expect($plural->invoke($command, 'ProductCategory'))->toBe('Product categories')
            ->and($plural->invoke($command, 'Genre'))->toBe('Genres');
    });
});
