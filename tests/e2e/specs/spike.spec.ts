import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { homeUrl, renderedTemplate, runId } from '../support/site';

test.describe('Spike: a Pollora site under Playwright', () => {
    test('the home page renders a template the framework names', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        const response = await page.goto(homeUrl('/'));

        expect(response?.status()).toBe(200);
        expect(renderedTemplate(await page.content())).not.toBeNull();
        expect(errors).toEqual([]);
    });

    test('a post written in the block editor renders on the front end', async ({ admin, editor, page, requestUtils }) => {
        const title = `E2E spike ${runId} ${Date.now()}`;
        const sentence = `Written by Playwright in run ${runId}.`;

        await admin.createNewPost({ title });
        await editor.insertBlock({ name: 'core/paragraph', attributes: { content: sentence } });
        const postId = await editor.publishPost();

        try {
            const post = await requestUtils.rest({ path: `/wp/v2/posts/${postId}` });
            const errors: string[] = [];
            page.on('pageerror', (error) => errors.push(error.message));

            const response = await page.goto(post.link);

            expect(response?.status()).toBe(200);
            await expect(page.getByText(sentence)).toBeVisible();
            expect(renderedTemplate(await page.content())?.template).toBe('single');
            expect(errors).toEqual([]);
        } finally {
            await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${postId}`, params: { force: true } });
        }
    });
});
