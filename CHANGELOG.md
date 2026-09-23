# Changelog

All notable changes to the Pollora framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/Pollora/framework/compare/v13.32.0-beta.6...develop)

### Fixed
- `composer install` and `composer update` no longer fail on a Pollora site that has no terminal. `pollora:env:setup` runs from composer's `post-autoload-dump` hook, so it fires inside container builds, deployments and CI; when the database was not reachable it reached for Laravel Prompts, which throws where there is nothing to prompt, and composer exited 1 reporting a prompting problem rather than a database one. Nothing in that command is a gate — `pollora:install` is what refuses to continue without a database — so with no terminal it now names what is wrong, names the command to run once the database is up, and lets composer finish

### Changed
- The commit-hook tooling is declared where it belongs. `@commitlint/cli`, `@commitlint/config-conventional`, `husky`, `lint-staged` and `prettier` sat under `dependencies` rather than `devDependencies`, so every advisory in their tree was reported against this repository at **runtime** scope — ten high-severity alerts describing packages no Pollora site has ever loaded. Nothing installs differently: `npm ci` installs devDependencies by default, and the commit hook was checked after the move
- Every open npm advisory clears: `fast-uri` moves to 3.1.8 and `js-yaml` to 4.3.2, past the 3.1.6 and 4.3.2 that patch them. `npm audit` goes from 2 high-severity vulnerabilities to **0**
- The contribution guide documents the pull request title convention. CI validates the title of every pull request against the conventional commit format, and rejected one said only that the check had failed — a rule enforced on a first contribution and published nowhere. The allowed types are listed, along with the fact that **Validate Changelog** only runs on release pull requests into `main` and never concerns a contribution to `develop`

## [v13.32.0-beta.6](https://github.com/Pollora/framework/compare/v13.32.0-beta.5...v13.32.0-beta.6) - 2026-09-22

### Fixed
- `get_theme_file_uri()` answers the URL the build gave a theme file, instead of an empty string. It returned `''` for every path there is — measured on a live site, on the front end as much as on wp-login.php. WordPress hands the `theme_file_uri` filter both the URL it built and the file it was asked for; the file was thrown away and rebuilt by subtracting `get_stylesheet_directory_uri()` from the URL, then handed to the asset resolver, which prefixes the container's own root a second time. `resources/assets/app.js` was looked up as `resources/assets/resources/assets/app.js`, never found, and the failure was logged and swallowed — one line in `laravel.log` per call. Both spellings now resolve: the path from the theme root a caller would write, and the path relative to the container root the manifest is keyed on. A theme's own directory is not web-served on a Pollora project — measured, `/themes/…` and `/content/themes/…` both 404 while `/build/theme/…` serves — so the build is the only address a theme file has. Anything the build does not know about is handed back untouched rather than emptied
- The server's filesystem path no longer reaches the public HTML. `get_theme_root_uri()` can only turn a theme root into a URL when it sits under `WP_CONTENT_DIR`; a Pollora project registers its themes at the project root, and for a root it cannot map WordPress returns the path verbatim — so `get_stylesheet_directory_uri()` answered `/var/www/html/themes/apiary`, and WordPress's speculative-loading rules printed it into the `<head>` of every front-end page. Measured: one occurrence per page before, zero after. Only an answer that is not a URL is replaced, so a project keeping its themes under content is untouched

## [v13.32.0-beta.5](https://github.com/Pollora/framework/compare/v13.32.0-beta.4...v13.32.0-beta.5) - 2026-09-22

### Added
- The login screen wears the theme's design, from the theme's own theme.json. WordPress loads the theme on `wp-login.php` but emits none of its design there — measured: zero occurrences of `wp--preset--color` in the HTML of a login screen — so every site logs in through the same grey form whatever it looks like elsewhere. A theme that ships a `config/login.php` now gets its colours, radii and typography printed on `login_head`, its own logo above the form, and its home page behind that logo instead of wordpress.org. Nothing is duplicated: the design stays in theme.json, and restyling a theme restyles its login screen. Sign-in, lost password, reset, register and the confirm-admin-email prompt are all covered, being one screen as far as `login_head` is concerned. Strictly opt-in — a theme with no `config/login.php` gets WordPress's screen, byte for byte, so upgrading the framework never changes the page people sign in through. See [Theming → Login screen](documentation/theming.md#login-screen)
- `pollora/login/palette`, `pollora/login/logo`, `pollora/login/styles` and `pollora/login/credit` filters, for a module or a plugin to take over any part of the login screen without touching the theme
- Discovery says so when one location takes more than 250ms to scan, naming the location, the time and how many structures it found. The cost above was absorbed in complete silence for as long as it existed — no log line, no notice, nothing that measured it. Debug mode only: with the cache on, the cost is paid once

### Changed
- A theme's `config/login.php` is loaded on `init`, like `menus.php`, `sidebars.php` and `templates.php`, so `__()` works in it. WordPress fires `init` through `wp-load.php` before `wp-login.php` fires `login_init`, so the config is in place well before anything reads it

### Fixed
- Attribute discovery no longer walks a plugin's `node_modules`. Themes and modules have always been scanned at `app/`, falling back to `src/` — the two directories the autoloader maps `Plugin\{Name}\` onto — while plugins were scanned at their root, which is where `node_modules/` lands as soon as a plugin has a Vite build. Symfony's Finder enumerates every file before filtering by extension, so the walk costs everything and the extension check costs nothing: measured on a demo plugin with a block build, 69,741 files walked on every request to reach the three PHP files the plugin owns — 1,691 ms against 1 ms for the same directory without `node_modules`. It also discovered five WordPress core classes shipped inside `@wordpress/style-engine` and handed them to the discovery pipeline, which is its own kind of wrong. With `APP_DEBUG` on, discovery keeps no cache, so a site paid this on every request: the home page of a site with one such plugin went from 3.0s to 0.78s, and from 691MB of peak memory to 37MB under Blackfire. A plugin shipping neither `app/` nor `src/` is still scanned at its root, so one keeping its classes at the top level is unaffected

## [v13.32.0-beta.4](https://github.com/Pollora/framework/compare/v13.32.0-beta.3...v13.32.0-beta.4) - 2026-09-22

### Added
- Every page says which template answered it, as an HTML comment on `wp_head`, whenever `WP_DEBUG` is on. WordPress offers no way to see which template rendered a request short of reading the code and guessing, and a Blade hierarchy on top makes the guess harder — several files could plausibly have answered, and which one did is the whole question when a page comes back wrong. Themes had started marking their own views by hand, one at a time, and drifting: theme-apiary marks most of its templates and not its front page. This is derived from the template the request actually resolved to, so it cannot be forgotten, and it reaches every theme without one of its own. A comment changes no markup and no styling; nothing at all is emitted in production, and the path is relative to the project so it never says where the site lives on disk

### Fixed
- A failed starter download says what happened instead of raising a TypeError. `make:theme` and `make:plugin` fetch their template from GitHub and, when that fails, fall back to copying one bundled with the framework — except none is bundled: neither `src/Theme/stubs/` nor `src/Plugin/stubs/` exists. `getTemplatePath()` declared `string` and returned `realpath()`'s `false`, so a transient network failure surfaced as `getTemplatePath(): Return value must be of type string, false returned`, naming a method the user has never heard of and saying nothing about the download. Measured on CI the first time GitHub did not serve an archive. Both commands now fail with a sentence that names the cause
- A REST controller declaring `WP_REST_Request $request` receives it. Handler arguments were built from the route parameters alone, so that type hint had `get_param('request')` looked up — no route declares a parameter by that name — and the method was invoked with `null`, fatalling on a TypeError before a line of it ran. Every endpoint whose handler asked for the request answered 500. A parameter type-hinted `WP_REST_Request` now gets the request; everything else still comes from the route, and builtin types are left alone since they name route values
- WordPress's own entry points answer their real status again. `wp-login.php`, `wp-links-opml.php` and the rest are run directly by PHP, with Pollora loaded along the way through `wp-config.php`; it resolved the URL as a content request anyway, and `/cms/wp-login.php` matches the attachment rewrite rule `[^/]+/([^/]+)/?$`. No such attachment exists, so WordPress sent a 404 header — and then rendered a perfectly good login form underneath it. The query now runs only when Laravel's front controller is the script being executed, which covers any entry point WordPress or a project adds later without naming it. `pollora_loaded` still fires either way
- `/cms/` no longer answers with the source of a Blade template. The WordPress installation root is served by WordPress's own `index.php`, whose `template-loader.php` `include`s whatever the template hierarchy hands it — and Pollora puts Blade sources in that hierarchy, since it renders them through Laravel. PHP printed one verbatim, directives and all. A front-end request that reaches that loader has bypassed Laravel and was looking for the site, so it is redirected to the home URL
- `make:theme` and `make:plugin` download the highest version of a starter, not whichever tag GitHub listed first. That order follows what was pushed most recently, so a fix tagged on an older line — 1.2.1 published after 1.4.0 — would have been handed to everyone scaffolding, a downgrade with nothing to notice it by. Tags are now compared as versions, pre-releases lose to any stable version so tagging a beta does not push it onto people who asked for nothing in particular, and a tag that is not a version at all is left out of the comparison rather than winning it. A repository whose tags follow some other scheme keeps its previous behaviour. The request also asks for 100 tags instead of the default 30, which could leave the highest version on an unread second page

## [v13.32.0-beta.3](https://github.com/Pollora/framework/compare/v13.32.0-beta.2...v13.32.0-beta.3) - 2026-09-18

### Fixed
- The block editor now shows the theme's own styles, so a block looks in the back office much like it does on the front. Both default themes declare `editor-styles` support, but that support only tells WordPress how to treat the styles it is handed — it loads none, and nothing named any: theme assets are registered with `toFrontend()`, leaving the editor with theme.json's variables and none of the rules built from them. The theme's built stylesheets are read from its Vite manifest and passed to `add_editor_style()`, which is what reaches inside the editor iframe; enqueueing on `enqueue_block_editor_assets` lands in the admin page around it instead. Any theme declaring the support gets this, with no change of its own
- Multisite is usable again. `Bootstrap::rewriteNetworkUrl()` is hooked to the `network_site_url` filter, which WordPress applies with a `$scheme` of `null` whenever no explicit scheme is requested — the common case — while the method declared a non-nullable `string $scheme`. Every request fatalled as soon as `MULTISITE` was enabled, the admin included. `$path` and `$scheme` now carry defaults matching the filter's own signature; single-site installs never reach this path ([#296](https://github.com/Pollora/framework/pull/296))
- `pollora:install` no longer leaves a site whose articles answer 404. It set the permalink structure and flushed, but the rule set WordPress can build mid-install is incomplete — taxonomies and post types are not all registered at that point — so flushing stored exactly that, and every single-post URL 404'd until something flushed again later. The option is dropped instead, letting WordPress rebuild it on the first request. This is the same reasoning `completeWebInstall()` already applied to the WordPress web installer; that hook is registered only outside the console, so the command-line install never went through it. Measured on a fresh install: 404 on an article before, 200 after
- The missing-theme guidance page now reaches the case it was written for. It was wired only onto the exception handler, catching the `View [home] not found` that `view()` used to throw; since the skeleton stopped declaring WordPress routes the template hierarchy decides, nothing calls `view()`, nothing throws, and a site with no theme answered a bare 404 instead — the crash the page exists to replace, wearing a different status code. The request is now taken over on `template_redirect`, which WordPress fires before any template is chosen and which `runWp()` reaches before Laravel routes, alongside the existing handling for robots, favicons, feeds and trackbacks. The exception path stays for anything still routing through `view()`, and its registration no longer sits behind a guard that could skip the hook. The admin, AJAX and REST keep their normal responses: the admin is where the user goes to fix this

## [v13.32.0-beta.2](https://github.com/Pollora/framework/compare/v13.32.0-beta...v13.32.0-beta.2) - 2026-09-17

### Added
- [pollora.dev](https://pollora.dev) as the project website and documentation: `homepage` and `support.docs` in `composer.json` (shown on Packagist), `homepage` in `package.json`, the README, and a Documentation link in the Pollora admin dashboard header
- Gutenberg blocks render with Blade: a `block.json` `"render": "file:./render.blade.php"` renders through the view factory with `$attributes`, `$content` and `$block`, Blade components included. `pollora:make:block` now creates dynamic Blade blocks by default — their markup is not stored in `post_content`, so changing it never invalidates existing content — and `--static` creates a block with `save.jsx`
- `BlockRegistrar::registerDirectory()` and `registerBlock()` accept an optional `basePath`, the Vite project root the asset entry points are resolved from; it defaults to the closest directory holding a `vite.config` file, or else the one containing `resources/`

### Changed
- Blocks live in `resources/views/blocks/{slug}` instead of `resources/blocks/{slug}`, next to the Blade views, for `pollora:make:block`, its `BlocksServiceProvider` stub and the Vite entries it generates. See [Migrating from `resources/blocks`](documentation/blocks.md#migrating-from-resourcesblocks)
- `pollora:make:block` limits Vite full reloads under `resources/views` to Blade files: laravel-vite-plugin's `refreshPaths` and a `resources/views/**` glob would reload the page on every block JSX change instead of hot-replacing it
- **BREAKING** for custom `BlockRegistrarInterface` implementations: both methods gained the optional `?string $basePath = null` parameter
- Relicensed under MIT, like the other Pollora packages: `composer.json` and the README declared `GPL-2.0-or-later`, and the only license files were the 0BSD/WTFPL texts inherited from the original WordPress integration by Jordan Doyle, now credited in the README
- Development against WordPress 7.1 stubs (`php-stubs/wordpress-stubs` `^7.1`); `patches/wordpress-stubs.patch` regenerated for them and reduced to the `__()` → `__wp()` rename. Its `wp_mail()` hunk no longer matched the stubs (WordPress added an `$embeds` parameter) and renamed a function nothing overrides
- `mockery/mockery` `^1.6.11` in development

### Deprecated
- Blocks in `resources/blocks` are still registered, with a notice in the log, until Pollora v15. A theme or plugin blocks directory scans both locations, and a block present in both is taken from `resources/views/blocks`
- `pollora:make:block --dynamic`, now the default

### Fixed
- A block `render` file outside the block directory is no longer included: WordPress built its own render callback from `block.json` whenever Pollora's check rejected the path
- Blocks built with `vite build` get their view script and stylesheets again: the Vite entries generated by `pollora:make:block` only listed each block's `index` script, so `viewScript`, `style` and `editorStyle` were missing from the production manifest. Running the command again in a configured theme or plugin rewrites the entries
- `pollora:make:block` adds `wordpressPlugin()` to the Vite plugins on first use: it imported the plugin, then skipped adding it because the name was already in the file
- The Artisan commands renamed to the Laravel colon convention in v13.32.0-beta answer to their previous names again. The rename left no alias, so a project whose `composer.json` still ran `pollora:env-setup` in `post-autoload-dump` failed `composer install` right after upgrading. Kept as aliases: `pollora:env-setup`, `pollora:make-theme`, `pollora:delete-theme`, `pollora:make-plugin`, `pollora:make-block`, `pollora:make-model`, `pollora:make-action`, `pollora:make-filter`, `pollora:make-posttype`, `pollora:make-taxonomy`, `pollora:make-wp-cli`
- `pollora:install` pointed to a non-existent `wp:env-setup` command when the database connection failed
- Asset containers no longer share one Vite configuration. Each `ViteManager` reconfigured Laravel's `Vite` singleton in place, so the last container set up — typically the theme — imposed its hot file on every other one: with the theme on `npm run dev` and a plugin built for production, the plugin's assets resolved as built but were enqueued as hot, and the block editor crashed with `forceFullUrl(): Argument #1 ($path) must be of type string, array given`. The `getAssetUrls` macro likewise read the build directory of the last manager registered instead of its own, and the Vite client tag was rendered from the unconfigured singleton
- `pollora:install` completes without interaction (`--no-interaction`, CI, piped stdin). It called `pollora:make:theme` without a name, which failed with `Not enough arguments (missing: "name")` after WordPress was installed; it now generates the `default` theme from `pollora/theme-default` — the theme WordPress activates on install — or the one named by the new `--theme` option
- `pollora:install` runs its migrations with `--force` and fails when they fail. In production, `migrate` asks for confirmation and cancels when it cannot prompt, yet the install reported `Migration completed successfully`: a non-interactive production install left the sessions and cache tables missing and every page answered 500
- Commands using `PromptsForMissingOption` (`pollora:make:theme`, `pollora:make:plugin`) apply their prompt defaults when they cannot prompt, instead of leaving the options empty: a theme generated without interaction had a `style.css` with no author, URI, description or version

### Removed
- `patches/mockery-php84-nullable.patch` — Mockery fixed the PHP 8.4 implicit nullable deprecations upstream, so the patch no longer applied and printed `Could not apply patch!` on every `composer install`, including in projects built on the skeleton. `patches/wordpress-core.patch` stays: it is what lets `pollora/helper-overrider` declare `__()`

## [v13.32.0-beta](https://github.com/Pollora/framework/compare/v13.4.3...v13.32.0-beta) - 2026-09-17

Pollora now follows Laravel's version numbers: this release requires Laravel 13.32.

### Added
- Translation diagnostics in `pollora:status` and the admin dashboard — reports whether the `__()` override is the one actually installed, alongside the WordPress and Laravel locales. `laravel/framework` declares `__()` behind the same `function_exists()` guard as [`pollora/helper-overrider`](https://github.com/Pollora/helper-overrider) and Composer emits `autoload.files` in dependency order, so whichever loads first wins; when Laravel wins nothing errors, WordPress catalogues simply stop resolving and every core, theme and plugin string silently renders untranslated. Detection compares the file declaring `__()` against the one declaring `pollora_translation_resolver()` — they ship in the same `helpers.php`, so matching paths prove the package won the race whatever the install layout
- WordPress Abilities API support through the new [`pollora/abilities`](https://github.com/Pollora/abilities) package (requires WordPress 6.9)
  - `Ability` facade for fluent declaration: `Ability::define('acme/get-posts')->description(…)->category(…)->can(…)->using(…)`
  - `#[Ability]` attribute on classes implementing `AbilityHandler`, discovered automatically
  - `Ability::category()` for the categories abilities file under, declared for you when an ability names one nobody registered
  - Behaviour annotations (`reads`/`creates`/`updates`/`deletes`) published to AI clients as `readOnlyHint`, `destructiveHint` and `idempotentHint`
  - Typed `SchemaBuilder` for input and output JSON Schema, and a defensive `Input` reader
  - Permission checks receive the same input as the body, allowing per-object capability checks; they default to refusing
- `#[SkipDiscovery]` attribute to exclude classes from the discovery engine
  - Classes annotated with `#[SkipDiscovery]` are completely invisible to all discoveries — no reflection loaded, no attributes scanned
  - `except` parameter for selective exclusion: `#[SkipDiscovery(except: [HookDiscovery::class])]` skips all discoveries except the listed ones
- Publishable `config/discovery.php` with `skip_classes` and `skip_paths` arrays for config-level exclusions (third-party packages, vendor classes)
- `DiscoveryRegistrar` — automatically registers Discovery classes from the service container, eliminating manual `addDiscovery()` calls in ServiceProviders
- `pollora_register()` helper with `ModuleType` enum for simplified theme/plugin registration
  - Themes: `pollora_register(ModuleType::Theme)` — 3 lines in `functions.php`, auto-detects theme name and path
  - Plugins: `pollora_register(ModuleType::Plugin, 'my-plugin', __DIR__)` — replaces manual `PluginRegistrar` wiring
- `#[Ajax]` attribute for declarative AJAX action registration with security-by-default
- Discovery system performance benchmark suite (`tests/Benchmark/`) with realistic attribute complexity and multi-location DDD scenarios
- Public method caching in `ReflectionCache::getPublicMethods()` — avoids redundant `getMethods(IS_PUBLIC)` reflection calls

### Changed
- **BREAKING**: requires Laravel 13.32 (`illuminate/*` `^13.32`). The framework version now tracks the Laravel release it targets, hence the jump from v13.4.3
- **BREAKING**: `Pollora\Services\Translater` now requires its `$domain` argument. The old `'wordpress'` default existed only to pair with the `wordpress.` key prefix that [`pollora/helper-overrider`](https://github.com/Pollora/helper-overrider) 1.2.0 removed — it prefixed every lookup as `__('wordpress.'.$value)` and relied on the resolver stripping it before the gettext call, so with 1.2.0 installed it silently returned values untranslated. Nothing in the framework used it (`Sidebar` and `Menus` both pass `'sidebars'`/`'menus'` explicitly); pass `__($value, 'default')` if you want WordPress's core catalogue. Two latent bugs went with it: the prefix was stripped with an unanchored `str_replace()`, so a value such as `'Go to menus.example'` came back as `'Go to example'`, and `translateItem()` was typed `string` under `declare(strict_types=1)`, so a wildcard `translate(['*'])` over a config array holding an int or bool raised a `TypeError`
- **BREAKING**: Ajax module extracted to `pollora/ajax` package
- **BREAKING**: Option module extracted to `pollora/option` package
- **BREAKING**: Hook domain and adapters extracted to `pollora/hook` package
- Domain layer paths restored for hexagonal architecture consistency
- Public API types exposed for Theme and Option modules
- All manual `addDiscovery()` calls removed from ServiceProviders — replaced by `DiscoveryRegistrar` auto-registration
- Empty `boot()` methods removed from `LaravelPluginModule` and `LaravelThemeModule` (Rector `RemoveParentDelegatingClassMethodRector`)
- Artisan command signatures renamed to Laravel colon convention (e.g. `pollora:make:theme`)
- `DiscoveryItems::all()` optimized — replaced spread-in-loop with `array_merge`
- Redundant reflection pre-loading removed from `DiscoveryEngine`

### Fixed
- `WordPressHeaders` middleware now respects the `DONOTCACHEPAGE` constant set by WooCommerce and compatible cache plugins, preventing public cache headers on cart, checkout, and account pages
- **`__()` no longer fatals on a translation with replacements whose key is absent from the current locale.** `__('Shipping :brand', ['brand' => 'Test'])` raised `TypeError: Cannot access offset of type array in isset or empty` whenever `Lang::has()` answered no: the guard normalised only the empty array to the `default` text domain, so a non-empty replacement array travelled into WordPress's `translate()` as the domain and reached `isset($l10n[$domain])`. It hit every `__($key, [...])` call whose key was not in the locale's catalogue — typically a site whose sources and language are both English, where no `en_US.json` exists at all, taking down every string with a placeholder. Requires [`pollora/helper-overrider`](https://github.com/Pollora/helper-overrider) 1.2, which routes on the caller's intent — a string second argument is a WordPress text domain, a non-empty array is a Laravel call — instead of on whether Laravel happens to hold the key. The same release fixes an unguarded `Lang::has()` that raised `A facade root has not been set.` wherever `__()` runs before or without an application, a `wordpress.` prefix strip that rewrote the substring anywhere it appeared (`__('Go to wordpress.org')` returned `Go to org`), Laravel group keys returning an array to callers typed against WordPress's `string`, and a missing base-language locale fallback that kept a `fr_FR` site from ever reading `lang/fr.json`
- **The `Loop` and `Query` facade aliases no longer crash a page that uses them.** Both were still declared in `extra.laravel.aliases` after their facade classes had been deleted — `Loop` since `9a50cac` (2026-04-21), `Query` since `9c0cb42` (2024-08-05). Laravel registers an alias without checking its target and only calls `class_alias()` the first time the short name is used, so the package installed and booted cleanly and then threw `Class "Pollora\Support\Facades\Loop" not found` on whichever page happened to call it. Present in every v13.4.x release. See the migration note below for what replaces `Loop`.
- `PluginManagerTest` isolation — real `Container` with dynamic `WP_PLUGIN_DIR` replaces fragile `ContainerInterface` mock
- WooCommerce hooks test updated for `ComingSoonHandler` dependency
- PHPStan dead catches widened, redundant `@return $this` docblocks removed
- `OptionService` namespace aligned with extracted package
- GitHub releases are created again on tag push — the Deploy workflow lacked `contents: write`; suffixed tags (`-beta`, `-rc.1`) are now published as pre-releases

### Removed
- The `Loop` and `Query` entries in `extra.laravel.aliases`, whose facade classes no longer exist

### Migrating from `Loop`

`Loop::` was removed in `9a50cac` as a breaking change, but the alias stayed
behind and the removal was never written down — so a project only found out when
a page 500'd. This is that note, late.

In Blade, the replacement is [Sage Directives](https://github.com/Log1x/sage-directives)
(`log1x/sage-directives`, registered by `PolloraServiceProvider`): `@title`,
`@content`, `@excerpt`, `@permalink`, `@published`, `@posts` and the rest.

In PHP, `Pollora\View\Loop` was only ever a proxy over WordPress functions. The
full mapping, read off the deleted source rather than reconstructed:

| Removed | Replacement |
|---|---|
| `Loop::id()` | `get_the_ID()` |
| `Loop::title($post)` | `get_the_title($post)` |
| `Loop::author()` | `get_the_author()` |
| `Loop::authorMeta($field, $userId)` | `get_the_author_meta($field, $userId)` |
| `Loop::content($moreText, $stripTeaser)` | `apply_filters('the_content', get_the_content($moreText, $stripTeaser))`, then `str_replace(']]>', ']]&gt;', …)` |
| `Loop::excerpt($post)` | `apply_filters('the_excerpt', get_the_excerpt($post))` |
| `Loop::thumbnail($size, $attr, $post)` | `get_the_post_thumbnail($post, $size, $attr)` |
| `Loop::thumbnailUrl($size, $icon)` | `wp_get_attachment_image_src(get_post_thumbnail_id(), $size, $icon)[0] ?? null` |
| `Loop::link($post, $leavename)` | `get_permalink($post, $leavename)` |
| `Loop::category($id)` | `get_the_category($id)` |
| `Loop::tags($id)` | `get_the_tags($id) ?: []` |
| `Loop::terms($taxonomy, $post)` | `get_the_terms($post, $taxonomy) ?: []` |
| `Loop::date($format, $post)` | `get_the_date($format, $post)` |
| `Loop::postClass($class, $postId)` | `'class="'.implode(' ', get_post_class($class, $postId)).'"'` |
| `Loop::nextPage($label, $maxPage)` | `get_next_posts_link($label, $maxPage)` |
| `Loop::previousPage($label)` | `get_previous_posts_link($label)` |
| `Loop::paginate($args)` | `paginate_links($args)` |

Four of these are not straight renames, and a blind substitution breaks them:

- `thumbnail()` and `terms()` **reorder their arguments** — the post moves from
  last to first — and both defaulted the post to the one in the loop.
- `postClass()` returned a ready-made `class="…"` attribute string, where
  `get_post_class()` returns an array.
- `content()` and `excerpt()` applied `the_content` / `the_excerpt` themselves;
  dropping the filter silently strips whatever other plugins add there.
- `tags()` and `terms()` normalised `false` to an empty array.

`Query::` has no replacement to document — the facade was already gone before
v13, and the alias outlived it by two years.
- Unfinished Admin Pages module
- Manual `addDiscovery()` boot logic in HookServiceProvider, PostTypeServiceProvider, TaxonomyServiceProvider, WpRestAttributeServiceProvider, SchedulerDiscoveryServiceProvider
- `RegisterScheduleDiscoveryUseCase` (superseded by `DiscoveryRegistrar`)

## [v13.4.3](https://github.com/Pollora/framework/compare/v13.4.2...v13.4.3) - 2026-08-31

### Fixed
- Theme Gutenberg patterns are registered again ([#295](https://github.com/Pollora/framework/issues/295)) — `PatternService` resolved the pattern directory from `WP_Theme::get_theme_root()`, which bypasses the `theme_root` filter and pointed at `WP_CONTENT_DIR/themes` instead of the configured `theme.path`; the directory is now derived from `ThemeMetadata`
- Patterns are discovered across the full theme ancestry (ancestors first, so the active theme can override an inherited slug) instead of a single parent level

### Removed
- `Pollora\BlockPattern\Infrastructure\Registrars\PatternRegistrar` — dead duplicate of `PatternService` carrying the same theme-root resolution bug

## [v13.4.2](https://github.com/Pollora/framework/compare/v13.4.1...v13.4.2) - 2026-06-29

### Fixed
- Force `$_SERVER['HTTPS']` when `APP_URL` uses HTTPS scheme

## [v13.4.1](https://github.com/Pollora/framework/compare/v13.4.0...v13.4.1) - 2026-06-29

### Fixed
- Use `config('app.url')` for `WP_HOME`/`WP_SITEURL` instead of `url()` helper

## [v13.4.0](https://github.com/Pollora/framework/compare/v13.3.0...v13.4.0) - 2026-04-22

### Added
- `WordPressRouteInterface` in `Route\Domain\Contracts` — pure domain contract for WordPress routing capabilities (`isWordPressRoute`, `setCondition`, `hasCondition`, etc.), decoupling the Domain layer from `Illuminate\Routing`
- Trailing slash removal at URL source via `user_trailingslashit` filter
  - All WordPress-generated URLs (posts, pages, terms, archives, feeds, pagination) are now consistent with no trailing slash
  - Previously only canonical redirects were handled, leaving in-page links with trailing slashes
  - Refactored `Permalink` module to DDD architecture (Domain contracts, services, Infrastructure providers)
  - `RewriteServiceProvider` renamed to `PermalinkServiceProvider` with DI for hook services
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
- DDD Application layer (UseCases) for Route, Modules, and Schedule modules
  - **Route**: `RegisterWordPressTypesUseCase` and `BindWordPressParametersUseCase` extracted from `WordPressRoutingService`
  - **Modules**: `DiscoverModulesUseCase` and `ApplyModulesUseCase` extracted from `ModuleServiceProvider` boot logic
  - **Schedule**: `RegisterSchedulerFiltersUseCase` and `RegisterScheduleDiscoveryUseCase` extracted from `SchedulerServiceProvider`
  - All three service providers restructured with `registerDomainContracts()` / `registerUseCases()` / `registerApplicationServices()` pattern (following View module architecture)
  - 31 new unit tests covering all use cases
- `setArg()` / `getArg()` methods on `PostTypeAttributeInterface` and `TaxonomyAttributeInterface` for type-safe attribute argument access
- `ModuleDiscoveryInterface` extracted from `ModuleDiscoveryOrchestratorInterface` for specialized discovery services
- Missing methods added to domain interfaces: `ModuleRepositoryInterface::resetCache()`, `ThemeService::hasTheme()/getActiveTheme()`, `DiscoveryItemsInterface::__serialize()`, `DiscoveryEngineInterface::clearCache()/clearLocations()/runDiscovery()`
- `$priority` parameter added to `PostTypeFactoryInterface::make()` and `TaxonomyFactoryInterface::make()` (matching existing implementations)
- `WordPressConditionManagerInterface` now extends `ConditionResolverInterface`

### Added
- `Support\Domain\StringHelper` — framework-agnostic string utilities (`studly`, `kebab`, `snake`, `headline`, `singular`, `plural`) replacing `Illuminate\Support\Str` in the Domain layer
- `Modules\Infrastructure\Services\ModuleScaffolderService` — shared service for file scaffolding, eliminating ~300 lines of duplication between `MakePluginCommand` and `MakeThemeCommand`
- `Discovery\Infrastructure\Services\DiscoveryCacheManager` — extracted Spatie cache orchestration from `DiscoveryEngine` (665 → 560 lines)
- Unit tests for `Filesystem`, `Mailer` (headers, attachments, from parsing), `WordPressGuard` (attempt, once, login), `PageServiceProvider`, `SchedulerServiceProvider`, `BlockServiceProvider`, `PluginServiceProvider`, `DiscoveryEngine`, `PluginManager`, `StringHelper` (+92 unit tests)
- Feature tests for `AssetEnqueuer` (fluent builder, type detection, context hooks, localize, Vite skip) (+23 feature tests)
- Backward-compatible class alias for `Pollora\Theme\Domain\Models\LaravelThemeModule` (moved to Infrastructure)
- Type hints on closure parameters in 17 ServiceProviders (type coverage 98.1% → 98.7%)

### Fixed
- Flaky `VersionCheckServiceProviderTest` — narrowed mock assertions to specific hook names instead of blanket `shouldNotReceive('add')`
- Risky `AssetEnqueuerTest` — replaced manual `__destruct` invocation with full chain integration test
- `RecursiveMenuIterator` invalid `@extends` PHPDoc tag removed
- `WordPressTaxonomyRegistry::getAll()` return type annotation corrected to `array<int|string, WP_Taxonomy>`
- `WordPressThemeParser` — replaced `app()` service locator with constructor-injected `Container`
- PHP 8.2+ deprecation warning for dynamic property creation on `Spatie\StructureDiscoverer\Data\DiscoveredClass` in `DiscoveryEngine` — replaced dynamic `$structure->location` assignment with an associative array pairing structures with their discovery locations
- `WordPressHeaders` middleware no longer overwrites response headers set by application code or plugins
  - Content-Type is preserved (was incorrectly removed, breaking PDF/JSON/binary responses)
  - Public cache directives are only applied to cacheable HTML responses (skip JSON, PDF, binary, streamed, redirects)
  - Explicit cache directives (`no-store`, `max-age`, `s-maxage`) from plugins or controllers are respected
  - `Expires` header removed when applying public cache (prevents conflict with WordPress's `Expires: 1984` on WP routes)
  - Per-condition cache TTL via `wordpress.cache.ttl` config (e.g. `is_front_page: 600`, `is_single: 7200`)
  - Optional CDN/reverse proxy `s-maxage` directive via `wordpress.cache.shared_max_age` config
- WordPress `shutdown` hook output now reaches the browser
  - Plugins relying on `shutdown` (Query Monitor toolbar, WP Rocket cache processing) were broken because Laravel's `Response::send()` calls `fastcgi_finish_request()` before PHP shutdown
  - `WordPressShutdown` middleware now fires `do_action('shutdown')` within a controlled output buffer before the response is returned, injecting captured output (e.g. QM toolbar) before `</body>` in HTML responses
  - Prevents double execution by clearing shutdown callbacks after firing; `wp_cache_close()` remains unaffected
  - Exception-safe buffer management following Laravel's `PhpEngine` pattern
- Typo `isOrchastraTest` → `isOrchestraTest` in `SchedulerServiceProvider`
- `ThemeRegistrar` container type restored to `ContainerInterface` (was incorrectly narrowed, causing TypeError at boot)
- `WpCli\WordpressCommand` accessing non-existent `$attribute->name` and `$attribute->description` on `WpCli` attribute
- `PluginDeleted` redundant readonly property redeclaration removed
- Dead catches widened from `ReflectionException` to `Throwable` in PostTypeDiscovery/TaxonomyDiscovery
- `WordPressDatabase::$dbh` PHPDoc type aligned with parent `wpdb`
- `LaunchPadInstallCommand` exception imports fixed
- `Ajax` and `Mail` facade `@method` PHPDoc annotations fixed
- `BlockRegistrar` crash in WP-CLI context (`wp_register_script()` called before WordPress script API loaded)

### Changed
- **DDD Domain purity**: `Route` moved from `Route\Domain\Models` to `Route\Infrastructure\Models` — extends `Illuminate\Routing\Route`, now implements `WordPressRouteInterface`. Consumers type-hint the interface (Domain) or concrete class (Infrastructure) depending on layer. Middlewares use `instanceof WordPressRouteInterface` for condition checks
- **DDD Domain purity**: `LaravelPluginModule` and `LaravelThemeModule` moved from `Domain/Models/` to `Infrastructure/Models/` — they use `config()`, `env()`, `add_action()`, `AliasLoader` directly, which are infrastructure concerns
- **DDD Domain purity**: `SystemInfoCollector` refactored — `Application::VERSION` replaced with injected `$laravelVersion`, `Illuminate\Contracts\Container\Container` replaced with `Psr\Container\ContainerInterface`
- **DDD Domain purity**: `Illuminate\Support\Str` replaced with `Support\Domain\StringHelper` in `AbstractModule`, `AbstractTaxonomy`, `SystemInfoCollector` — only `AbstractTaxonomy` retains `Str` for `singular`/`plural` (Doctrine Inflector, now also in StringHelper)
- **DDD Domain purity**: `add_action()` direct calls in `LaravelPluginModule`/`LaravelThemeModule` replaced with framework `Action` service via container
- `MakePluginCommand` reduced from 782 → 447 lines, `MakeThemeCommand` from 568 → 307 lines (shared `ModuleScaffolderService`)
- `DiscoveryEngine` reduced from 665 → 560 lines (extracted `DiscoveryCacheManager`)
- `ThemeInitializer::register()` cleaned up — anonymous closure replaced with method reference, unused `overrideThemeDirectory()` removed
- PHPStan baseline reduced from 186 to 36 entries (−81%), fixing ~150 real type errors
- Rector auto-fixes applied: arrow function return types and newline-after-statement across 34 files
- All 74 PostType/Taxonomy attribute classes refactored from direct `$attributeArgs` property access to `setArg()`/`getArg()` method calls
- Exception classes `ModuleException`, `PluginException`, `DiscoveryNotFoundException`, `InvalidDiscoveryException` made `final`
- `FrameworkModuleDiscovery` and `LaravelModuleDiscovery` now implement `ModuleDiscoveryInterface` instead of the full `ModuleDiscoveryOrchestratorInterface`
- All 65 `error_log()` calls replaced with PSR-3 `LoggerInterface` across 27 files
- Renamed `src/Taxonomy/config/post-types.php` to `taxonomies.php` (fixes inconsistent naming)
- Added comprehensive PHPDoc to `WordPressShutdown` and `WordPressHeaders` middlewares

### Removed
- Legacy `ModuleBootstrap` and `ModuleManifest` classes (empty shells with no dependents)
- Defensive `method_exists()` checks and redundant `bound()` guards in `ModuleServiceProvider`
- **BREAKING**: `Pollora\Route\Domain\Models\Route` moved to `Pollora\Route\Infrastructure\Models\Route` — update imports in any code referencing the old namespace
- **BREAKING**: `@theme` Blade directive removed — conflicted with Tailwind CSS v4 `@theme` at-rule, had zero usage. Use `app('theme.service')->hasTheme($name)` if needed
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