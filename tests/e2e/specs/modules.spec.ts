import { existsSync } from 'node:fs';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { artisan } from '../support/site';

/**
 * Plugins › Modules: an administrator switches a Laravel module, and the next
 * request no longer has it.
 *
 * The fixture module ships a block and no service provider: once disabled, the
 * block type's REST route answers 404, since nothing registered it.
 */
const MODULE = 'E2eModule';
const BLOCK_ROUTE = '/wp/v2/block-types/e2e-module/module-card';

/**
 * The block type as the REST API answers it: the type, or the error body
 * requestUtils.rest() rejects with on a 404.
 */
async function blockType(requestUtils: { rest: (options: { path: string }) => Promise<unknown> }): Promise<string> {
    return JSON.stringify(await requestUtils.rest({ path: BLOCK_ROUTE }).catch((error: unknown) => error));
}

const fixtureBuilt = existsSync(`${process.env.E2E_SITE_DIR ?? process.cwd()}/Modules/${MODULE}/module.json`);

test.describe('Plugins › Modules', () => {
    test.skip(!fixtureBuilt, 'fixture module missing: run tests/e2e/bin/fixtures.sh up');

    test.afterAll(() => {
        // Whatever happened, give the other specs their module back
        artisan('module:enable', MODULE, '--no-interaction');
    });

    test('lists the modules among the plugin views', async ({ admin, page }) => {
        await admin.visitAdminPage('plugins.php');
        await page.locator('.subsubsub').getByRole('link', { name: /^Modules/ }).click();

        await expect(page).toHaveURL(/plugins\.php\?page=pollora-modules/);
        await expect(page.locator(`tr[data-module="${MODULE}"]`)).toContainText('Enabled');
    });

    test('disables a module from the admin: its block route answers 404 on the next request', async ({ admin, page, requestUtils }) => {
        expect(await blockType(requestUtils)).toContain('"name":"e2e-module/module-card"');

        await admin.visitAdminPage('plugins.php', 'page=pollora-modules');

        // The JSON file does not survive a deployment: each switch asks first
        page.once('dialog', (dialog) => dialog.accept());
        await page.locator(`tr[data-module="${MODULE}"]`).getByRole('button', { name: 'Disable' }).click();

        await expect(page.locator('.notice-success')).toContainText(`${MODULE} disabled. The change applies from the next request.`);
        await expect(page.locator(`tr[data-module="${MODULE}"]`)).toContainText('Disabled');
        expect(await blockType(requestUtils)).toMatch(/rest_block_type_invalid|404/);

        page.once('dialog', (dialog) => dialog.accept());
        await page.locator(`tr[data-module="${MODULE}"]`).getByRole('button', { name: 'Enable' }).click();

        await expect(page.locator('.notice-success')).toContainText(`${MODULE} enabled.`);
        expect(await blockType(requestUtils)).toContain('"name":"e2e-module/module-card"');
    });

    test('warns that the JSON file does not survive a deployment', async ({ admin, page }) => {
        await admin.visitAdminPage('plugins.php', 'page=pollora-modules');

        await expect(page.locator('.notice-warning')).toContainText('Stored in modules_statuses.json — the next deployment resets it.');
    });
});
