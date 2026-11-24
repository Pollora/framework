import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    compareMetrics,
    describeCalibration,
    describeComparison,
    judgeRun,
    type Diagnostics,
    lowestCounts,
    median,
    overBudget,
    parseDiagnostics,
    unmeasured,
    withMeasured,
} from './perf.ts';

const report = (wordpress: number, laravel = 0): Diagnostics => ({ wordpress_queries: wordpress, laravel_queries: laravel, boot_ms: 40.2, elapsed_ms: 90 });

describe('parseDiagnostics', () => {
    it('reads the REST header', () => {
        assert.deepEqual(parseDiagnostics(JSON.stringify(report(12, 1)), '{}'), report(12, 1));
    });

    it('reads the trailing comment of an HTML page, the last one when a theme printed its own', () => {
        const html = `<html></html>\n<!-- pollora:diagnostics {"note":"theirs"} -->\n<!-- pollora:diagnostics ${JSON.stringify(report(31))} -->\n`;

        assert.deepEqual(parseDiagnostics(undefined, html), report(31));
    });

    it('accepts a boot time of null', () => {
        const noBoot = { ...report(3), boot_ms: null };

        assert.deepEqual(parseDiagnostics(JSON.stringify(noBoot), ''), noBoot);
    });

    it('answers null when the response carries no report, or a malformed one', () => {
        assert.equal(parseDiagnostics(undefined, '<html></html>'), null);
        assert.equal(parseDiagnostics('not json', ''), null);
        assert.equal(parseDiagnostics('{"wordpress_queries":"12"}', ''), null);
        assert.equal(parseDiagnostics('{"wordpress_queries":1.5,"laravel_queries":0,"boot_ms":null,"elapsed_ms":1}', ''), null);
        assert.equal(parseDiagnostics('null', ''), null);
    });

    it('falls back to the comment when the header is unusable', () => {
        assert.deepEqual(parseDiagnostics('garbage', `<!-- pollora:diagnostics ${JSON.stringify(report(7))} -->`), report(7));
    });
});

describe('lowestCounts', () => {
    it('takes the lowest of each layer, separately', () => {
        assert.deepEqual(lowestCounts([report(30, 2), report(28, 3), report(29, 1)]), { wordpress: 28, laravel: 1 });
    });

    it('refuses to answer without a sample', () => {
        assert.throws(() => lowestCounts([]), /no sample/);
    });
});

describe('overBudget', () => {
    it('fails above the budget, with no margin', () => {
        assert.deepEqual(overBudget('home', { wordpress: 20, laravel: 0 }, { wordpress: 21, laravel: 0 }), [
            { page: 'home', layer: 'wordpress', budget: 20, measured: 21 },
        ]);
    });

    it('passes at the budget and below it', () => {
        assert.deepEqual(overBudget('home', { wordpress: 20, laravel: 2 }, { wordpress: 20, laravel: 0 }), []);
    });

    it('reports every layer that is over', () => {
        assert.equal(overBudget('post', { wordpress: 1, laravel: 1 }, { wordpress: 2, laravel: 2 }).length, 2);
    });

    it('never fails a layer without a budget yet', () => {
        assert.deepEqual(overBudget('home', { wordpress: null, laravel: null }, { wordpress: 999, laravel: 999 }), []);
    });

    it('treats a budget of zero as a real limit', () => {
        assert.equal(overBudget('rest', { wordpress: 5, laravel: 0 }, { wordpress: 5, laravel: 1 }).length, 1);
    });
});

describe('unmeasured', () => {
    it('is true for a missing page and for a page with a null layer', () => {
        assert.equal(unmeasured(undefined), true);
        assert.equal(unmeasured({ wordpress: 3, laravel: null }), true);
        assert.equal(unmeasured({ wordpress: null, laravel: 0 }), true);
        assert.equal(unmeasured({ wordpress: 3, laravel: 0 }), false);
    });
});

describe('withMeasured', () => {
    it('writes the measured counts and keeps the pages it did not measure', () => {
        const budgets = { pages: { home: { wordpress: null, laravel: null }, post: { wordpress: 9, laravel: 0 } } };

        assert.deepEqual(withMeasured(budgets, { home: { wordpress: 20, laravel: 1 } }), {
            pages: { home: { wordpress: 20, laravel: 1 }, post: { wordpress: 9, laravel: 0 } },
        });
    });

    it('does not change its input', () => {
        const budgets = { pages: { home: { wordpress: null, laravel: null } } };
        withMeasured(budgets, { home: { wordpress: 1, laravel: 1 } });

        assert.equal(budgets.pages.home.wordpress, null);
    });
});

describe('median', () => {
    it('takes the middle value, whatever the order', () => {
        assert.equal(median([9, 1, 5]), 5);
    });

    it('averages the two middle values of an even list', () => {
        assert.equal(median([4, 1, 2, 10]), 3);
    });

    it('refuses an empty list', () => {
        assert.throws(() => median([]), /no value/);
    });
});

describe('compareMetrics', () => {
    const baseline = { boot_ms: 100, cold_boot_ms: 400, thumbnail_ms: 200 };

    it('flags a growth beyond 10 %, not one at 10 %', () => {
        const comparison = compareMetrics({ boot_ms: 110.1, cold_boot_ms: 440, thumbnail_ms: 200 }, baseline);

        assert.deepEqual(comparison.regressions.map((finding) => finding.metric), ['boot_ms']);
    });

    it('reports an improvement without failing it', () => {
        const comparison = compareMetrics({ boot_ms: 80, cold_boot_ms: 400, thumbnail_ms: 200 }, baseline);

        assert.equal(comparison.regressions.length, 0);
        assert.deepEqual(comparison.improvements.map((finding) => finding.metric), ['boot_ms']);
    });

    it('names a metric the run lost and one it gained', () => {
        const comparison = compareMetrics({ boot_ms: 100, cold_boot_ms: 400, new_ms: 5 }, baseline);

        assert.deepEqual(comparison.missing, ['thumbnail_ms']);
        assert.deepEqual(comparison.added, ['new_ms']);
    });

    it('honours another tolerance', () => {
        assert.equal(compareMetrics({ boot_ms: 106 }, { boot_ms: 100 }, 0.05).regressions.length, 1);
    });

    it('never flags a baseline of zero', () => {
        assert.equal(compareMetrics({ boot_ms: 50 }, { boot_ms: 0 }).regressions.length, 0);
    });
});

describe('describeComparison', () => {
    it('writes one line per finding, regressions first', () => {
        const lines = describeComparison(compareMetrics({ boot_ms: 150, cold_boot_ms: 300, fresh_ms: 1 }, { boot_ms: 100, cold_boot_ms: 400, gone_ms: 9 }));

        assert.equal(lines.length, 4);
        assert.match(lines[0], /^REGRESSION boot_ms: 100 ms -> 150 ms \(\+50\.0 %, limit \+10 %\)/);
        assert.match(lines[1], /^MISSING gone_ms/);
        assert.match(lines[2], /^improved cold_boot_ms.*-25\.0 %/);
        assert.match(lines[3], /^new fresh_ms/);
    });
});

describe('judgeRun', () => {
    const run = (metrics: Record<string, number>, id = '1') => ({ measuredAt: '2026-11-18T03:30:00Z', run: id, metrics });

    it('passes the first night, with no baseline, and says the run becomes the reference', () => {
        const verdict = judgeRun(run({ boot_ms: 100 }), null);

        assert.equal(verdict.failed, false);
        assert.match(verdict.lines[0], /No baseline yet/);
        assert.ok(verdict.lines.includes('boot_ms: 100 ms'));
    });

    it('fails on a regression and names the baseline it compared with', () => {
        const verdict = judgeRun(run({ boot_ms: 120 }, '2'), run({ boot_ms: 100 }, '1'));

        assert.equal(verdict.failed, true);
        assert.match(verdict.lines[0], /run 1/);
    });

    it('fails when a metric disappeared: a lost measure is not a pass', () => {
        assert.equal(judgeRun(run({}), run({ boot_ms: 100 })).failed, true);
    });

    it('passes within the tolerance', () => {
        assert.equal(judgeRun(run({ boot_ms: 109 }), run({ boot_ms: 100 })).failed, false);
    });
});

describe('describeCalibration', () => {
    it('says nothing when the run has no calibration', () => {
        assert.deepEqual(describeCalibration(undefined, { cpu_ref_ms: 50 }), []);
    });

    it("prints this night's numbers alone when the reference night has none", () => {
        assert.deepEqual(describeCalibration({ cpu_ref_ms: 60 }, undefined), ['calibration cpu_ref_ms: 60 ms']);
        assert.deepEqual(describeCalibration({ cpu_ref_ms: 60, fs_ref_ms: 9 }, { cpu_ref_ms: 0 }), ['calibration cpu_ref_ms: 60 ms', 'calibration fs_ref_ms: 9 ms']);
    });

    it('compares each number with the reference night', () => {
        assert.deepEqual(describeCalibration({ cpu_ref_ms: 75, fs_ref_ms: 8 }, { cpu_ref_ms: 50, fs_ref_ms: 10 }), [
            'calibration cpu_ref_ms: 75 ms (reference night 50 ms, +50.0 %)',
            'calibration fs_ref_ms: 8 ms (reference night 10 ms, -20.0 %)',
        ]);
    });
});

describe('judgeRun and the calibration', () => {
    const report = (metrics: Record<string, number>, calibration?: Record<string, number>) => ({ measuredAt: '2026-11-18T03:30:00Z', run: '1', metrics, calibration });

    it('never fails on a calibration that moved: the machine is not the code', () => {
        const verdict = judgeRun(report({ boot_ms: 100 }, { cpu_ref_ms: 200 }), report({ boot_ms: 100 }, { cpu_ref_ms: 50 }));

        assert.equal(verdict.failed, false);
        assert.ok(verdict.lines.includes('calibration cpu_ref_ms: 200 ms (reference night 50 ms, +300.0 %)'));
    });

    it('still fails on a regression, calibration or not', () => {
        assert.equal(judgeRun(report({ boot_ms: 150 }, { cpu_ref_ms: 50 }), report({ boot_ms: 100 })).failed, true);
    });

    it('prints the calibration on the first night too', () => {
        assert.ok(judgeRun(report({ boot_ms: 100 }, { cpu_ref_ms: 50 }), null).lines.includes('calibration cpu_ref_ms: 50 ms'));
    });
});
