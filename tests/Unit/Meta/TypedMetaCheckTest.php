<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Meta\Application\Services\MetaAuditor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Meta\Infrastructure\Checks\TypedMetaCheck;
use Tests\Unit\Meta\Fixtures\ArrayMetaInventory;
use Tests\Unit\Meta\Fixtures\ArticleExtras;
use Tests\Unit\Meta\Fixtures\BookGenre;
use Tests\Unit\Meta\Fixtures\Event;

if (! class_exists('WP_Post_Type')) {
    eval('class WP_Post_Type { public $show_in_rest = true; }');
}

if (! class_exists('WP_Taxonomy')) {
    eval('class WP_Taxonomy { public $show_in_rest = true; }');
}

beforeEach(function (): void {
    $this->schemas = new MetaSchemaRepository;

    foreach ([Event::class, ArticleExtras::class, BookGenre::class] as $class) {
        $this->schemas->add((new MetaSchemaBuilder)->build($class));
    }

    $this->rows = [];
    $this->check = fn (): TypedMetaCheck => new TypedMetaCheck($this->schemas, new MetaAuditor($this->schemas, new ArrayMetaInventory($this->rows), new MetaValueCaster));

    // post, page, event and the genre taxonomy exist and are in REST, unless a test says otherwise
    $this->missing = [];
    $this->outOfRest = [];
    $object = function (string $name, string $class): ?object {
        if (in_array($name, $this->missing, true)) {
            return null;
        }

        $object = new $class;
        $object->show_in_rest = ! in_array($name, $this->outOfRest, true);

        return $object;
    };

    Functions\when('did_action')->justReturn(1);
    Functions\when('post_type_exists')->justReturn(true);
    Functions\when('get_post_type_object')->alias(fn (string $name): ?object => $object($name, 'WP_Post_Type'));
    Functions\when('get_taxonomy')->alias(fn (string $name): ?object => $object($name, 'WP_Taxonomy'));
});

it('passes when every meta is registered, reachable and readable', function (): void {
    $result = ($this->check)()->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Ok)
        ->and($result->summary)->toContain('declared by 3 class(es)');
});

it('reports a post type that does not exist and a meta kept out of REST', function (): void {
    $this->missing = ['page'];
    $genre = (new MetaSchemaBuilder)->build(BookGenre::class)->subtypes[0];
    $this->outOfRest = [$genre];

    $result = ($this->check)()->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details)->toBe([
            ArticleExtras::class.': the post type "page" does not exist, so its meta are never used',
            BookGenre::class.': meta marked showInRest are not in the REST API, because the taxonomy "'.$genre.'" is not (show_in_rest)',
        ]);
});

it('reports stored values that read as the default', function (): void {
    $this->rows = ['post' => ['event' => [11 => ['capacity' => ['a lot']]]]];

    $result = ($this->check)()->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Warning)
        ->and($result->details[0])->toStartWith(Event::class.'::$capacity (post "event"): 1 stored value(s) read as the default');
});

it('reports a declaration discovery refused, with the other problems', function (): void {
    $this->schemas->fail('App\\Broken', 'the property needs a single type.');
    $this->missing = ['page'];

    $result = ($this->check)()->run(RunContext::Console);

    expect($result->status)->toBe(CheckStatus::Error)
        ->and($result->details)->toBe([
            'App\\Broken: the property needs a single type.',
            ArticleExtras::class.': the post type "page" does not exist, so its meta are never used',
        ]);
});
