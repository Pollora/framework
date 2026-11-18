import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { DIAGNOSTICS_HEADER, median, type Metrics, parseDiagnostics } from '../support/perf.ts';
import { artisan, homeUrl, runId, wp } from '../support/site';

/**
 * PERF-07: boot, cold discovery and thumbnail generation, measured every night.
 *
 * It writes ./test-results/perf-metrics.json (override with E2E_PERF_OUT); bin/perf-compare.ts
 * compares that file with the last good night's and fails past 10 %. Skipped unless
 * E2E_PERF=1: the ordinary suite measures nothing here, and a timing taken in the middle of
 * a run that does other things would mean little. Run it on its own:
 *
 *   E2E_PERF=1 npx playwright test perf-metrics
 *
 * Each metric is the median of several runs, which absorbs the occasional slow one on a
 * shared runner; a median still moves when the code gets slower.
 *
 *   boot_ms        time from the start of the request to wp_loaded, warm (REST index)
 *   cold_boot_ms   the same, on the first request after `discovery:clear`
 *   thumbnail_ms   wp_generate_attachment_metadata() for one 2400x1600 JPEG, every size
 */
const out = process.env.E2E_PERF_OUT ?? './test-results/perf-metrics.json';
const warmRuns = 7;
const coldRuns = 3;
const thumbnailRuns = 3;
const metrics: Metrics = {};

test.skip(process.env.E2E_PERF !== '1', 'set E2E_PERF=1 to measure');
test.describe.configure({ mode: 'serial' });
test.use({ storageState: { cookies: [], origins: [] } });

test.beforeEach(({}, testInfo) => {
    const first = (process.env.E2E_BROWSERS ?? 'chromium').split(',')[0].trim();

    test.skip(testInfo.project.name !== first, 'the timings do not depend on the browser');
});

test.afterAll(() => {
    if (Object.keys(metrics).length === 0) {
        return;
    }

    mkdirSync(join(out, '..'), { recursive: true });
    writeFileSync(out, `${JSON.stringify({ measuredAt: new Date().toISOString(), run: runId, metrics }, null, 2)}\n`);
});

/** One REST index request, and the boot time the framework reported for it. */
async function bootMs(request: import('@playwright/test').APIRequestContext): Promise<number> {
    const response = await request.get(homeUrl('/?rest_route=/'), { headers: { [DIAGNOSTICS_HEADER]: '1' } });
    const diagnostics = parseDiagnostics(response.headers()[DIAGNOSTICS_HEADER.toLowerCase()], '');

    expect(response.status()).toBe(200);
    expect(diagnostics?.boot_ms, 'no boot time reported: is WP_DEBUG on?').toBeGreaterThan(0);

    return diagnostics?.boot_ms as number;
}

test('boot, warm', async ({ request }) => {
    await bootMs(request); // The first one builds what later ones reuse.

    const samples: number[] = [];

    for (let run = 0; run < warmRuns; run++) {
        samples.push(await bootMs(request));
    }

    metrics.boot_ms = median(samples);
});

test('boot, cold discovery', async ({ request }) => {
    const samples: number[] = [];

    for (let run = 0; run < coldRuns; run++) {
        artisan('discovery:clear');
        samples.push(await bootMs(request));
    }

    metrics.cold_boot_ms = median(samples);

    // Leave the cache built, as the other specs expect it.
    await bootMs(request);
});

test('thumbnail generation', () => {
    const script = readFileSync(join(import.meta.dirname, '..', 'fixtures', 'perf', 'thumbnail.php'), 'utf8').replace(/^<\?php\s*/, '');
    const samples: number[] = [];

    for (let run = 0; run < thumbnailRuns; run++) {
        const result = JSON.parse(wp('eval', script)) as { thumbnail_ms: number; sizes: number };

        expect(result.sizes, 'no size was generated: the measure would be of nothing').toBeGreaterThan(0);
        samples.push(result.thumbnail_ms);
    }

    metrics.thumbnail_ms = median(samples);
});
