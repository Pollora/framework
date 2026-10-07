import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { request as playwrightRequest } from '@playwright/test';
import { artisan, wp } from '../support/site';

/**
 * Asynchronous actions: an #[Action] made asynchronous by #[Async(via: 'queue')]
 * is queued as a Laravel job when its hook fires, and runs in a queue worker.
 */
test.describe('Asynchronous actions', () => {
    const result = (): unknown => {
        try {
            return JSON.parse(wp('option', 'get', 'e2e_async_result', '--format=json'));
        } catch {
            return null;
        }
    };

    test.beforeEach(() => {
        try {
            wp('option', 'delete', 'e2e_async_result');
        } catch {
            // Not there yet
        }
        // Leave no job of an earlier run behind
        artisan('queue:clear', '--force');
    });

    test('an #[Action] with #[Async] runs in a Laravel queue worker, not in the request', async () => {
        const visitor = await playwrightRequest.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
        const response = await visitor.get(new URL('wp-admin/admin-ajax.php?action=e2e_async_dispatch&id=42', process.env.WP_BASE_URL).toString());

        expect(await response.json()).toEqual({ success: true, data: { queued: true } });
        expect(result()).toBeNull();
        await visitor.dispose();

        artisan('queue:work', '--once', '--stop-when-empty');

        expect(result()).toEqual({ id: 42, hook: 'e2e_async_event', console: true, injected: true });
    });
});
