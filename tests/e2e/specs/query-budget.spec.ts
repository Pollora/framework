import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import {
    type Budgets,
    type Counts,
    DIAGNOSTICS_HEADER,
    type Diagnostics,
    lowestCounts,
    overBudget,
    parseDiagnostics,
    unmeasured,
    withMeasured,
} from '../support/perf.ts';
import { homeUrl } from '../support/site';

/**
 * PERF-01: the number of SQL queries per type of page never exceeds the reference.
 *
 * Each page is asked for its own cost: the framework answers an `X-Pollora-Diagnostics: 1`
 * request with its WordPress and Laravel query counts when WP_DEBUG is on (a header on REST,
 * a trailing comment on HTML). The counts are deterministic, so the margin is zero.
 *
 * The references live in budgets.json. They are those of the CI site: a fresh install with
 * the fixtures of this suite. A site with other content has other counts.
 *
 *   E2E_UPDATE_BUDGETS=1 npx playwright test query-budget   writes the measured counts
 *                                                           into budgets.json (npm run budgets:update)
 *
 * A page whose budget is still null is measured and reported, never failed. Every run
 * writes what it measured to test-results/budgets.measured.json, which CI uploads.
 */
const budgetsFile = join(import.meta.dirname, '..', 'budgets.json');
const measuredFile = './test-results/budgets.measured.json';
const updating = process.env.E2E_UPDATE_BUDGETS === '1';

/** Pages by type. The ids are those WordPress creates on install (Hello world, Sample Page). */
const pages: Record<string, { path: string; status: number; rest?: boolean }> = {
    home: { path: '/', status: 200 },
    post: { path: '/?p=1', status: 200 },
    page: { path: '/?page_id=2', status: 200 },
    archive: { path: '/?cat=1', status: 200 },
    notFound: { path: '/?p=999999999', status: 404 },
    search: { path: '/?s=hello', status: 200 },
    rest: { path: '/?rest_route=/wp/v2/posts', status: 200, rest: true },
};

const measured: Record<string, Counts> = {};

// An anonymous visitor, not the administrator the suite logs in: that is the page a budget is for.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe.configure({ mode: 'serial' });

// The counts do not depend on the browser: measure once, in the first project.
test.beforeEach(({}, testInfo) => {
    const first = (process.env.E2E_BROWSERS ?? 'chromium').split(',')[0].trim();

    test.skip(testInfo.project.name !== first, 'query counts do not depend on the browser');
});

test.afterAll(() => {
    if (Object.keys(measured).length === 0) {
        return;
    }

    mkdirSync('./test-results', { recursive: true });
    writeFileSync(measuredFile, `${JSON.stringify(withMeasured({ pages: {} }, measured), null, 2)}\n`);

    if (updating && Object.keys(measured).length === Object.keys(pages).length) {
        const budgets = JSON.parse(readFileSync(budgetsFile, 'utf8')) as Budgets;

        writeFileSync(budgetsFile, `${JSON.stringify(withMeasured(budgets, measured), null, 2)}\n`);
    }
});

for (const [name, page] of Object.entries(pages)) {
    test(`${name} stays within its query budget`, async ({ playwright }) => {
        const budgets = JSON.parse(readFileSync(budgetsFile, 'utf8')) as Budgets;
        const context = await playwright.request.newContext({ ignoreHTTPSErrors: true, extraHTTPHeaders: { [DIAGNOSTICS_HEADER]: '1' } });

        try {
            // The first request warms what a request builds once (discovery, caches, opcache);
            // the next ones are the page's own cost.
            const samples: Diagnostics[] = [];

            for (let attempt = 0; attempt < 4; attempt++) {
                const response = await context.get(homeUrl(page.path));
                const diagnostics = parseDiagnostics(response.headers()[DIAGNOSTICS_HEADER.toLowerCase()], await response.text());

                expect(response.status(), `${name}: ${page.path}`).toBe(page.status);
                expect(diagnostics, `${name} sent no diagnostics: is WP_DEBUG on, and the framework recent enough?`).not.toBeNull();

                if (attempt > 0) {
                    samples.push(diagnostics as Diagnostics);
                }
            }

            const counts = lowestCounts(samples);
            measured[name] = counts;
            test.info().annotations.push({ type: 'measured', description: `${name}: WordPress ${counts.wordpress}, Laravel ${counts.laravel}` });

            if (updating) {
                return;
            }

            const budget = budgets.pages[name];

            if (unmeasured(budget)) {
                test.info().annotations.push({ type: 'unmeasured', description: `${name} has no budget yet: commit the counts of test-results/budgets.measured.json` });

                return;
            }

            expect(overBudget(name, budget, counts), `${name} runs more queries than its budget (${JSON.stringify(budget)}); measured ${JSON.stringify(counts)}`).toEqual([]);
        } finally {
            await context.dispose();
        }
    });
}
