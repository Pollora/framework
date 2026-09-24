import { randomBytes } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { request } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
import { wp } from './support/site';

/**
 * Creates a throwaway administrator for the run and logs it in once.
 * The site's own accounts and content are never used or touched.
 *
 * Playwright skips the global teardown when this setup fails, so a failure
 * after the account exists deletes it here instead of leaving it behind.
 */
export default async function globalSetup(): Promise<void> {
    const username = `e2e-${randomBytes(4).toString('hex')}`;
    const password = randomBytes(18).toString('base64url');

    mkdirSync('./.auth', { recursive: true });
    wp('user', 'create', username, `${username}@example.test`, '--role=administrator', `--user_pass=${password}`, '--porcelain');

    try {
        writeFileSync('./.auth/user.json', JSON.stringify({ username }));
        // The WordPress utilities find the editor by its English labels; the site keeps its own language.
        wp('user', 'meta', 'update', username, 'locale', 'en_US');

        const requestContext = await request.newContext({ baseURL: process.env.WP_BASE_URL, ignoreHTTPSErrors: true });
        const requestUtils = new RequestUtils(requestContext, {
            user: { username, password },
            storageStatePath: './.auth/admin.json',
        });

        await requestUtils.setupRest();
        await requestContext.dispose();

        // Workers start after this and inherit these: the utilities' own worker
        // fixture logs in with them instead of its admin/password defaults.
        process.env.WP_USERNAME = username;
        process.env.WP_PASSWORD = password;
        process.env.STORAGE_STATE_PATH = './.auth/admin.json';
    } catch (error) {
        wp('user', 'delete', username, '--yes');
        throw error;
    }
}
