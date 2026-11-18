/**
 * Compare a nightly run (PERF-07) with the last good one.
 *
 *   node bin/perf-compare.ts <current.json> [<baseline.json>] [--tolerance=0.1]
 *
 * Exits 1 on a regression beyond the tolerance or a lost metric, 0 otherwise, also when the
 * baseline file is absent (the first night). The decision is `judgeRun()`, tested in
 * support/perf.test.ts; this file only reads the files and prints.
 */
import { existsSync, readFileSync } from 'node:fs';
import { judgeRun, type MetricsReport } from '../support/perf.ts';

const args = process.argv.slice(2);
const files = args.filter((arg) => ! arg.startsWith('--'));
const tolerance = Number(args.find((arg) => arg.startsWith('--tolerance='))?.split('=')[1] ?? '0.1');

if (files.length < 1 || ! Number.isFinite(tolerance) || tolerance < 0) {
    console.error('Usage: node bin/perf-compare.ts <current.json> [<baseline.json>] [--tolerance=0.1]');
    process.exit(2);
}

const read = (path: string): MetricsReport => JSON.parse(readFileSync(path, 'utf8')) as MetricsReport;
const baseline = files[1] !== undefined && existsSync(files[1]) ? read(files[1]) : null;
const verdict = judgeRun(read(files[0]), baseline, tolerance);

for (const line of verdict.lines) {
    console.log(line);
}

process.exit(verdict.failed ? 1 : 0);
