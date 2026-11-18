# Browser tests

Playwright tests run against a real Pollora site (see `playwright.config.ts` for the variables:
`E2E_HOME_URL`, `WP_BASE_URL`, `E2E_WP_CLI`, `E2E_BROWSERS`). CI builds the site with DDEV; the
`e2e` job of `.github/workflows/ci.yml` is the reference run.

## Performance gates

Two rules of the quality harness are enforced here.

### PERF-01: a query budget per type of page

`specs/query-budget.spec.ts` asks seven pages (home, post, page, archive, 404, search, REST) for
their own cost and fails when one runs more SQL queries than `budgets.json` allows, in WordPress
(`$wpdb->num_queries`) or in Laravel (`QueryExecuted`). The margin is zero: counts are deterministic.
Fewer queries than the budget passes; lower the budget with the update mode so the gain is kept.

**How the numbers are read.** The framework answers a request carrying `X-Pollora-Diagnostics: 1`
with a report (`wordpress_queries`, `laravel_queries`, `boot_ms`, `elapsed_ms`): a response header
on REST, a trailing `<!-- pollora:diagnostics {...} -->` comment on an HTML page. It does so only
when `WP_DEBUG` is on **and** the request sends that header (`Pollora\Foundation\Support\RequestDiagnostics`);
a production site registers nothing. It does not depend on the debug bar.

**References.** `budgets.json` holds the counts of the CI site (a fresh install plus the fixtures of
this suite); another site has other counts. A page whose budget is `null` is measured and reported,
never failed. Every run writes `test-results/budgets.measured.json`, which CI uploads as the
`e2e-budgets-measured-*` artifact.

```sh
npm run budgets:update       # measures, then writes the counts into budgets.json
```

Run it against the CI-like site, or take `budgets.measured.json` from a green CI run, and commit.
The pages are `/?p=1`, `/?page_id=2` and `/?cat=1`: the content WordPress creates on install.

### PERF-07: boot, cold discovery and thumbnail, every night

`specs/perf-metrics.spec.ts` (skipped unless `E2E_PERF=1`) writes `perf-metrics.json`: `boot_ms`
(warm, to `wp_loaded`), `cold_boot_ms` (first request after `discovery:clear`) and `thumbnail_ms`
(every size of one 2400x1600 JPEG), each the median of several runs. On the nightly run, the job
compares them with the last night that passed (`bin/perf-compare.ts`, reference kept in the
Actions cache) and fails past +10 %. A failing night does not replace the reference. To accept a
slowdown, delete the `perf07-reference-*` cache entries.

```sh
E2E_PERF=1 npx playwright test perf-metrics
node bin/perf-compare.ts current.json reference.json --tolerance=0.1
```

### Unit tests

The comparison rules are in `support/perf.ts` and tested without a site or a browser:

```sh
npm run test:unit
```
