import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { runId } from '../support/site';

/**
 * The active theme's Vite entry and WordPress's own script modules, on one page.
 *
 * A Vite entry is an ES module. Printed before WordPress's import map, it voids the map
 * in Firefox and Safari: every WordPress module on the page — the navigation block's,
 * here — then fails on `@wordpress/interactivity`. Chromium tolerates the order, so the
 * order itself is asserted from the HTML, in every browser.
 */

// Named, as the theme may have a navigation block of its own.
const navigation = '<!-- wp:navigation {"overlayMenu":"always","className":"e2e-script-modules"} --><!-- wp:page-list /--><!-- /wp:navigation -->';

test('the theme entry comes after the import map, and WordPress modules run', async ({ page, requestUtils }) => {
    const post = await requestUtils.rest({
        method: 'POST',
        path: '/wp/v2/posts',
        data: { title: `E2E script modules ${runId}`, content: navigation, status: 'publish' },
    });

    try {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto(post.link);
        const html = await page.content();

        const importMap = html.search(/<script\b[^>]*\btype="importmap"/);
        expect(importMap, 'the navigation block puts an import map on the page').toBeGreaterThan(-1);

        const themeEntries = [...html.matchAll(/<script\b[^>]*\bsrc="[^"]*\/build\/theme\/[^"]*"[^>]*>/g)];
        test.skip(themeEntries.length === 0, 'the active theme loads no Vite script');

        for (const entry of themeEntries) {
            expect(entry[0], 'the theme entry is a module').toContain('type="module"');
            expect(entry.index, 'the theme entry is printed after the import map').toBeGreaterThan(importMap);
        }

        const menu = page.locator('.e2e-script-modules');
        await menu.locator('.wp-block-navigation__responsive-container-open').click();
        await expect(menu.locator('.wp-block-navigation__responsive-container')).toHaveClass(/\bis-menu-open\b/);
        expect(errors, 'no uncaught page error').toEqual([]);
    } finally {
        await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${post.id}`, params: { force: true } });
    }
});
