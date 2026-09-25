import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests run against a real Pollora site.
 *
 * E2E_HOME_URL   the site's front end (WordPress `home`), e.g. https://pollora-test.ddev.site
 * WP_BASE_URL    WordPress itself (`siteurl`), e.g. https://pollora-test.ddev.site/cms/ —
 *                read by @wordpress/e2e-test-utils-playwright for wp-admin, login and REST
 * E2E_WP_CLI     how to run WP-CLI against that site, e.g. "ddev wp" (default)
 * E2E_BROWSERS   comma-separated: chromium (default), firefox, webkit — the nightly runs all three
 */
const homeUrl = process.env.E2E_HOME_URL ?? 'https://pollora-test.ddev.site';
// Trailing slash: relative paths such as wp-login.php must resolve inside /cms.
process.env.WP_BASE_URL ??= `${homeUrl}/cms/`;

// DDEV serves HTTPS with a mkcert certificate. The WordPress utilities open their
// own request contexts, so trust mkcert's authority rather than disabling checks;
// workers are spawned after this file runs and inherit the variable.
if (! process.env.NODE_EXTRA_CA_CERTS) {
    try {
        const rootCa = `${execFileSync('mkcert', ['-CAROOT'], { encoding: 'utf8' }).trim()}/rootCA.pem`;

        if (existsSync(rootCa)) {
            process.env.NODE_EXTRA_CA_CERTS = rootCa;
        }
    } catch {
        // No mkcert: the site's certificate must already be trusted.
    }
}

const browsers: Record<string, string> = {
    chromium: 'Desktop Chrome',
    firefox: 'Desktop Firefox',
    webkit: 'Desktop Safari',
};

const projects = (process.env.E2E_BROWSERS ?? 'chromium').split(',').map((name) => name.trim()).map((name) => {
    if (! (name in browsers)) {
        throw new Error(`E2E_BROWSERS: unknown browser "${name}" (known: ${Object.keys(browsers).join(', ')})`);
    }

    return { name, use: { ...devices[browsers[name]] } };
});

export default defineConfig({
    testDir: './specs',
    globalSetup: './global-setup.ts',
    globalTeardown: './global-teardown.ts',
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    outputDir: './test-results',
    use: {
        baseURL: process.env.WP_BASE_URL,
        ignoreHTTPSErrors: true,
        storageState: './.auth/admin.json',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects,
});
