<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Pollora\Providers\QueryServiceProvider;
use Pollora\Query\DateQuery;
use Pollora\Query\MetaQuery;
use Pollora\Query\PostQuery as PostQueryBuilder;
use Pollora\Query\TaxQuery;
use Pollora\Support\Facades\MetaQuery as MetaQueryFacade;
use Pollora\Support\Facades\PostQuery;
use Pollora\Support\Facades\TaxQuery as TaxQueryFacade;

// These tests hold with pollora/query 1.x and 2.x alike: the constraint allows both.

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    (new QueryServiceProvider($this->app))->register();

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->app);
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('binds the post, taxonomy, meta and date queries', function (string $key, string $class): void {
    expect($this->app->make($key))->toBeInstanceOf($class);
})->with([
    'posts' => ['wp.query.post', PostQueryBuilder::class],
    'taxonomies' => ['wp.query.taxonomy', TaxQuery::class],
    'meta' => ['wp.query.meta', MetaQuery::class],
    'dates' => ['wp.query.date', DateQuery::class],
]);

it('resolves the post, taxonomy and meta facades to their query', function (): void {
    expect(PostQuery::getFacadeRoot())->toBeInstanceOf(PostQueryBuilder::class)
        ->and(TaxQueryFacade::getFacadeRoot())->toBeInstanceOf(TaxQuery::class)
        ->and(MetaQueryFacade::getFacadeRoot())->toBeInstanceOf(MetaQuery::class);
});

it('builds the arguments of a post query through its facade', function (): void {
    expect(PostQuery::select()->postType('page')->postParent(5)->getArguments())
        ->toMatchArray(['post_type' => 'page', 'post_parent' => 5]);
});
