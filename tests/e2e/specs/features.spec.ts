import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';
import { request as playwrightRequest, type APIRequestContext, type Page } from '@playwright/test';
import { homeUrl, runId, wp } from '../support/site';

/**
 * Framework features, each declared by the e2e-features fixture plugin or by the active
 * theme, and each checked by the effect a visitor or an editor would see.
 *
 * The plugin works whatever the active theme; the theme checks (built assets, menus,
 * login screen) read what the active theme declares instead of assuming one.
 */

type FooterData = { action: string; themeEntry: string | null; themeFileUri: string | null };

const siteDir = process.env.E2E_SITE_DIR ?? process.cwd();
const activeTheme = (): string => wp('theme', 'list', '--status=active', '--field=name');

async function footerData(page: Page): Promise<FooterData> {
    return JSON.parse((await page.locator('script#e2e-features').textContent()) ?? 'null') as FooterData;
}

/** A request context with no session, as a visitor. */
async function visitor(): Promise<APIRequestContext> {
    return playwrightRequest.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
}

test.describe('Hooks declared by attribute', () => {
    test('an #[Action] on wp_footer prints its marker', async ({ page }) => {
        await page.goto(homeUrl('/'));

        expect((await footerData(page)).action).toBe('wp_footer');
    });

    test('a #[Filter] on the_content changes a post', async ({ page, requestUtils }) => {
        const post = await requestUtils.rest({
            method: 'POST',
            path: '/wp/v2/posts',
            data: { title: `E2E filter ${runId}`, content: '<p>Body.</p>', status: 'publish' },
        });

        try {
            await page.goto(post.link);
            await expect(page.locator('[data-e2e-filter="the_content"]')).toHaveCount(1);
        } finally {
            await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/posts/${post.id}`, params: { force: true } });
        }
    });
});

test.describe('A post type declared by a plugin', () => {
    test('is registered, with its archive and its admin menu', async ({ page, requestUtils }) => {
        const type = await requestUtils.rest({ path: '/wp/v2/types/e2e_event' });
        expect(type.slug).toBe('e2e_event');

        const archive = await page.goto(wp('eval', 'echo get_post_type_archive_link("e2e_event");'));
        expect(archive?.status()).toBe(200);

        await page.goto(new URL('wp-admin/', process.env.WP_BASE_URL).toString());
        await expect(page.locator('#adminmenu a[href="edit.php?post_type=e2e_event"]').first()).toBeAttached();
    });
});

test.describe('REST routes declared by #[WpRestRoute]', () => {
    test('a route with no permission is public', async () => {
        const context = await visitor();
        const response = await context.get(homeUrl('/wp-json/e2e/v1/public'));

        expect(response.status()).toBe(200);
        expect(await response.json()).toEqual({ route: 'public' });
        await context.dispose();
    });

    test('an IsAdmin route refuses a visitor', async () => {
        const context = await visitor();
        const response = await context.get(homeUrl('/wp-json/e2e/v1/admin/7'));

        expect(response.status()).toBe(403);
        await context.dispose();
    });

    test('an IsAdmin route answers an administrator, with its route parameter', async ({ requestUtils }) => {
        expect(await requestUtils.rest({ path: '/e2e/v1/admin/7' })).toEqual({ route: 'admin', id: 7 });
    });
});

test.describe('Ajax actions declared by #[Ajax]', () => {
    const ajaxUrl = (): string => new URL('wp-admin/admin-ajax.php?action=e2e_ping', process.env.WP_BASE_URL).toString();

    test('a visitor gets an answer', async () => {
        const context = await visitor();
        const response = await context.get(ajaxUrl());

        expect(await response.json()).toEqual({ success: true, data: { pong: true, loggedIn: false } });
        await context.dispose();
    });

    test('a logged-in user gets an answer too', async ({ page }) => {
        const response = await page.request.get(ajaxUrl());

        expect(await response.json()).toEqual({ success: true, data: { pong: true, loggedIn: true } });
    });
});

test.describe('Assets', () => {
    test('a script enqueued through the Asset facade runs', async ({ page }) => {
        await page.goto(homeUrl('/'));

        await expect(page.locator('html')).toHaveAttribute('data-e2e-features-script', 'ran');
    });

    test('every script and stylesheet of the home page loads, none over plain http', async ({ page }) => {
        const failed: string[] = [];
        const insecure: string[] = [];
        const mixed: string[] = [];

        page.on('response', (response) => {
            const type = response.request().resourceType();

            if ((type === 'script' || type === 'stylesheet') && response.status() >= 400) {
                failed.push(`${response.status()} ${response.url()}`);
            }
        });
        page.on('request', (request) => {
            if (request.url().startsWith('http://')) {
                insecure.push(request.url());
            }
        });
        page.on('console', (message) => {
            if (message.text().includes('Mixed Content')) {
                mixed.push(message.text());
            }
        });

        const response = await page.goto(homeUrl('/'), { waitUntil: 'load' });
        const html = await page.content();

        expect(response?.status()).toBe(200);
        expect(failed, 'scripts and stylesheets that failed').toEqual([]);
        expect(insecure, 'requests over plain http').toEqual([]);
        expect(mixed, 'mixed content').toEqual([]);
        expect(html, 'no PHP warning in the page').not.toMatch(/<b>(Warning|Notice|Deprecated)<\/b>:/);
    });

    test("the active theme's built entry has a URL through get_theme_file_uri()", async ({ page }) => {
        await page.goto(homeUrl('/'));
        const data = await footerData(page);

        expect(data.themeEntry, `the active theme (${activeTheme()}) has no Vite build`).not.toBeNull();
        expect(data.themeFileUri).toContain(`/build/theme/${activeTheme()}/`);
        expect((await page.request.get(data.themeFileUri as string)).status()).toBe(200);
    });

    // As a visitor: a debugging plugin such as Query Monitor shows paths to administrators, on purpose.
    test('no server path leaks into the page a visitor gets', async () => {
        const basePath = wp('eval', 'echo base_path();');
        const context = await visitor();
        const html = await (await context.get(homeUrl('/'))).text();
        await context.dispose();

        expect(html.includes(basePath), `the page contains the server path ${basePath}`).toBe(false);
    });
});

test.describe('Translations through __()', () => {
    test("a text domain goes to WordPress's catalogues, replacements to Laravel", async () => {
        const context = await visitor();
        const translations = await (await context.get(homeUrl('/wp-json/e2e/v1/translations'))).json();
        await context.dispose();

        expect(translations).toEqual({
            wordpress: 'Bonjour depuis le catalogue',
            untranslated: 'E2E string outside the catalogue',
            laravel: 'Shipping Example',
            wpNative: 'Bonjour depuis le catalogue',
        });
    });
});

test.describe('The active theme', () => {
    test('registers the menu locations its config/menus.php declares', async ({ requestUtils }) => {
        const configFile = join(siteDir, 'themes', activeTheme(), 'config', 'menus.php');
        test.skip(! existsSync(configFile), 'the active theme declares no menus');

        const declared = JSON.parse(wp('eval', 'echo json_encode(array_keys((array) include get_stylesheet_directory()."/config/menus.php"));')) as string[];
        const registered = Object.keys(await requestUtils.rest({ path: '/wp/v2/menu-locations' }));

        expect(declared.length).toBeGreaterThan(0);
        expect(registered).toEqual(expect.arrayContaining(declared));
    });

    test('dresses the login screen when its config/login.php asks for it', async ({ browser }) => {
        const dressed = existsSync(join(siteDir, 'themes', activeTheme(), 'config', 'login.php'));
        const context = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
        const page = await context.newPage();

        const response = await page.goto(new URL('wp-login.php', process.env.WP_BASE_URL).toString());

        expect(response?.status()).toBe(200);
        await expect(page.locator('#loginform')).toBeVisible();
        await expect(page.locator('style#pollora-login')).toHaveCount(dressed ? 1 : 0);

        if (dressed) {
            await expect(page.locator('body')).toHaveClass(/pollora-login/);
            expect(await page.locator('style#pollora-login').textContent()).toMatch(/--pollora-login-primary:\s*[^;]+;/);
        }

        await context.close();
    });
});
