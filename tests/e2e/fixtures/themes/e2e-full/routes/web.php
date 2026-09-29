<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
 * Routes take priority over the template hierarchy, which only runs when no route matched.
 * The page e2e-hierarchy-routed has a template of its own, and the page
 * e2e-hierarchy-laravel exists in WordPress: neither may render through the hierarchy.
 */

$page = static fn (string $by): string => '<!doctype html><html><head></head><body class="'.esc_attr(implode(' ', get_body_class())).'">'
    ."<main data-e2e-view=\"route:{$by}\">{$by}</main></body></html>";

Route::wp('page', 'e2e-hierarchy-routed', static fn (): Response => response($page('wp')));

Route::get('/e2e-hierarchy-laravel', static fn (): Response => response($page('laravel')));

// A URL WordPress knows nothing about: its own resolution calls it a 404.
Route::get('/e2e-laravel-only/{tab}', static fn (): Response => response($page('laravel-only')));
