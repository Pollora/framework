# Changelog

All notable changes to the Pollora framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/Pollora/framework/compare/v13.4.0...develop)

## [v13.4.0](https://github.com/Pollora/framework/compare/v13.3.0...v13.4.0) - 2026-04-22

### Added
- Dynamic `theme.json` resolution via `wp_theme_json_data_theme` WordPress filter (`ThemeJsonResolver`)
  - Reads the Vite-built `theme.json` from `public/build/theme/{slug}/assets/theme.json` at runtime
  - Injects Tailwind-enriched settings (colors, fonts, border-radius) into WordPress without file copy
  - In-memory caching avoids repeated filesystem reads
  - Eliminates the `copy-theme-json` Vite plugin hack — source and built `theme.json` are no longer mixed
- Routing refactoring with dedicated `WordPressRoutingService` for cleaner separation of concerns
  - Simplified `ExtendedRouter` with extracted WordPress-specific routing logic
  - Configurable `cache-control` max-age via `wordpress.headers.cache_max_age`
  - Fixed route parameters shadowing WordPress globals in Blade loaders
- Gutenberg block registration system with Vite integration (`BlockRegistrar`, `BlockServiceProvider`)
  - Scans `resources/blocks/` directories and pre-registers script/style handles via ViteManager
  - Creates `{parent}.blocks` asset container (no basePath) for direct manifest resolution
  - Adds `type="module"` and `crossorigin` attributes for Vite-compiled scripts
  - Works identically for themes (`theme`), plugins (`plugin.{slug}`), and modules (`module.{slug}`)
- `pollora:make-block` Artisan command for scaffolding Gutenberg blocks
  - Generates block.json, index.jsx, edit.jsx, save.jsx, CSS, and view.js
  - Supports `--dynamic` (render.php), `--inner-blocks`, `--no-view-script` options
  - First-run bootstrap: creates `BlocksServiceProvider`, patches `vite.config.js`, adds npm dependencies
  - Publishable stubs via `--tag=pollora-block-stubs`
- JSX/TSX/TS support in `AssetEnqueuer::determineFileType()` (mapped to `js` type)
- [Blocks documentation](documentation/blocks.md) covering the full workflow
- Admin dashboard page under **Tools > Pollora** with Pollora branding and inline SVG logo
  - Framework version status (current vs latest, dev branch detection)
  - Environment info (PHP, Laravel, WordPress versions)
  - WordPress config (WP_DEBUG, multisite, permalink structure)
  - Discovered post types and taxonomies with labels, slugs, and class names
  - Discovered hooks count (actions and filters)
  - Discovered REST API routes, WP-CLI commands, and scheduled tasks
  - Auto-discovered service providers list
  - Laravel modules status (enabled/disabled via nwidart/laravel-modules)
  - Discovery cache state and performance stats (cache hits/misses, classes processed)
  - Active theme info (name, version, template)
  - Notification badge on menu item when a framework update is available (Site Health pattern)
- `php artisan pollora:status` CLI command with the same information
  - `--json` flag for machine-readable output (AI agents, CI pipelines)
- Update notifications for new Pollora versions ([#162](https://github.com/Pollora/framework/issues/162))
  - Dismissable admin notice when a newer version is available
  - Site Health debug information section (installed version, latest version, update status)
  - Site Health status test reporting whether Pollora is up to date
  - Version data fetched from Packagist API v2, cached via WordPress transients (12h)
- Brain Monkey (`brain/monkey`) for structured WordPress function mocking in tests
- Type coverage check in CI (`pest --type-coverage --min=98`)
- Testbench integration tests for `DiscoveryServiceProvider`, `HookServiceProvider`, and `VersionCheckServiceProvider`
- Text domain `pollora` for all framework UI strings (AdminNotice, SiteHealthCheck, auto-generated labels)
- `#[Labels]` sub-attribute now accepts named parameters for partial label overrides with extractible `__()` calls
- `load_textdomain('pollora')` in `PolloraServiceProvider::boot()` with user override via `wp-content/languages/pollora/`
- Generated `pollora.pot` translation template with all framework strings
- Translations: French (fr_FR), Spanish (es_ES), German (de_DE), Portuguese Brazil (pt_BR), Italian (it_IT), Dutch (nl_NL), Japanese (ja)

### Fixed
- PHP 8.2+ deprecation warning for dynamic property creation on `Spatie\StructureDiscoverer\Data\DiscoveredClass` in `DiscoveryEngine` — replaced dynamic `$structure->location` assignment with an associative array pairing structures with their discovery locations
- WordPress `shutdown` hook output now reaches the browser
  - Plugins relying on `shutdown` (Query Monitor toolbar, WP Rocket cache processing) were broken because Laravel's `Response::send()` calls `fastcgi_finish_request()` before PHP shutdown
  - `WordPressShutdown` middleware now fires `do_action('shutdown')` within a controlled output buffer before the response is returned, injecting captured output (e.g. QM toolbar) before `</body>` in HTML responses
  - Prevents double execution by clearing shutdown callbacks after firing; `wp_cache_close()` remains unaffected
  - Exception-safe buffer management following Laravel's `PhpEngine` pattern

### Changed
- Renamed `src/Taxonomy/config/post-types.php` to `taxonomies.php` (fixes inconsistent naming)
- Added comprehensive PHPDoc to `WordPressShutdown` and `WordPressHeaders` middlewares

### Removed
- Config-based post type and taxonomy registration (`config/post-types.php`, `config/taxonomies.php`) — use `#[PostType]` / `#[Taxonomy]` attributes instead
- Replaced `'textdomain'` placeholder with `sprintf(__('Edit %s', 'pollora'), $singular)` pattern for extractible i18n
- Replaced manual WordPress mock system with Brain Monkey (`tests/Unit/helpers.php`: 1302 → 416 lines)
- Tests now use `Brain\Monkey\Functions\when()` / `stubs()` instead of `WP::$wpFunctions` + global function stubs
- Migrated all 24 PHPUnit-style test files to Pest closure format (~1000 lines removed)
- Enabled Rector on `tests/` directory (previously excluded)
- Excluded `helpers.php` from Rector to prevent mock logic corruption

## [v13.3.0](https://github.com/Pollora/framework/compare/v13.2.0...v13.3.0) - 2026-04-22

### Added
- SECURITY.md security policy with GitHub Security Advisories reporting
- Larastan (PHPStan for Laravel) and Orchestra Testbench for improved static analysis and testing
- PHPUnit XML configuration (`phpunit.xml.dist`)
- Real integration tests for ThemeRegistrar (replacing skipped tests)
- Consolidated CI workflow (`ci.yml`) with tests, code-quality, coverage-upload, and changelog validation jobs
- Composer cache in CI workflows for faster builds
- `composer audit` in code-quality CI job
- Security scan workflow (`security.yml`) with weekly schedule
- Deploy workflow now extracts changelog body for GitHub releases

### Changed
- Replaced all `dev-main` dependencies with stable version constraints (`pollora/helper-overrider ^1.0`, `pollora/entity ^1.2`, `pollora/query ^1.0`, `spatie/php-structure-discoverer ^2.4`, `laravel/prompts ^0.3.17`, `wp-cli/wp-cli ^2.12`)
- Removed `minimum-stability: dev` (no longer needed)
- Configured `driftingly/rector-laravel` with Laravel-specific rule sets (UP_TO_LARAVEL_130, code quality, collections, type declarations)
- Applied Rector Laravel refactoring: `app()` → `resolve()`, collection filter/reject improvements
- Replaced deprecated `strictBooleans` Rector set with `codingStyle`

### Fixed
- Patched Mockery 1.x for PHP 8.4 implicit nullable parameter deprecations
- Fixed npm vulnerabilities (picomatch, ajv, yaml)
- Fixed Pint code style issues

## [v13.2.0](https://github.com/Pollora/framework/compare/v13.1.0...v13.2.0) - 2026-04-22

### Added
- `pollora:make-theme` now removes `bin/` directory from generated themes (dev-only files)
- CHANGELOG.md and versioning guidelines in CLAUDE.md and CONTRIBUTING.md
- Documentation reference (online links) in CLAUDE.md

### Fixed
- Rewrite rules now flush correctly after permalink setup during installation
- `ThemeMetadata::getThemeNamespace()` now returns `Theme\{Name}` instead of just `{Name}`, consistent with autoloader conventions

### Changed
- Upgraded Illuminate dependencies to `^13.5`
- Upgraded laravel/pint to `^1.29.1`

### Removed
- **BREAKING**: `Loop` facade and `Pollora\View\Loop` class — use [Sage Directives](https://log1x.github.io/sage-directives-docs/) (`@title`, `@content`, `@excerpt`, `@permalink`, `@published`) instead

## [v13.1.0](https://github.com/Pollora/framework/compare/v13.0.0...v13.1.0) - 2026-04-20

### Added
- CLI options for `pollora:install` command (`--title`, `--description`, `--admin-user`, `--admin-email`, `--admin-password`, `--locale`, `--public`) for non-interactive installation
- Updated documentation submodule with CLI options reference

### Changed
- `InstallationConfig::fromPrompts()` now accepts optional parameters to bypass interactive prompts
- Renamed `wp:env-setup` → `pollora:env-setup` and `wp:install` → `pollora:install` in documentation

## [v13.0.0](https://github.com/Pollora/framework/compare/v12.0.0...v13.0.0) - 2026-04-20

### Changed
- **BREAKING**: Target Laravel 13 exclusively (`illuminate/* ^13.0`)
- Upgraded all Illuminate dependencies to `^13.0`

## [v12.0.0](https://github.com/Pollora/framework/releases/tag/v12.0.0)

Previous stable release targeting Laravel 12.