import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { homeUrl, runId, wp } from '../support/site';

/**
 * Block Bindings: core blocks and a Blade block take their value from a source
 * declared by #[BlockBinding] (the e2e-features plugin) and from the typed meta
 * sources Pollora ships, on the page a visitor sees.
 */

/** A block bound to a source, as the editor saves it. */
function bound(block: string, bindings: Record<string, unknown>, html: string, attributes: Record<string, unknown> = {}): string {
    const attrs = JSON.stringify({ ...attributes, metadata: { bindings } });

    return html === '' ? `<!-- wp:${block} ${attrs} /-->` : `<!-- wp:${block} ${attrs} -->${html}<!-- /wp:${block} -->`;
}

const content = [
    bound('paragraph', { content: { source: 'e2e/event', args: { field: 'seats' } } }, '<p class="e2e-seats">Saved seats</p>', { className: 'e2e-seats' }),
    `<!-- wp:buttons --><div class="wp-block-buttons">${bound(
        'button',
        { url: { source: 'e2e/event', args: { field: 'booking_url' } } },
        '<div class="wp-block-button e2e-book"><a class="wp-block-button__link wp-element-button">Book</a></div>',
        { className: 'e2e-book' },
    )}</div><!-- /wp:buttons -->`,
    bound('heading', { content: { source: 'pollora/post-meta', args: { key: 'starts_at', format: 'Y-m-d' } } }, '<h2 class="wp-block-heading e2e-date">Date to come</h2>', { className: 'e2e-date' }),
    bound('paragraph', { content: { source: 'pollora/post-meta', args: { key: 'capacity', format: 'raw' } } }, '<p class="e2e-capacity">Saved capacity</p>', { className: 'e2e-capacity' }),
    bound('paragraph', { content: { source: 'pollora/post-meta', args: { key: 'sold_out', true: 'Sold out', false: 'Seats left' } } }, '<p class="e2e-sold-out">Saved availability</p>', { className: 'e2e-sold-out' }),
    bound('paragraph', { content: { source: 'pollora/post-meta', args: { key: 'backstage_code' } } }, '<p class="e2e-backstage">Kept</p>', { className: 'e2e-backstage' }),
    bound('e2e-blocks/bound-card', { title: { source: 'e2e/event', args: { field: 'seats' } } }, ''),
].join('\n');

test.describe('Block Bindings', () => {
    let event: { id: number; link: string };

    test.beforeAll(async ({ requestUtils }) => {
        // The e2e_event single needs its rewrite rules, which activating the plugin does not write
        wp('rewrite', 'flush');

        event = await requestUtils.rest({
            method: 'POST',
            path: '/wp/v2/e2e_event',
            data: {
                title: `E2E bound event ${runId}`,
                status: 'publish',
                content,
                meta: { capacity: 1200, starts_at: '2026-11-14T09:00:00+00:00', sold_out: false },
            },
        });
    });

    test.afterAll(async ({ requestUtils }) => {
        await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/e2e_event/${event.id}`, params: { force: true } });
    });

    test('a #[BlockBinding] field fills a paragraph, escaped, and a button URL', async ({ page }) => {
        await page.goto(event.link);

        await expect(page.locator('p.e2e-seats')).toHaveText('1200 seats <for> you');
        await expect(page.locator('.e2e-book a')).toHaveAttribute('href', homeUrl(`/book/${event.id}`));
    });

    test('a Blade block listing the attribute under pollora.bindings shows the bound value', async ({ page }) => {
        await page.goto(event.link);

        await expect(page.locator('.e2e-bound-card__title')).toHaveText('1200 seats <for> you');
    });

    test('pollora/post-meta formats a typed meta by its type', async ({ page }) => {
        await page.goto(event.link);

        await expect(page.locator('h2.e2e-date')).toHaveText('2026-11-14');
        await expect(page.locator('p.e2e-capacity')).toHaveText('1200');
        await expect(page.locator('p.e2e-sold-out')).toHaveText('Seats left');
    });

    test('pollora/post-meta never shows a meta kept out of REST', async ({ page }) => {
        await page.goto(event.link);

        await expect(page.locator('p.e2e-backstage')).toHaveText('Kept');
    });

    test('the editor knows the sources and opens the bound blocks as valid', async ({ admin, page }) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await admin.editPost(event.id);

        await expect
            .poll(() => page.evaluate(() => window.wp.blocks.getBlockBindingsSources()))
            .toMatchObject({ 'e2e/event': { label: 'E2E Event' }, 'pollora/post-meta': {}, 'pollora/option': {} });
        await expect
            .poll(() => page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().every((block) => block.isValid)))
            .toBe(true);
        expect(errors, 'no uncaught page error').toEqual([]);
    });

    test('the editor previews the bound values, escaped like the page', async ({ admin, editor }) => {
        await admin.editPost(event.id);

        await expect(editor.canvas.locator('p.e2e-seats')).toHaveText('1200 seats <for> you');
        await expect(editor.canvas.locator('h2.e2e-date')).toHaveText('2026-11-14');
        await expect(editor.canvas.locator('.e2e-bound-card__title')).toHaveText('1200 seats <for> you');
    });

    test('a field is chosen in the Attributes panel and its value shows at once', async ({ admin, editor, page }) => {
        await admin.editPost(event.id);
        await editor.insertBlock({ name: 'core/paragraph', attributes: { content: 'Pick a field', className: 'e2e-picked' } });
        await editor.openDocumentSettingsSidebar();

        const panel = page.locator('.block-editor-bindings__panel');
        await panel.getByRole('button', { name: /Attributes options/i }).click();
        await page.getByRole('menuitemcheckbox', { name: /content/i }).click();
        await page.keyboard.press('Escape');
        await panel.getByRole('button', { name: /content/i }).click();

        // The menus open on hover and move under the pointer: a forced click is the stable one
        const openMenus = page.locator('[role=menu][data-open="true"]');
        await openMenus.getByRole('menuitem', { name: 'E2E Event' }).click();
        const fields = openMenus.last();
        await expect(fields.locator('[role^=menuitem]')).toHaveText([/Seats/, /Booking link/]);
        await fields.locator('[role^=menuitem]').filter({ hasText: 'Seats' }).first().click({ force: true });

        await expect
            .poll(() => page.evaluate(() => window.wp.data.select('core/block-editor').getSelectedBlock()?.attributes.metadata?.bindings))
            .toEqual({ content: { source: 'e2e/event', args: { field: 'seats' } } });
        await expect(editor.canvas.locator('p.e2e-picked')).toHaveText('1200 seats <for> you');
    });
});
