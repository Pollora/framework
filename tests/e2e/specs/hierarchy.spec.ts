import { rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { activateTheme, restoreSite, seed, type Seeded, themeDir } from '../support/hierarchy';
import { renderedTemplate } from '../support/site';

/**
 * The WordPress template hierarchy, resolved to Blade by the framework, read in the
 * marker it writes under WP_DEBUG (`<!-- pollora:template="…" path="…" -->`).
 *
 * Two fixture themes: e2e-full has a template for every case, so each URL must reach
 * the most specific one; e2e-index has index.blade.php alone, so each must fall back to
 * it. Every fixture template also names itself in a data-e2e-view attribute, which is
 * what tells a route's response apart from the hierarchy's: routes write no marker.
 */

type Case = {
    /** Key of the URL in the seed's output */
    url: string;
    /** Marker the page must carry, or null when a route answered and the hierarchy never ran */
    template: string | null;
    view: string;
    status?: number;
};

const fullTheme: Record<string, Case> = {
    'a static front page renders front-page': { url: 'front', template: 'front-page', view: 'front-page' },
    'the posts page renders home': { url: 'blog', template: 'home', view: 'home' },
    'a page with a template for its slug renders page-{slug}': { url: 'about', template: 'page-e2e-hierarchy-about', view: 'page-e2e-hierarchy-about' },
    'a page with a template for its id renders page-{id}': { url: 'byId', template: 'page-{id}', view: 'page-{id}' },
    'a page with a custom template renders it': { url: 'landing', template: 'landing', view: 'templates/landing' },
    'any other page renders page': { url: 'plain', template: 'page', view: 'page' },
    'a post renders single': { url: 'post', template: 'single', view: 'single' },
    'a custom post type with a template renders single-{type}': { url: 'book', template: 'single-e2e_book', view: 'single-e2e_book' },
    'a custom post type without one renders single': { url: 'note', template: 'single', view: 'single' },
    'a post type archive with a template renders archive-{type}': { url: 'bookArchive', template: 'archive-e2e_book', view: 'archive-e2e_book' },
    'a post type archive without one renders archive': { url: 'noteArchive', template: 'archive', view: 'archive' },
    'a term with a template renders taxonomy-{tax}-{term}': { url: 'rock', template: 'taxonomy-e2e_genre-e2e-rock', view: 'taxonomy-e2e_genre-e2e-rock' },
    'any other term renders taxonomy-{tax}': { url: 'jazz', template: 'taxonomy-e2e_genre', view: 'taxonomy-e2e_genre' },
    'a category with a template renders category-{slug}': { url: 'category', template: 'category-e2e-hierarchy-cat', view: 'category-e2e-hierarchy-cat' },
    'a tag renders tag': { url: 'tag', template: 'tag', view: 'tag' },
    'an author with a template renders author-{nicename}': { url: 'author', template: 'author-e2e-hierarchy-author', view: 'author-e2e-hierarchy-author' },
    'a date archive renders date': { url: 'date', template: 'date', view: 'date' },
    'a search renders search': { url: 'search', template: 'search', view: 'search' },
    'an unknown URL renders 404, with a 404 status': { url: 'notFound', template: '404', view: '404', status: 404 },
    'a Blade view wins over a PHP template of the same name': { url: 'php', template: 'page-e2e-hierarchy-php', view: 'page-e2e-hierarchy-php' },
    'a Route::wp() route wins over the page template': { url: 'routed', template: null, view: 'route:wp' },
    'a Laravel route wins over the WordPress page at its URL': { url: 'laravel', template: null, view: 'route:laravel' },
};

// Everything WordPress itself resolves; the post types, the taxonomy and the routes
// belong to e2e-full and do not exist under this theme.
const indexTheme: Record<string, Case> = Object.fromEntries(
    ['front', 'blog', 'about', 'byId', 'landing', 'plain', 'post', 'category', 'tag', 'author', 'date', 'search', 'routed', 'laravel', 'php'].map((url) => [
        url,
        { url, template: 'index', view: 'index' },
    ]),
);

let seeded: Seeded;

test.beforeAll(() => {
    activateTheme('e2e-full');
    seeded = seed();

    const byId = seeded.ids.byId;
    fullTheme['a page with a template for its id renders page-{id}'] = { url: 'byId', template: `page-${byId}`, view: `page-${byId}` };
    writeFileSync(join(themeDir('e2e-full'), 'resources', 'views', `page-${byId}.blade.php`), `@extends('layouts.e2e')\n@section('view', 'page-${byId}')\n`);
});

test.afterAll(() => {
    if (seeded) {
        rmSync(join(themeDir('e2e-full'), 'resources', 'views', `page-${seeded.ids.byId}.blade.php`), { force: true });
    }

    restoreSite();
});

async function expectRendered(page: import('@playwright/test').Page, url: string, expected: Case): Promise<void> {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(url);
    const html = await page.content();

    expect(response?.status(), `status of ${url}`).toBe(expected.status ?? 200);
    expect(renderedTemplate(html)?.template ?? null, `marker of ${url}`).toBe(expected.template);
    await expect(page.locator('main[data-e2e-view]')).toHaveAttribute('data-e2e-view', expected.view);
    expect(errors, 'no uncaught page error').toEqual([]);
}

test.describe('Template hierarchy, with a template for every case', () => {
    test.beforeAll(() => activateTheme('e2e-full'));

    for (const [name, expected] of Object.entries(fullTheme)) {
        test(name, async ({ page }) => {
            // Read at run time: the page-{id} case is only known once the content exists.
            await expectRendered(page, seeded.urls[expected.url], fullTheme[name]);
        });
    }
});

test.describe('Template hierarchy, with index alone', () => {
    test.beforeAll(() => activateTheme('e2e-index'));

    for (const [key, expected] of Object.entries(indexTheme)) {
        test(`${key} falls back to index`, async ({ page }) => {
            await expectRendered(page, seeded.urls[key], expected);
        });
    }

    test('an unknown URL answers 404, not index', async ({ page }) => {
        const response = await page.goto(seeded.urls.notFound);

        expect(response?.status()).toBe(404);
        expect(renderedTemplate(await page.content())).toBeNull();
        await expect(page.locator('main[data-e2e-view="index"]')).toHaveCount(0);
    });
});
