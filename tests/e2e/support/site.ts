import { execFileSync } from 'node:child_process';

export const homeUrl = (path = '/'): string =>
    new URL(path, `${process.env.E2E_HOME_URL ?? 'https://pollora-test.ddev.site'}/`).toString();

/**
 * Run WP-CLI against the site under test: E2E_WP_CLI ("ddev wp" by default),
 * from E2E_SITE_DIR (the project root DDEV needs; the current directory by default).
 * Passwords are redacted from the error, which would otherwise print the command.
 */
export function wp(...args: string[]): string {
    const [command, ...prefix] = (process.env.E2E_WP_CLI ?? 'ddev wp').split(' ');

    try {
        return execFileSync(command, [...prefix, ...args], {
            cwd: process.env.E2E_SITE_DIR ?? process.cwd(),
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }).trim();
    } catch (error) {
        const redacted = args.map((arg) => arg.replace(/^(--user_pass=).*/, '$1***')).join(' ');
        const stderr = (error as { stderr?: string }).stderr ?? '';

        throw new Error(`WP-CLI failed: ${command} ${prefix.join(' ')} ${redacted}\n${stderr}`);
    }
}

/**
 * The template that rendered a page, from the marker the framework writes under WP_DEBUG.
 */
export function renderedTemplate(html: string): { template: string; path: string } | null {
    const match = html.match(/<!-- pollora:template="([^"]+)" path="([^"]+)" -->/);

    return match ? { template: match[1], path: match[2] } : null;
}

export const runId = process.env.E2E_RUN_ID ?? 'local';
