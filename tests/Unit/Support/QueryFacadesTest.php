<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Pollora\Providers\QueryServiceProvider;
use Pollora\Query\CommentQuery as CommentQueryBuilder;
use Pollora\Query\DateQuery;
use Pollora\Query\MetaQuery;
use Pollora\Query\NetworkQuery as NetworkQueryBuilder;
use Pollora\Query\PostQuery as PostQueryBuilder;
use Pollora\Query\SiteQuery as SiteQueryBuilder;
use Pollora\Query\TaxQuery;
use Pollora\Query\TermQuery as TermQueryBuilder;
use Pollora\Query\UserQuery as UserQueryBuilder;
use Pollora\Support\Facades\CommentQuery;
use Pollora\Support\Facades\MetaQuery as MetaQueryFacade;
use Pollora\Support\Facades\NetworkQuery;
use Pollora\Support\Facades\PostQuery;
use Pollora\Support\Facades\SiteQuery;
use Pollora\Support\Facades\TaxQuery as TaxQueryFacade;
use Pollora\Support\Facades\TermQuery;
use Pollora\Support\Facades\UserQuery;

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

it('binds a query for every WordPress query class', function (string $key, string $class): void {
    expect($this->app->make($key))->toBeInstanceOf($class);
})->with([
    'posts' => ['wp.query.post', PostQueryBuilder::class],
    'taxonomies' => ['wp.query.taxonomy', TaxQuery::class],
    'meta' => ['wp.query.meta', MetaQuery::class],
    'dates' => ['wp.query.date', DateQuery::class],
    'terms' => ['wp.query.term', TermQueryBuilder::class],
    'users' => ['wp.query.user', UserQueryBuilder::class],
    'comments' => ['wp.query.comment', CommentQueryBuilder::class],
    'sites' => ['wp.query.site', SiteQueryBuilder::class],
    'networks' => ['wp.query.network', NetworkQueryBuilder::class],
]);

it('builds the arguments of every query through its facade', function (): void {
    expect(PostQuery::select()->postType('page')->getArguments())->toMatchArray(['post_type' => 'page'])
        ->and(TermQuery::select()->taxonomy('category')->rootOnly()->getArguments())->toMatchArray(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false])
        ->and(UserQuery::select()->roleIn(['editor', 'author'])->getArguments())->toMatchArray(['role__in' => ['editor', 'author']])
        ->and(CommentQuery::select()->postId(42)->pending()->getArguments())->toMatchArray(['post_id' => 42, 'status' => 'hold'])
        ->and(SiteQuery::select()->networkId(1)->wherePublic()->getArguments())->toMatchArray(['network_id' => 1, 'public' => 1, 'number' => 0])
        ->and(NetworkQuery::select()->domain('example.com')->getArguments())->toMatchArray(['domain' => 'example.com']);
});

it('uses the same arguments through the facades as through the classes', function (): void {
    expect(TermQuery::find(5)->getArguments())->toBe(TermQueryBuilder::find(5)->getArguments())
        ->and(UserQuery::find([1, 2])->getArguments())->toBe(UserQueryBuilder::find([1, 2])->getArguments());
});

it('starts a fresh builder at each call of the new facades', function (string $facade, string $method, array $arguments, string $key): void {
    $facade::$method(...$arguments);

    expect($facade::getFacadeRoot()->getArguments())->not->toHaveKey($key)
        ->and($facade::getFacadeRoot())->not->toBe($facade::getFacadeRoot());
})->with([
    'terms' => [TermQuery::class, 'taxonomy', ['category'], 'taxonomy'],
    'users' => [UserQuery::class, 'role', ['editor'], 'role'],
    'comments' => [CommentQuery::class, 'postId', [42], 'post_id'],
    'sites' => [SiteQuery::class, 'networkId', [1], 'network_id'],
    'networks' => [NetworkQuery::class, 'domain', ['example.com'], 'domain'],
]);

it('keeps resolving the sub-query and post facades as before', function (): void {
    expect(PostQuery::getFacadeRoot())->toBeInstanceOf(PostQueryBuilder::class)
        ->and(TaxQueryFacade::getFacadeRoot())->toBeInstanceOf(TaxQuery::class)
        ->and(MetaQueryFacade::getFacadeRoot())->toBeInstanceOf(MetaQuery::class);
});
