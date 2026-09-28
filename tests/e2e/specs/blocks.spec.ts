import { existsSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { runId, wp } from '../support/site';

/**
 * Gutenberg blocks shipped by themes and plugins exist where WordPress needs them:
 * the REST API (the editor and ServerSideRender read it), the editor, and the page.
 *
 * Measured before this suite existed: blocks registered by a theme or plugin
 * provider were visible to WP-CLI only — absent from the editor, the front end
 * and REST. Checking them with `wp eval` is what hid it.
 */

type BlockCase = {
    name: string;
    attributes?: Record<string, unknown>;
    /** Text the block renders on the front end */
    text: string;
};

const pluginBlocks: BlockCase[] = [
    { name: 'e2e-blocks/dynamic-card', text: 'Dynamic Card' },
    { name: 'e2e-blocks/static-card', text: 'Static Card' },
    { name: 'e2e-blocks/inner-card', text: 'Inner Card' },
];

const moduleBlocks: BlockCase[] = [{ name: 'e2e-module/module-card', text: 'Module Card' }];

const generatedThemeBlocks: BlockCase[] = [{ name: 'default/theme-card', text: 'Theme Card' }];

const themeDefaultBlocks: BlockCase[] = [
    { name: 'default/hero', text: 'Hero' },
    { name: 'default/call-to-action', attributes: { heading: `Call to action ${runId}` }, text: `Call to action ${runId}` },
];

function watchPageErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    return errors;
}

/**
 * Whether a fixture exists on disk. A fixture is skipped only when it was never
 * built — never because its block is missing from WordPress, which is the bug.
 */
function fixtureBuilt(relativePath: string): boolean {
    return existsSync(`${process.env.E2E_SITE_DIR ?? process.cwd()}/${relativePath}`);
}

function blockCases(): { host: string; blocks: BlockCase[]; skip: string | null }[] {
    const activePlugins = wp('plugin', 'list', '--status=active', '--field=name').split('\n');
    const activeTheme = wp('theme', 'list', '--status=active', '--field=name');

    return [
        {
            host: 'Laravel module with no service provider',
            blocks: moduleBlocks,
            skip: fixtureBuilt('Modules/E2eModule/resources/views/blocks/module-card/block.json') ? null : 'fixture module missing: run tests/e2e/bin/fixtures.sh up',
        },
        {
            host: 'theme, made there by make:block',
            blocks: generatedThemeBlocks,
            skip: fixtureBuilt('themes/default/resources/views/blocks/theme-card/block.json') ? null : 'no theme block fixture: fixtures.sh only makes one in theme-default',
        },
        {
            host: 'plugin made by make:plugin and make:block',
            blocks: pluginBlocks,
            skip: activePlugins.includes('e2e-blocks') ? null : 'fixture plugin missing: run tests/e2e/bin/fixtures.sh up',
        },
        {
            host: 'theme-default as shipped',
            blocks: themeDefaultBlocks,
            skip: activeTheme === 'default' ? null : `active theme is "${activeTheme}", not theme-default`,
        },
    ];
}

for (const { host, blocks, skip } of blockCases()) {
    test.describe(`Blocks of a ${host}`, () => {
        test.skip(skip !== null, skip ?? '');

        for (const block of blocks) {
            test(`${block.name} is registered for the REST API`, async ({ requestUtils }) => {
                const type = await requestUtils.rest({ path: `/wp/v2/block-types/${block.name}` });

                expect(type.name).toBe(block.name);
                expect(type.editor_script_handles?.length ?? 0).toBeGreaterThan(0);
            });

            test(`${block.name} previews in the editor what the page shows`, async ({ admin, editor }) => {
                await admin.createNewPost({ title: `E2E preview ${block.name} ${runId}` });
                await editor.insertBlock({ name: block.name, attributes: block.attributes });

                // A dynamic block renders on the server: the text arrives from REST
                const preview = editor.canvas.locator(`[data-type="${block.name}"]`);
                await expect(preview).toContainText(block.text);
                await expect(preview).not.toContainText('Block Editor');
            });

            test(`${block.name} is inserted in the editor, stays valid and renders on the page`, async ({ admin, editor, page, requestUtils }) => {
                const errors = watchPageErrors(page);

                await admin.createNewPost({ title: `E2E ${block.name} ${runId}` });

                expect(
                    await page.evaluate((name) => !! window.wp.blocks.getBlockType(name), block.name),
                    'block type known to the editor',
                ).toBe(true);

                await editor.insertBlock({ name: block.name, attributes: block.attributes });
                const postId = await editor.publishPost();

                try {
                    // Reloaded, a static block whose save() output drifted is flagged invalid
                    await admin.editPost(postId);
                    await expect
                        .poll(() => page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => ({ name: b.name, isValid: b.isValid }))))
                        .toEqual([{ name: block.name, isValid: true }]);

                    const post = await requestUtils.rest({ path: `/wp/v2/posts/${postId}` });
                    await page.goto(post.link);

                    const wrapper = page.locator(`.wp-block-${block.name.replace('/', '-')}`);
                    await expect(wrapper).toHaveCount(1);
                    await expect(wrapper).toContainText(block.text);
                    expect(errors, 'no uncaught page error').toEqual([]);
                } finally {
                    await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${postId}`, params: { force: true } });
                }
            });
        }
    });
}

/**
 * <InnerBlocks /> in a Blade template: editable in place in the editor, with the
 * tag's options, and replaced by the inner blocks on the page, in the tag's wrapper.
 * make:block --inner-blocks used to make a block whose children were neither
 * editable in its preview, saved, nor printed.
 */
test.describe('Inner blocks in a Blade template', () => {
    const name = 'e2e-blocks/inner-card';

    test.skip(! wp('plugin', 'list', '--status=active', '--field=name').split('\n').includes('e2e-blocks'), 'fixture plugin missing: run tests/e2e/bin/fixtures.sh up');

    test('are edited in place, saved, and printed in the wrapper the tag names', async ({ admin, editor, page, requestUtils }) => {
        const errors = watchPageErrors(page);
        const text = `E2E inner paragraph ${runId}`;

        await admin.createNewPost({ title: `E2E inner blocks ${runId}` });
        await editor.insertBlock({ name });

        const card = editor.canvas.locator(`[data-type="${name}"]`);
        await expect(card.locator('.e2e-inner-card__title')).toHaveText('Inner Card');

        // The tag's template attribute seeded a paragraph, inside the tag's wrapper
        const body = card.locator('.e2e-inner-card__body');
        await expect(body).toHaveCount(1);
        const paragraph = body.locator('[data-type="core/paragraph"]');
        await expect(paragraph).toHaveCount(1);

        // The tag's allowedBlocks reached the inner block list
        const allowed = await page.evaluate((blockName) => {
            const store = window.wp.data.select('core/block-editor');
            const parent = store.getBlocks().find((block) => block.name === blockName);

            return {
                paragraph: store.canInsertBlockType('core/paragraph', parent.clientId),
                image: store.canInsertBlockType('core/image', parent.clientId),
            };
        }, name);
        expect(allowed).toEqual({ paragraph: true, image: false });

        await paragraph.click();
        await page.keyboard.type(text);
        await expect(paragraph).toHaveText(text);

        const postId = await editor.publishPost();

        try {
            // Saved: the paragraph is in post_content, the block reloads valid
            const saved = await requestUtils.rest({ path: `/wp/v2/posts/${postId}`, params: { context: 'edit' } });
            expect(saved.content.raw).toContain(`<!-- wp:${name} -->`);
            expect(saved.content.raw).toContain(text);

            await admin.editPost(postId);
            await expect
                .poll(() => page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((block) => ({
                    name: block.name,
                    isValid: block.isValid,
                    inner: block.innerBlocks.map((inner) => inner.name),
                }))))
                .toEqual([{ name, isValid: true, inner: ['core/paragraph'] }]);

            // Printed: in the tag's wrapper, inside the block, the tag itself gone
            await page.goto(saved.link);
            const wrapper = page.locator('.wp-block-e2e-blocks-inner-card');
            await expect(wrapper.locator('> .e2e-inner-card__body > p')).toHaveText(text);
            expect(await page.content()).not.toContain('<InnerBlocks');
            expect(errors, 'no uncaught page error').toEqual([]);
        } finally {
            await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${postId}`, params: { force: true } });
        }
    });
});
