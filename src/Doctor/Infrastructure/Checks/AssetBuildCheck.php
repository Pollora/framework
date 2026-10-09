<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Illuminate\Support\Facades\Http;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ProjectModule;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Throwable;

/**
 * A theme is installed, and every theme, plugin and module built with Vite has its
 * assets where Pollora reads them.
 *
 * Each failure renders the page unstyled without an error: no manifest and no dev
 * server leaves every asset URL empty; a hot file left behind points every asset
 * at a dev server that is not running; a build written to another folder than
 * Pollora reads — build/plugins/ for build/plugin/, once — is never found.
 */
final readonly class AssetBuildCheck implements CheckInterface
{
    /**
     * @param  (Closure(string $url): ?int)|null  $probe  the HTTP status for Vite's client at this URL, null when nothing answers; a request when null
     */
    public function __construct(
        private ThemeAvailability $availability,
        private ProjectModules $modules,
        private ?Closure $probe = null,
    ) {}

    public function id(): string
    {
        return 'asset-builds';
    }

    public function label(): string
    {
        return 'Theme, plugin and module builds';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if ($this->availability->isMissing()) {
            return CheckResult::error('No theme is installed: the front end cannot render.', [], 'php artisan pollora:make:theme my-theme');
        }

        $problems = [];
        $fixes = [];
        $built = 0;

        foreach ($this->modules->all() as $module) {
            if (! is_file($module->root.'/vite.config.js')) {
                continue;
            }

            $problem = $this->problem($module);

            if ($problem === null) {
                $built++;

                continue;
            }

            [$problems[], $fixes[]] = $problem;
        }

        if ($problems !== []) {
            return CheckResult::error(
                sprintf('%d build(s) cannot be served: their assets do not load.', count($problems)),
                $problems,
                implode(' ; ', array_unique($fixes)),
            );
        }

        return CheckResult::ok(sprintf('%d Vite build(s) where Pollora reads them.', $built));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function problem(ProjectModule $module): ?array
    {
        if (is_file($module->hotFile)) {
            $url = rtrim(trim((string) file_get_contents($module->hotFile)), '/');

            $status = $url === '' ? null : $this->probe($url);

            if ($status !== null && $status >= 200 && $status < 300) {
                return null;
            }

            // A proxy in front of a stopped dev server answers 502-504: that is "not running" too.
            if ($status === null || in_array($status, [502, 503, 504], true)) {
                return [
                    sprintf('%s: its hot file points to %s, where no Vite dev server answers', $module->label(), $url),
                    sprintf('cd %s && npm run dev, or delete %s', $module->relativeRoot(), $this->relative($module->hotFile)),
                ];
            }

            return [
                sprintf('%s: its hot file points to %s, which answers %d for Vite\'s client: what answers there is not the dev server — it is stopped, or its port is not exposed', $module->label(), $url, $status),
                sprintf('cd %s && npm run dev, with its port exposed to the browser (DDEV: web_extra_exposed_ports, container_port 5173); or delete %s', $module->relativeRoot(), $this->relative($module->hotFile)),
            ];
        }

        if (is_file($module->manifest())) {
            return null;
        }

        if ($module->type === 'module' && $this->isStockModuleConfig($module)) {
            return [
                sprintf("%s: its vite.config.js is the one nwidart/laravel-modules' module:make writes, which builds into public/build-%s, where Pollora never looks", $module->label(), strtolower(basename($module->root))),
                sprintf("php artisan pollora:module:frontend %s (replaces package.json and vite.config.js with the module template's, keeping a backup), then cd %s && npm install && npm run build", basename($module->root), $module->relativeRoot()),
            ];
        }

        $elsewhere = $this->buildElsewhere($module);

        if ($elsewhere !== null) {
            return [
                sprintf('%s: built into %s, but Pollora reads %s', $module->label(), $elsewhere, $this->relative(dirname($module->manifest()))),
                sprintf('in %s/vite.config.js, build into public/%s', $module->relativeRoot(), trim($module->buildDirectory, '/')),
            ];
        }

        return [
            sprintf('%s: not built — no manifest at %s', $module->label(), $this->relative($module->manifest())),
            sprintf('cd %s && npm install && npm run build', $module->relativeRoot()),
        ];
    }

    /**
     * The Vite config nwidart's stub writes: `buildDirectory: 'build-<lower>'`.
     */
    private function isStockModuleConfig(ProjectModule $module): bool
    {
        $config = (string) @file_get_contents($module->root.'/vite.config.js');

        return preg_match('/buildDirectory\s*:\s*[\'"]build-/', $config) === 1;
    }

    /** A manifest for this module under another public/build folder, relative to the project. */
    private function buildElsewhere(ProjectModule $module): ?string
    {
        $candidates = glob(public_path('build/*/'.basename(dirname($module->manifest())).'/manifest.json'), GLOB_NOSORT) ?: [];
        $candidates = [...$candidates, ...(glob(public_path('build/*/'.basename($module->root).'/manifest.json'), GLOB_NOSORT) ?: [])];

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== $module->manifest()) {
                return $this->relative(dirname($candidate));
            }
        }

        return null;
    }

    /**
     * The status Vite's client answers at this URL, as the page will request it; null when nothing answers.
     *
     * A TCP connection proves nothing: DDEV's router listens on the dev server's port
     * itself. It answers 502 when Vite is stopped, 404 when the port is not exposed.
     */
    private function probe(string $url): ?int
    {
        if ($this->probe instanceof Closure) {
            return ($this->probe)($url);
        }

        try {
            return Http::withoutVerifying()->timeout(2)->get($url.'/@vite/client')->status();
        } catch (Throwable) {
            return null;
        }
    }

    private function relative(string $path): string
    {
        $base = rtrim(base_path(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
