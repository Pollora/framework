import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { runId } from '../support/site';

/**
 * Block styles and global styles are printed in the head.
 *
 * WordPress 7 loads a classic theme's block styles on demand: printed at wp_footer,
 * once the page's blocks are known, then moved back into the head by the template
 * enhancement output buffer — which WordPress only starts when it includes a template.
 * A Blade page includes none, so until the framework played the buffer's part they
 * stayed at the bottom of the page, after the content they style, and the head kept
 * two empty placeholders. A block theme prints them in the head itself: the assertions
 * hold for both.
 */

// A block whose stylesheet only loads when the block is on the page.
const separator = '<!-- wp:separator {"className":"e2e-styles"} --><hr class="wp-block-separator has-alpha-channel-opacity e2e-styles"/><!-- /wp:separator -->';

test('block styles and global styles are in the head, not after the content', async ({ page, requestUtils }) => {
    const post = await requestUtils.rest({
        method: 'POST',
        path: '/wp/v2/posts',
        data: { title: `E2E styles ${runId}`, content: separator, status: 'publish' },
    });

    try {
        const response = await page.request.get(post.link);
        expect(response.status()).toBe(200);

        const html = await response.text();
        const headEnd = html.indexOf('</head>');
        expect(headEnd, 'the page has a head').toBeGreaterThan(-1);

        for (const [name, pattern] of [
            ['global styles', /id=["']global-styles-inline-css["']/],
            ['the separator block style', /id=["']wp-block-separator-(?:inline-)?css["']/],
        ] as const) {
            const position = html.search(pattern);
            expect(position, `${name} are on the page`).toBeGreaterThan(-1);
            expect(position, `${name} are printed in the head`).toBeLessThan(headEnd);
        }

        expect(html, 'no placeholder is left for the hoisted styles').not.toContain('-styles-placeholder-inline-css');
    } finally {
        await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${post.id}`, params: { force: true } });
    }
});
