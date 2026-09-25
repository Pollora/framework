import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { wp } from './site';

/**
 * Site state for the template hierarchy spec, which has to switch the active theme and the
 * reading settings. What it changes is written to .auth/hierarchy.json before it changes
 * it, so the spec's own teardown — or the global teardown, if the run was interrupted —
 * can put the site back.
 */
const statePath = './.auth/hierarchy.json';
const fixtures = join(import.meta.dirname, '..', 'fixtures', 'hierarchy');

type SiteState = { theme: string; options: Record<string, string> };

export type Seeded = { ids: Record<string, number>; urls: Record<string, string> };

const readingOptions = ['show_on_front', 'page_on_front', 'page_for_posts'];

/** The PHP of a fixture script, for `wp eval` (which takes code without its opening tag). */
const script = (name: string): string => readFileSync(join(fixtures, name), 'utf8').replace(/^<\?php\s*/, '');

/** Where the site's copy of a fixture theme lives. */
export const themeDir = (theme: string): string => join(process.env.E2E_SITE_DIR ?? process.cwd(), 'themes', theme);

function rememberSite(): void {
    if (existsSync(statePath)) {
        return; // An interrupted run already recorded the site as it was before any test touched it.
    }

    const state: SiteState = {
        theme: wp('theme', 'list', '--status=active', '--field=name'),
        options: Object.fromEntries(readingOptions.map((name) => [name, wp('option', 'get', name)])),
    };

    writeFileSync(statePath, JSON.stringify(state));
}

export function activateTheme(theme: string): void {
    rememberSite();
    wp('theme', 'activate', theme);
    // Post type and taxonomy URLs change with the theme that registers them.
    wp('rewrite', 'flush');
}

/** Clear anything an earlier run left, then create the content. Needs e2e-full active. */
export function seed(): Seeded {
    wp('eval', script('cleanup.php'));

    return JSON.parse(wp('eval', script('seed.php'))) as Seeded;
}

/** Remove the content and give the site back its theme and reading settings. Safe to run twice. */
export function restoreSite(): void {
    if (! existsSync(statePath)) {
        return;
    }

    const state = JSON.parse(readFileSync(statePath, 'utf8')) as SiteState;

    // The e2e_genre terms can only be deleted while the theme registering them is active.
    if (existsSync(themeDir('e2e-full'))) {
        wp('theme', 'activate', 'e2e-full');
        wp('eval', script('cleanup.php'));
    }

    for (const [name, value] of Object.entries(state.options)) {
        wp('option', 'update', name, value);
    }

    wp('theme', 'activate', state.theme);
    wp('rewrite', 'flush');
    rmSync(statePath);
}
