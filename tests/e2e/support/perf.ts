/**
 * Pure helpers of the performance gates (rules PERF-01 and PERF-07 of the harness).
 *
 * Nothing here touches the site, the network or the disk: the specs and the nightly
 * script feed these functions what they measured, so every decision (what a report
 * says, when a number is a regression) can be tested without a browser or WordPress.
 */

/** What the framework reports about one request (see `RequestDiagnostics` in the framework). */
export type Diagnostics = {
    wordpress_queries: number;
    laravel_queries: number;
    boot_ms: number | null;
    elapsed_ms: number;
};

/** The request header that asks for the report, and the response header that carries it on REST. */
export const DIAGNOSTICS_HEADER = 'X-Pollora-Diagnostics';

/** Absorbs floating point noise, so a change of exactly the tolerance is not a regression. */
const EPSILON = 1e-9;

const COMMENT = /<!-- pollora:diagnostics (\{.*?\}) -->/s;

function isDiagnostics(value: unknown): value is Diagnostics {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const report = value as Record<string, unknown>;

    return Number.isInteger(report.wordpress_queries)
        && Number.isInteger(report.laravel_queries)
        && (report.boot_ms === null || typeof report.boot_ms === 'number')
        && typeof report.elapsed_ms === 'number';
}

/**
 * Read the report out of a response: the header (REST) or, for an HTML page, the trailing
 * comment. The last comment wins: a theme that printed its own would come first.
 *
 * @returns the report, or null when the response carries none (WP_DEBUG off, an older framework)
 */
export function parseDiagnostics(header: string | undefined, body: string): Diagnostics | null {
    const candidates: string[] = [];

    if (header) {
        candidates.push(header);
    }

    const comments = [...body.matchAll(new RegExp(COMMENT, 'gs'))];

    if (comments.length > 0) {
        candidates.push(comments[comments.length - 1][1]);
    }

    for (const candidate of candidates) {
        try {
            const parsed: unknown = JSON.parse(candidate);

            if (isDiagnostics(parsed)) {
                return parsed;
            }
        } catch {
            // Not JSON: try the next source.
        }
    }

    return null;
}

/** A query count, per layer. `null` means "not measured yet". */
export type Budget = { wordpress: number | null; laravel: number | null };

export type Budgets = { pages: Record<string, Budget> };

export type Counts = { wordpress: number; laravel: number };

/**
 * The count of a page: the lowest of several samples.
 *
 * Counts are deterministic, so the samples agree; when they do not, the extra queries
 * come from something that is not the page (a transient expiring, WP-Cron spawning), and
 * the lowest sample is the page's own cost. A regression raises every sample.
 */
export function lowestCounts(samples: Diagnostics[]): Counts {
    if (samples.length === 0) {
        throw new Error('lowestCounts: no sample');
    }

    return {
        wordpress: Math.min(...samples.map((sample) => sample.wordpress_queries)),
        laravel: Math.min(...samples.map((sample) => sample.laravel_queries)),
    };
}

export type BudgetFinding = { page: string; layer: keyof Counts; budget: number; measured: number };

/**
 * Where a page exceeds its budget. The margin is zero: the count is deterministic, and a
 * margin would let queries creep in unseen. Fewer queries than budgeted is not a finding
 * (it is good news: lower the budget with the update mode).
 *
 * A budget of `null` is not measured yet and never fails; `unmeasured()` lists those.
 */
export function overBudget(page: string, budget: Budget, measured: Counts): BudgetFinding[] {
    const findings: BudgetFinding[] = [];

    for (const layer of ['wordpress', 'laravel'] as const) {
        const limit = budget[layer];

        if (limit !== null && measured[layer] > limit) {
            findings.push({ page, layer, budget: limit, measured: measured[layer] });
        }
    }

    return findings;
}

/** Whether a page has any layer without a budget yet. */
export function unmeasured(budget: Budget | undefined): boolean {
    return budget === undefined || budget.wordpress === null || budget.laravel === null;
}

/**
 * The budgets file with the measured counts written in: what the update mode saves.
 * Pages that were not measured keep what they had.
 */
export function withMeasured(budgets: Budgets, measured: Record<string, Counts>): Budgets {
    const pages: Record<string, Budget> = { ...budgets.pages };

    for (const [page, counts] of Object.entries(measured)) {
        pages[page] = { wordpress: counts.wordpress, laravel: counts.laravel };
    }

    return { ...budgets, pages };
}

/** Median of a non-empty list: the middle value, or the mean of the two middle ones. */
export function median(values: number[]): number {
    if (values.length === 0) {
        throw new Error('median: no value');
    }

    const sorted = [...values].sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);

    return sorted.length % 2 === 1 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
}

/** The nightly measures, in milliseconds. Each is the median of several runs. */
export type Metrics = Record<string, number>;

/** What one nightly run writes: the metrics and what they were measured on. */
export type MetricsReport = { measuredAt: string; run: string; metrics: Metrics };

export type MetricFinding = { metric: string; baseline: number; current: number; change: number };

export type Comparison = {
    /** Metrics that grew by more than the tolerance */
    regressions: MetricFinding[];
    /** Metrics that shrank by more than the tolerance: worth knowing, never a failure */
    improvements: MetricFinding[];
    /** Metrics in the baseline that the current run did not produce: a measure was lost */
    missing: string[];
    /** Metrics the baseline does not know yet */
    added: string[];
};

/**
 * Compare a run with the baseline. A metric regresses when it grows by more than
 * `tolerance` (0.10 = 10 %) of its baseline. The comparison is on the metric's own scale,
 * so a baseline of 0 never regresses (there is nothing to take a percentage of).
 */
export function compareMetrics(current: Metrics, baseline: Metrics, tolerance = 0.1): Comparison {
    const result: Comparison = { regressions: [], improvements: [], missing: [], added: [] };

    for (const [metric, before] of Object.entries(baseline)) {
        const after = current[metric];

        if (after === undefined) {
            result.missing.push(metric);
            continue;
        }

        if (before <= 0) {
            continue;
        }

        const change = (after - before) / before;
        const finding = { metric, baseline: before, current: after, change };

        if (change > tolerance + EPSILON) {
            result.regressions.push(finding);
        } else if (change < -tolerance - EPSILON) {
            result.improvements.push(finding);
        }
    }

    result.added = Object.keys(current).filter((metric) => ! (metric in baseline));

    return result;
}

/** A comparison as the lines a CI log shows. */
export function describeComparison(comparison: Comparison, tolerance = 0.1): string[] {
    const percent = (change: number): string => `${change >= 0 ? '+' : ''}${(change * 100).toFixed(1)} %`;
    const lines: string[] = [];

    for (const finding of comparison.regressions) {
        lines.push(`REGRESSION ${finding.metric}: ${finding.baseline} ms -> ${finding.current} ms (${percent(finding.change)}, limit +${(tolerance * 100).toFixed(0)} %)`);
    }

    for (const metric of comparison.missing) {
        lines.push(`MISSING ${metric}: in the baseline, not measured by this run`);
    }

    for (const finding of comparison.improvements) {
        lines.push(`improved ${finding.metric}: ${finding.baseline} ms -> ${finding.current} ms (${percent(finding.change)})`);
    }

    for (const metric of comparison.added) {
        lines.push(`new ${metric}: no baseline yet`);
    }

    return lines;
}

/** The verdict of a nightly run: what to print and whether the job fails. */
export type Verdict = { failed: boolean; lines: string[] };

/**
 * Judge a run against its baseline.
 *
 * Without a baseline (the first night, or an expired cache) there is nothing to compare:
 * the run passes and says so, and its numbers become the baseline. A regression, or a
 * metric the run no longer produces, fails.
 */
export function judgeRun(current: MetricsReport, baseline: MetricsReport | null, tolerance = 0.1): Verdict {
    if (baseline === null) {
        return { failed: false, lines: ['No baseline yet: this run becomes the reference.', ...Object.entries(current.metrics).map(([metric, value]) => `${metric}: ${value} ms`)] };
    }

    const comparison = compareMetrics(current.metrics, baseline.metrics, tolerance);
    const lines = [`Baseline of ${baseline.measuredAt} (run ${baseline.run})`, ...describeComparison(comparison, tolerance)];

    return { failed: comparison.regressions.length > 0 || comparison.missing.length > 0, lines };
}
