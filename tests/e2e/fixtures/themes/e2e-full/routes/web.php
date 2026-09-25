<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Routes take priority over the template hierarchy, which only runs when no route matched.
 * The page e2e-hierarchy-routed has a template of its own, and the page
 * e2e-hierarchy-laravel exists in WordPress: neither may render through the hierarchy.
 */

$page = static fn (string $by): string => '<!doctype html><html><head></head><body>'
    ."<main data-e2e-view=\"route:{$by}\">{$by}</main></body></html>";

Route::wp('page', 'e2e-hierarchy-routed', static fn () => response($page('wp')));

Route::get('/e2e-hierarchy-laravel', static fn () => response($page('laravel')));
