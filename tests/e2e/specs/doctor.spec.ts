import { expect, test } from '@wordpress/e2e-test-utils-playwright';

/**
 * pollora:doctor's checks in WordPress's Site Health, read by an administrator.
 *
 * The point of running them there: an administrator's web request is the boot a
 * visitor gets. Blocks registered under WP-CLI but missing over HTTP were exactly
 * that kind of failure, and the console check could never have seen it.
 */
test('Site Health lists the Pollora checks, the web-only block check included', async ({ admin, page }) => {
    await admin.visitAdminPage('site-health.php');

    // Site Health lists its results once every test, asynchronous ones included, has answered.
    await expect(page.getByText('Results are still loading')).toHaveCount(0, { timeout: 60_000 });

    // Passed tests sit in a collapsed list: read the triggers, visible or not.
    const results = page.locator('.health-check-accordion-trigger');

    for (const label of ['WordPress core patch', 'Composer patches lock', 'Theme and its build', 'Theme blocks registered']) {
        await expect(results.filter({ hasText: label }).filter({ hasText: 'Pollora' }), label).toHaveCount(1);
    }

    // The console-only check has no place there.
    await expect(results.filter({ hasText: 'Discovery cache' })).toHaveCount(0);
});
