# Changelog

All notable changes to the Pollora framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/Pollora/framework/compare/v13.35.2...develop)

### Added
- `pollora:make:module Crm` creates a lean module from the new [Pollora/module-default](https://github.com/Pollora/module-default) template: classes discovered in `app/` (an example hooks class, no service provider), `resources/views/blocks`, and a Vite build through [`@pollora/vite-config`](https://github.com/Pollora/vite-config) into `public/build/module/<kebab>`. Each Laravel layer is one flag away: `--provider`, `--routes` (implies `--provider`), `--api` (implies `--routes`), `--config`, `--database`, `--tests`, or `--full`; `--no-assets` for a PHP-only module. The module is enabled, `composer dump-autoload` merges its `composer.json`, and npm builds it (`--no-enable`, `--no-npm`). `--repository` and `--repo-version` pick another template; `--offline`, or a failed download, uses the copy bundled with the framework
- `module:make` writes the same lean module, offline, unless the project published `config/modules.php`: the framework turns off nwidart/laravel-modules' stock folders, files and classes and writes its bundled template once the module is created
- Every enabled module with a `vite.config.*` gets its `module.<kebab>` asset container, so `Asset::add(...)->container('module.crm')` works without a provider of its own; it was set up only for modules with blocks
- `pollora:make:block` recognises a `vite.config.js` built on `@pollora/vite-config`, which builds blocks already, instead of warning that it does not match the Pollora pattern
- Module activation connectors: whether a Laravel module is enabled comes from a connector, through nwidart/laravel-modules' activator (`'activator' => 'pollora'`), so `module:enable`, `module:disable` and everything reading `app('modules')` keep working. `json` (default) reads and writes `modules_statuses.json` as nwidart does; `database` keeps a JSON map in the non-autoloaded `pollora_modules` WordPress option, read with Laravel's connection before WordPress loads (never unserialized) and written with `update_option()` once it has, falling back on `json` while the options table does not exist; `config` reads `connectors.config.states` or `MODULES_ENABLED` / `MODULES_DISABLED`, read-only. A project connector implements `ModuleStateConnector` and is declared in `connectors.<name>.class`, or given to `ModuleConnectors::extend()` in `bootstrap/app.php`
- `modules.locked.enabled` / `modules.locked.disabled` (`MODULES_LOCKED_ENABLED` / `MODULES_LOCKED_DISABLED`) force a module's state over any connector; switching a locked module throws `ModuleLockedException`
- A switch clears nwidart's provider manifest and the discovery cache, and fires `ModuleEnabled` / `ModuleDisabled` (module, source `admin` or `console`, WordPress user)
- `config/modules.php`, published with `php artisan vendor:publish --tag=pollora-modules`: nwidart reads its activator while it registers, before any provider of the application, so the connector is chosen in this file, not from a provider
- `pollora:module:connector [connector] [--import]` shows where module states live and copies them into another connector before the configuration points to it
- Plugins › Modules (`plugins.php?page=pollora-modules`, linked as "Modules (n)" among the plugin views): every module with its description, path, state (enabled, disabled, locked) and where the state lives, with Enable / Disable per row and as bulk actions. Switches need `activate_plugins` (`modules.admin.capability`), are nonce-protected, go through nwidart's `Module::enable()` / `disable()` and say the change applies from the next request. A locked module has no switch; `MODULES_ADMIN_TOGGLE=false` turns every switch off. With the JSON file, the view warns that the next deployment resets it and each switch asks for confirmation; when the file cannot be written, switches are off and the view explains how to change states
- Tools › Pollora's Modules card and `pollora:status` say where module states are stored, and the card links to the Modules view
- `pollora:make:block --module=<name>` creates a block in a module's `resources/views/blocks`, named after the module in kebab case (`blocks-demo/hero`), like the seven generators that already took `--module`

### Fixed
- Tools › Pollora and `pollora:status` reported 0 modules when one `module.json` had no `priority`: nwidart's `getPriority()` is typed `string` and threw
- Generators with `--module` wrote into `Modules/<Studly>` under the `Modules\<Studly>` namespace whatever the module declared: they now take the path where nwidart/laravel-modules found the module and the namespace its `composer.json` maps onto `app/` (or `src/`), so a module under `Module\BlocksDemo\` gets classes it can autoload

### Removed
- The framework's `Modules/config/modules.php`, merged into `modules` after nwidart/laravel-modules' own config and so never applied (its module path pointed at the themes directory)

## [v13.35.2](https://github.com/Pollora/framework/compare/v13.35.1...v13.35.2) - 2026-10-07

### Added
- Asynchronous actions in the framework (pollora/hook 1.4): `Action::add(...)->async()` can queue handlers as Laravel jobs. The `queue` driver dispatches a `RunAsyncAction` job on `hooks.async.queue.connection` (the default connection when null) and on the action's `onQueue()` or `hooks.async.queue.queue`. `auto`, the default, tries the Laravel queue first once `HOOKS_ASYNC_CONNECTION` names the connection a worker runs (without a worker, jobs would never run, so the queue is opt-in), then Action Scheduler, then WP-Cron; a `sync` or `null` connection is always skipped. `via('queue')` and `HOOKS_ASYNC_DRIVER=queue` use the default connection. The job is tried once: retries are new jobs queued after the backoff, and the last failure lands in the failed jobs table. It also clears WordPress's in-memory cache before each handler, since a worker lives across jobs
- `config/hooks.php`, published with `php artisan vendor:publish --tag=pollora-hooks`: default driver (`HOOKS_ASYNC_DRIVER`, `sync` in a developer's `.env` runs every handler at once), attempts, backoff, `as_user`, queue connection and name
- `#[Async]`, next to `#[Action]`, makes a method asynchronous with the options of `->async()` as named parameters: `delay`, `via`, `onQueue`, `unique` (`true` or the lock duration), `tries`, `backoff`, `asUser`, `capture` and `when` (public methods of the class), `keepMissing`, `except`. On a class it applies to every `#[Action]` method, and a method's own `#[Async]` replaces it. A declaration that cannot be honoured (a hook in `except` the method does not declare, a missing or non-public `capture`/`when` method, invalid attempts, backoff or lock) is logged and the action runs synchronously; `#[Async]` on a `#[Filter]` or without `#[Action]` is logged
- `pollora:doctor` and Site Health check asynchronous actions (`async-actions`): an ignored `#[Async]` (now kept, not only logged), an unavailable default driver (every handler then runs in the request) or `via()` driver, a `HOOKS_ASYNC_CONNECTION` that is undefined or `sync`/`null`, WP-Cron events overdue for an hour while WP-Cron or Action Scheduler is in use (Pollora sets `DISABLE_WP_CRON`: a system cron must request `wp-cron.php`), jobs of a database queue waiting 15 minutes for a worker, and payloads or unique locks the daily recovery task left behind
- `pollora:make:action --async` generates the method with `#[Async]` and its import, in a new class or added to an existing one
- `pollora:async:list` lists the asynchronous actions (hook, priority, handler, driver, delay, attempts and backoff, unique lock, queue, as user), which drivers are available in this request, and the default driver with where it comes from (`HOOKS_ASYNC_DRIVER`, the `POLLORA_ASYNC_DRIVER` constant, the `pollora/hook/async_driver` filter, or `auto` and the drivers it tries); `--json` for scripts
- Async handlers in the framework: debug mode follows `app.debug`, incidents go to the Laravel log, closures are signed with the application key, Eloquent models travel by class and key and are reloaded at execution, and parameters typed with a service are resolved from the container

### Fixed
- `pollora:make:action` and `pollora:make:filter` were registered as `pollora:make:action {name}` and `pollora:make:filter {name}`: the console reached them by prefix, but `Artisan::call('pollora:make:action')` did not find them
- The method generated for a hook with separators read `handleSavepost` for `save_post`: it is now `handleSavePost`
- Errors met while registering hooks discovered by attribute went to no logger: `HookDiscovery` now receives the application's logger
- Login screen: the language switcher's label ("Language" and its icon, shown when a second language is installed) sat at the left edge of the window, far from its dropdown. The card style given to the login form also reached the switcher's form and made it a full-width block; it is an inline block again, as in WordPress's own sheet
- Login screen: the rules for the buttons were written `.pollora-login .wp-core-ui …`, but both classes are on `<body>`, so they never matched. The language switcher's button kept wp-admin's blue instead of the theme's primary colour, and the submit button kept WordPress's `button-large` padding

## [v13.35.1](https://github.com/Pollora/framework/compare/v13.35.0...v13.35.1) - 2026-10-07

### Security
- WordPress 7.1.3 is a security release (seven fixes, among them a stored XSS on the Comments screen and a second-order SQL injection in the WXR export). The skeleton installs it from v13.35.1; an existing project runs `composer update johnpbloch/wordpress johnpbloch/wordpress-core`. Pollora's core patch still applies

### Removed
- Editor and tool files no longer tracked: `.cursor/` and `.windsurf/` rules from 2025 (superseded by `CLAUDE.md` and Nectar), a `.claude/` plan, a 2023 `.php-cs-fixer.cache`, and the unused StyleCI and Code Climate configurations (style is checked by Pint in CI, coverage by Codecov)

### Fixed
- `pollora:make:plugin` no longer copies the template's `bin/` (its packaging script and tests) into the new plugin, as `pollora:make:theme` already did
- A plugin that answers from `template_redirect` and includes the theme's query template itself (`include get_query_template('404')`, WooCommerce's Review Order page) printed the Blade source of the view: the `{type}_template` filters now hand back a loader that renders it while `template_redirect` runs (#419)
- `template_redirect` ran before the application's providers had booted — WordPress is loaded from a provider's boot, the theme's providers boot after it — so a view rendered from it lacked the theme's view composers and shared data. It now runs once every provider has booted, still before routing, and `pollora_loaded` after it (#419)
- Trackbacks answered 500: `wp-trackback.php` was required from a method, where it found no `$wp` and loaded WordPress a second time (#419)

### Changed
- README: the organization's banner and badges (CI instead of Codecov), installation first, the newer features listed (typed meta, roles, blocks and Block Bindings, `pollora:doctor`), and the type coverage check under Testing

## [v13.35.0](https://github.com/Pollora/framework/compare/v13.34.6...v13.35.0) - 2026-10-06

### Added
- `pollora:doctor` and Site Health check every Block Binding written in the templates, template parts and patterns of the theme, the Pollora plugins and the modules: a source that is not registered, a field the source does not have, a meta no `#[Meta]` declares, a meta or an option the source may not show (not in REST, protected, not `public`, not listed in `block-bindings.options`), an attribute WordPress does not bind for that block, and a `block.json` whose `pollora.bindings` lacks `render` or lists an undeclared attribute. Each of these left the block with its saved content, silently. Content stored in the database is not read
- `pollora:doctor` and Site Health check typed meta: a `#[Meta]` declaration discovery refused (until now only logged, its meta never registered), a post type or taxonomy named by `#[PostMeta]`/`#[TermMeta]` that does not exist, meta marked `showInRest` on a post type or taxonomy that is not in REST, and stored values that cannot be read as their type (sampled: they read as the default)
- `pollora:doctor` and Site Health check roles: users still carrying a role removed from the code (named, with the users), a `default_role` naming a role that no longer exists (new users get no capability), capabilities given to users one by one outside the declared roles, and what the role declarations could not apply (until now only logged)
- `pollora:roles:list` (`--json`): every WordPress role with its origin (declared by a class, modified by one, or stored), its number of capabilities and of users
- `pollora:roles:show {role}` (`--json`, a slug or a `#[Role]` class): the effective capabilities of a role, each with where it comes from (inherited, granted by the class, by `#[ModifyRole]`, by the super roles), and the capabilities the code removes
- `pollora:roles:prune` (`--reassign=`, `--force`): takes roles removed from the code off the users who still carry them (WordPress's `remove_role()` ignores a role it no longer knows, so through `remove_cap()`), gives the users left with no role the `--reassign` one, and deletes the copies of removed roles a plugin wrote back to the database. Shows the changes unless `--force`; refuses to leave a user with no role
- `pollora:roles:import {role}` (`--inherits=`, `--class=`, `--theme`, `--plugin`, `--module`): generates a `#[Role]` class from a role stored in the database (`add_role()`, a role editor plugin); with `--inherits`, only the differences (`#[Grants]`, `#[Without]`). Sensitive capabilities get `allowSensitive: true` and a note; capabilities stored as denied are listed and left out. Refuses core roles (use `#[ModifyRole]`) and roles the code already declares
- `pollora:roles:dump` (`--force`): writes the roles as the code makes them into the `{prefix}user_roles` option, for tools that read the database without loading the site; what it writes is marked, so a role later removed from the code is still removed. Shows the changes unless `--force`
- `pollora:meta:list` (`--json`): every typed meta by class, with the object it belongs to, its key, type and options; exits with 1 when discovery refused a declaration
- `pollora:meta:audit` (`--limit`, `--json`): reads the stored values of every typed meta and names those that cannot be read as their type (a property whose type changed, a value written outside the meta API), with the objects concerned — exits with 1 then, for CI against a copy of production; and lists the keys stored on the project's own post types and taxonomies that no `#[Meta]` declares, such as the old key of a renamed property
- `pollora:binding:list` (`--json`): the Pollora binding sources, what each one offers (fields, the meta it may show by post type or taxonomy, the listed options), and the blocks whose attributes WordPress lets bind
- In debug mode, a Block Bindings field slower than 50 ms is logged as a warning, with its source, its field and its post: every bound block of the page waits for it

### Changed
- Requires Laravel 13.35 (`illuminate/*` `^13.35`)

## [v13.34.6](https://github.com/Pollora/framework/compare/v13.34.5...v13.34.6) - 2026-10-06

### Added
- Block Bindings sources declared in PHP (**experimental**): a class marked `#[BlockBinding('acme/event')]` is registered with `register_block_bindings_source()`, each public method marked `#[BindingField]` being a field a block chooses with `"args": {"field": "remaining_seats"}` (an `__invoke()` source receives every call). A field receives a `BindingContext` — the post or term of the block context, right in a query loop, the arguments, the bound attribute, `post()` and `meta(Event::class)` for typed meta — and any dependency the container resolves. Every source answers through one resolver, so none can forget its checks: nothing of a post the visitor cannot see (unpublished, password, `read_post`) or of a non-public term, text escaped where WordPress writes it as HTML, `url` fields sanitized, an `HtmlString` filtered like post content, a field that throws logged and the block keeping its content (thrown in debug mode), each value computed once per request. A declaration WordPress would reject or leave silently empty (name, missing or unsupported return type, field declared twice) is reported at discovery. `pollora:make:binding` generates a source
- A server-rendered block becomes bindable by listing attributes under `pollora.bindings` in its `block.json`: the attributes are added to `block_bindings_supported_attributes_{block}` and the Blade view receives the bound value in `$attributes`. A block without `render`, or an attribute the block does not declare, is reported
- Block Bindings sources for typed meta, formatted by their declared type: `pollora/post-meta` and `pollora/term-meta` (a term from the block context or the queried term) read a `#[Meta]` exposed in REST under a key that is not protected, as `core/post-meta` does; `pollora/author-meta` reads a user meta marked `#[Meta(public: true)]` only; `pollora/option` reads the options listed in `block-bindings.options` (none by default). A date shows in the site's format and language (`format` for another), a number with the site's separators (`decimals`), a boolean as Yes/No (`true`, `false` for other words), an enum by its `label()`, an array as a list, `format: raw` gives the stored value (a date in ISO 8601) and `fallback` the text of an empty meta
- The editor side of Pollora's binding sources: the block's "Attributes" panel offers the fields of every `#[BlockBinding]` class (only on the post types it names) and, for `pollora/post-meta`, `term-meta` and `author-meta`, the meta of the post type being edited that the source may show (an attachment also to `number` attributes, for an image ID); `pollora/option` offers the listed options. A bound block shows its value in the editor, computed on the server by the same resolver as the page, escaped the same way and formatted in the site's language whatever the user's, through `POST /wp-json/pollora/v1/block-bindings/resolve`: one request for the blocks waiting for a value, allowed to who can edit the post (`edit_posts` without one). The values are read-only; the field's label shows while a value loads or when there is none. The script is `resources/js/block-bindings.js`, printed inline after `wp-blocks` with no build
- `#[Meta(media: true)]` on an `int` property marks an attachment ID: bound to an image, it gives the URL (`size`), the alternative text, the title or the caption depending on the attribute; its input control is `Media`. `#[Meta(public: true)]` marks a meta anyone may see

## [v13.34.5](https://github.com/Pollora/framework/compare/v13.34.4...v13.34.5) - 2026-10-06

### Added
- Input fields for typed meta, through a contract (**experimental**): `#[Meta(control: Control::Color, group: 'Profile', hints: ['acf' => [...]])]` describes the field in neutral terms, a control being derived from the type otherwise (`Text`, `RichText` with `sanitize: 'wp_kses_post'`, `Number`, `Toggle`, `DateTime`, `Select`). A package implements `MetaUiDriver` and registers it with `Meta::extend('acf', AcfDriver::class)`; the project picks it in `meta.ui` (none by default), and the driver receives each schema on `init`, with only the meta it supports. `Meta::schemas()`, `Meta::schemaFor('post', 'event')`, the `MetaSchemasRegistered` event, and `MetaUiDriverConformance::check($driver)` for driver authors. The framework names no field plugin
- Arrays and data objects as typed meta: an `array` property declares its item type with `items: 'int'` (or a class) or a `@var list<string>` docblock, and is stored as one serialized array or, with `single: false`, one row per item (`whereMeta()` then matches a model by one of its items). A class with public typed properties (scalars, dates, backed enums) is stored as an array, never as a PHP object, and read back as an instance; absent, it reads as an instance with its own defaults. REST publishes `items` and `properties` schemas, so WordPress refuses an item or a property of the wrong type; strings inside are sanitized like a string meta
- Typed meta: a post type declared with `#[PostType]` gets `custom-fields` support when one of its `#[Meta]` has `showInRest: true`, without which WordPress leaves `meta` out of its REST responses. A post type targeted by `#[PostMeta]` is left as it is
- Typed meta validation: `#[Meta(rules: ['min:0', 'max:5000'])]` checks a value with Laravel's validator, the type rule implied (`integer` for an int, so `max` compares numbers). A write from PHP (`Meta::of()->set()`, a model attribute) throws a `MetaValidationException` naming the meta; a REST write to a post, term, user or comment answers 400 with the message of the rule (`params` → `meta.<key>`) before anything is stored. Writes through WordPress's own functions (`update_post_meta()`) are only sanitized
- Typed meta for every object (**experimental**): `#[PostMeta('product')]` or `#[PostMeta(['post', 'page'])]` for post types the project does not declare, `#[TermMeta('category')]`, `#[UserMeta]` and `#[CommentMeta]` (every comment type: WordPress has no per-type comment meta). `Meta::of()` reads and writes them by object ID; a key two classes declare for the same objects is refused at discovery
- Typed meta on the Eloquent models (**experimental**): `Pollora\Models\Post`, `Page`, `Term`, `User` and `Comment` read the `#[Meta]` their object carries as attributes, with their PHP type (`$event->capacity`, or by key `$event->sold_out`), check a write at once and store it through `update_metadata()` when the model is saved; `whereMeta('capacity', '>=', 100)` compares numbers as numbers. A post model with `protected $postType = 'event'` is bound to its post type at discovery, so `Post::find()` returns it. A class known to carry typed meta stops eager loading the `meta` relation and primes WordPress's meta cache once per collection (one query instead of the relation); other models and undeclared keys keep Colt's behaviour
- Roles in Laravel (**experimental**), a role being named by its slug or by the class of a `#[Role]`: `hasRole()`, `assignRole()`, `removeRole()` and `roles()` on `Pollora\Models\User` (trait `HasRoles`; writes go through `WP_User`, an unknown role is refused); the `role:` route middleware (`role:event_manager,editor`, or `EnsureUserHasRole::using(EventManager::class)`), which refuses with a 403 a user who has none of the roles, an alias the application already uses being kept; `@role` accepts role classes (`@role(EventManager::class)`) and keeps the behaviour of Sage Directives' `@role` for slugs
- REST permission `Can` for `#[WpRestRoute]` and `#[Method]`: `permissionCallback: new Can('edit_posts')`, a `#[CapabilitySet]` enum case, or `new Can('edit_post', parameter: 'id')` to check a meta capability on the object in the request. `permissionCallback` now accepts a `Permission` instance as well as a class name; a refusal answers 401 to a guest and 403 to a logged-in user
- Translated role labels: `#[Role(…, textDomain: 'my-theme')]` translates the label with that domain wherever WordPress shows role names (`translate_user_role()`: users list, role dropdowns). Translation happens when the admin displays the role, through `gettext_with_context_default`, so no translation is loaded early; a label WordPress already translates is left alone

## [v13.34.4](https://github.com/Pollora/framework/compare/v13.34.3...v13.34.4) - 2026-10-05

### Added
- Typed meta (**experimental**): a public typed property of a `#[PostType]` or `#[Taxonomy]` class marked `#[Meta]` is registered with `register_meta()` — type, default, sanitization, REST schema (dates as `date-time`, enums as `enum`), `capability` as `auth_callback`, `revisions`. `Meta::of(Event::class, $postId)` reads each meta with its PHP type (`int`, `float`, `bool`, `string`, dates, backed enums) and writes through WordPress's meta API (`->fill([...])->save()`). A declaration WordPress cannot register (union or array type, no default, protected key in REST without a capability, a key declared twice) is reported at discovery with the class and property named. The API may change before it is declared stable
- Roles declared in code (**experimental**): `#[Role('event_manager', inherits: 'author')]` with `#[Grants]`, `#[Without]`, `#[GrantsPostType(Event::class, Access::Editor)]` and `#[GrantsTaxonomy]`; `#[ModifyRole('editor')]` for roles the project does not own; `#[CapabilitySet]` enums for the project's own capabilities; `pollora:make:role`. Roles are injected into WordPress on `wp_roles_init`, never written to the database: the code is the only source of truth, a role or a capability removed from the code is gone even after a plugin wrote the roles back. The roles in `roles.super_roles` (`administrator`) receive every declared capability, so a post type with `#[CapabilityType]` no longer disappears from the admin. Sensitive capabilities need `allowSensitive: true`; a core role cannot be redeclared, a super role cannot be inherited from

### Fixed
- WordPress capabilities answer Laravel's authorization again: `$user->can('edit_posts')`, `Gate::allows('edit_post', $post)`, `@can` and the `can:` middleware. The `Gate::after()` bridge to `user_can()` was only added when WordPress was already loaded, which it never is when providers register, so every WordPress capability was denied. The bridge now converts the Gate's user (`Pollora\Models\User`, `WP_User` or ID) and Eloquent model arguments for `user_can()`, and `Pollora\Models\User` uses Laravel's `Authorizable` (`can()`, `cannot()`). Abilities defined with `Gate::define()` and policies keep priority (#390)

### Changed
- Dependency minimums: `pollora/ajax`, `pollora/hook`, `pollora/option` `^1.1`, `laravel/prompts` `^0.3.24` (#394)
- Discoveries also receive the enums that carry attributes (needed by `#[CapabilitySet]`); enums without attributes are still skipped

### Removed
- `src/Theme/Infrastructure/Services/Directives.php`: `@usercan`, `@template` and `@gravityform` were never registered (the file was loaded nowhere, and `@usercan` called a `User::current()` that does not exist). Use `@can` for capabilities

## [v13.34.3](https://github.com/Pollora/framework/compare/v13.34.2...v13.34.3) - 2026-10-05

### Fixed
- Each discovered item is applied once per request. The engine applied every discovery again for each scanned location and module engine (5 times per request on a typical project), with all the items found so far: each `#[Schedule]` task had 5 callbacks on its cron hook and **ran 5 times per cron run**, each `#[WpRestRoute]` route exposed 5 identical endpoints, `cron_schedules` and `init` collected duplicate closures. Measured on a project with WooCommerce: `apply()` time per request 6.6 ms → 2.6 ms, 80 fewer hook callbacks; registered post types, taxonomies, REST routes, cron events, WP-CLI commands and rendered pages unchanged (#386)

## [v13.34.2](https://github.com/Pollora/framework/compare/v13.34.1...v13.34.2) - 2026-10-05

### Security
- `pollora/colt` `^10.0.1`: meta and option values are unserialized without allowing classes, so a tampered value can no longer instantiate PHP objects (Pollora/colt#1)
- The `api_plugins` bootstrap reads `active_plugins` with `unserialize()` restricted to no classes: a tampered option can no longer instantiate PHP objects (#382)

### Fixed
- `saveMeta()` and `createMeta()` on `Pollora\Models` go through `update_metadata()` / `add_metadata()` when WordPress is loaded: sanitize callbacks, meta hooks and object cache invalidation now apply, and `get_post_meta()` no longer returns the previous value under a persistent object cache (Pollora/colt#1)
- `$post->acf`, `$term->acf` and `$user->acf` no longer end in a fatal error: Colt's ACF trait referenced a class that was never shipped (Pollora/colt#1)

## [v13.34.1](https://github.com/Pollora/framework/compare/v13.34.0...v13.34.1) - 2026-10-02

### Removed
- The `use_default_wp_theme_directory` key of `config/wordpress.php`. Nothing ever read it: setting it to `true` changed nothing, and themes always live in `themes/` (#297)

### Fixed
- Under WordPress 7, a classic theme's block styles and `global-styles` were printed at the bottom of every page, after the content they style — a flash of unstyled content on slow connections — and the head kept two empty placeholders. WordPress loads those styles on demand at `wp_footer`, then moves them back into the head through its template enhancement output buffer, which only starts when `template-loader.php` includes a template: a Blade page includes none. A `WordPressTemplateEnhancement` middleware now plays the buffer's part on the response — `wp_template_enhancement_output_buffer_started` before the view renders, the `wp_template_enhancement_output_buffer` filter and `wp_finalized_template_enhancement_output_buffer` on the HTML — for `Route::wp()` routes and the template hierarchy, so any plugin built on that filter sees Pollora's pages too. A plain Laravel route that prints `wp_head()`/`wp_footer()` can add the middleware itself
- `pollora:install` without a terminal — `--no-interaction`, CI, or `pollora new` driving it — stopped on "Site title is required" unless every option was passed: the prompts it could not show were still required. It now fills what is missing with a working local site — the project name as title (the first label of the `APP_URL` host: under DDEV the directory is always `html`), `admin` at `admin@<APP_URL host>`, a generated password shown once (also with `--install`), `en_US`, not indexed — and keeps every option it is given
- `pollora:status` and the dashboard reported a post type or taxonomy under a slug derived from its class name, ignoring the attribute: `#[PostType('synthese-presse')] class SyntheseDePresse` was listed as `synthese-de-presse`, a post type that does not exist, and its `plural` label was ignored too. Both read the attribute now, through `PostType::resolveSlug()` / `Taxonomy::resolveSlug()`, the rule discovery registers with, so they cannot disagree again (#298)

## [v13.34.0](https://github.com/Pollora/framework/compare/v13.34.0-beta.2...v13.34.0) - 2026-09-30

The first stable release since v13.4.4, on Laravel 13.34. It gathers every change from v13.32.0-beta to v13.34.0-beta.2 below; coming from 13.4, read those entries, or run Nectar's `upgrade-pollora-v13-32` prompt ([full comparison](https://github.com/Pollora/framework/compare/v13.4.4...v13.34.0)).

### Fixed
- A plugin with neither `app/` nor `src/` — one that only ships blocks, which need no class — was discovered from its root, `node_modules` included: 2 to 7 seconds on every request in debug mode, measured on a blocks-only plugin with a Vite build. Its other top-level directories are scanned instead, never `node_modules`, `vendor`, `bower_components`, `build`, `dist`, `public`, `resources`, `assets`, `languages`, `lang` or a hidden directory

## [v13.34.0-beta.2](https://github.com/Pollora/framework/compare/v13.34.0-beta...v13.34.0-beta.2) - 2026-09-30

### Added
- `pollora:doctor`: checks a project for the failures that stay silent — the site renders, the command exits 0 — and prints, under each, the command that fixes it. Each check comes from a failure met in practice: WordPress core not patched or `__()` not Pollora's; `patches.lock.json` missing or older than the framework's patches; `.env` names Pollora does not read (`DB_NAME`, `WP_HOME`…) or MySQL settings on a sqlite connection; configuration or routes cached outside production; classes missing from the discovery cache; for the theme, every Pollora plugin and every enabled module: a build missing, written to another folder than Pollora reads, or a hot file pointing at a dev server that is stopped or not exposed, a directory symlinked under another name, `%theme_*%`/`%plugin_*%` placeholders or `.stub` files left from a copied template, blocks still in the legacy `resources/blocks`; pattern files WordPress never registers or has not cached; `Route::wp()` routes answering in place of a block theme's templates. `--json` for scripts; exits 1 on an error
- The same checks in WordPress's **Site Health** (Tools › Site Health), with a "Pollora" badge, plus one that only a web request can make: every block of the theme, the Pollora plugins and the modules is registered. That is the boot a visitor gets — blocks were once registered under WP-CLI but not over HTTP, which a console check would have passed

### Fixed
- Deleting a navigation menu (`wp menu delete`, or the Menus screen) raised a `TypeError` once the menu was already gone: `delete_nav_menu` is the `delete_{$taxonomy}` hook, which passes the term ID first, and the listener expected a `WP_Term`. `MenuDeleted` now receives the menu WordPress copied before deleting it
- While Vite ran hot, its client was enqueued with WordPress's version appended (`@vite/client?ver=7.1.2`). The modules Vite serves import `/@vite/client` by its bare URL, so the browser loaded the client twice, as two modules with two HMR connections. It is enqueued with no version, like the entries (regression from v13.34.0-beta)
- On a branch install (`dev-develop`, `13.x-dev`), Site Health and the admin notice announced "Pollora 13.4.4 is available": `version_compare()` ranks a branch name below every release. A development build is no longer compared with releases — Site Health says it is one, the notice stays silent — and the dashboard, the admin menu badge and `pollora:status` share that one rule, which they each duplicated and missed `13.x-dev`. Site Health's info tab now labels the compared version "Latest stable version", since pre-releases are not counted

### Changed
- `pollora:make:theme` activates the generated theme only where the site needs one. A site with no usable theme — a first install — gets it without a question; a site that already has one keeps it unless the answer is "yes", now the default "no", so `--no-interaction` never replaces a working theme. `--activate` and `--no-activate` settle it without asking, and `pollora:install` passes `--activate`. Activation goes through `switch_theme()`, which fires `switch_theme` and `after_switch_theme`; the options were written directly before, so those hooks never ran

## [v13.34.0-beta](https://github.com/Pollora/framework/compare/v13.32.0-beta.9...v13.34.0-beta) - 2026-09-29

The framework's version tracks Laravel's: this beta requires Laravel 13.34.

### Added
- A third `pollora:make:theme` template, **Magazine** (`magazine` → `pollora/theme-buzz`): a Full Site Editing block theme whose templates, parts and patterns are edited in the Site Editor, next to `default` and `ecommerce`. The missing-theme page and admin notice list it too

### Fixed
- A Vite script was printed before WordPress's import map, which Firefox and Safari then ignore: any WordPress script module on the page — the navigation block's, the search block's, the image lightbox's — failed on `@wordpress/interactivity was a bare specifier`, so the block did nothing. Chromium tolerates the order, which hid it. In a classic theme the import map is always in the footer, so any theme with a Vite script in the head was affected as soon as an author inserted such a block. The Vite client of the dev server had the same problem
- A block theme's own `404.html` answered with HTTP 200. WordPress core resolves it to `wp-includes/template-canvas.php`, which is never a Blade view, so it always rendered through `FrontendController`'s raw-PHP-template branch — the only branch that never looked at `is_404()`. Measured on a fresh block theme: right content, wrong status
- A real 404 lost its `error404` body class, and every page served by the template hierarchy carried a meaningless one built from its path (`any-no-such-page`). The `WordPressBodyClass` middleware was meant for Laravel routes, which WordPress's own resolution calls a 404, but it only ran on WordPress routes — the `{any}` fallback included — where WordPress's verdict is the right one. So it did the opposite of its job on both sides: a Laravel route (`Route::get('/dashboard/{tab}')`) kept `error404`, `is_404()` true and a "Page not found" title over its 200 response

### Changed
- Requires Laravel 13.34: `illuminate/*` `^13.34` (was `^13.32`). Measured on `laravel/framework` v13.34.0: the full suite, Pint, PHPStan and Rector pass unchanged
- The `WordPressBodyClass` middleware is replaced by a `RouteMatched` listener, `ApplyApplicationRouteContext`, which runs on every route: a route WordPress answers (`Route::wp()` and the template-hierarchy fallback, both flagged `isWordPressRoute()`) keeps WordPress's classes and verdict untouched; any other route has `is_404()` cleared and its URI segments added as body classes (`dashboard tab-settings`). A middleware could not do this — Laravel routes are not given the WordPress middleware stack
- On the front end and in the admin, a Vite script is enqueued as a WordPress script module (`wp_enqueue_script_module`), so WordPress places it after its import map, as it does its own modules: in the head of a block theme (the footer with `loadInFooter()`), always in the footer of a classic theme. What a module cannot take — `dependencies()`, `localize()`, `inline()` — goes on a classic companion script, `{handle}-data`, which runs before the module. The editor, login screen and Customizer are unchanged

## [v13.32.0-beta.9](https://github.com/Pollora/framework/compare/v13.32.0-beta.8...v13.32.0-beta.9) - 2026-09-28

### Added
- `<InnerBlocks />` in a block's `render.blade.php`: the block's inner blocks are edited in place in the editor, inside the rendered template, and printed in place of the tag on the page, in a `div` carrying the tag's `class` (`pollora-inner-blocks` by default). The tag takes Gutenberg's inner blocks options — `allowedBlocks`, `template`, `templateLock`, `orientation`… — as attributes, JSON for arrays and objects. Modelled on ACF's `<InnerBlocks />`. A `render.php` template gets it too
- The block editor runtime `window.pollora.blocks` (script handle `pollora-block-editor`, a dependency of the editor script of every block with a `render` template): `bladeEdit(metadata)` previews the template through the core block-renderer route and makes its `<InnerBlocks />` editable; `save` stores the inner blocks. Shipped inline, as nothing under `vendor/` has a public URL
- `$isPreview` in a block template: true while it renders for the editor's preview
- `develop` is aliased to `13.x-dev` (`extra.branch-alias`). Without it, `dev-develop` satisfied no version constraint: a project on `dev-develop` could not install a package requiring `pollora/framework` `^13.0` — `pollora/nectar` 1.1 does — without an inline alias of its own

### Fixed
- `pollora:make:block --inner-blocks` made a dynamic block whose inner blocks were neither editable in its preview, saved in the post, nor printed by its template. It now writes `<InnerBlocks />` in `render.blade.php`
- `TemplateMarker`'s documentation no longer says every response goes through `template_include`: responses from `Route::wp()` and Laravel routes bypass it and carry no marker, as the hierarchy browser tests assert
- "Validate Changelog" no longer fails the pull request of every release. It required a non-empty `[Unreleased]` on any pull request to `main`, which a release from `develop` or `release/*` has emptied into the version's section by construction; it now checks hotfixes only

### Changed
- `pollora:make:block` generates dynamic blocks on the runtime: `edit.jsx` is `window.pollora.blocks.bladeEdit(metadata)` instead of `ServerSideRender`, `save` is `window.pollora.blocks.save` instead of `() => null`, and `@wordpress/server-side-render` is no longer added to `package.json`. Blocks made before keep working
- The framework no longer declares the wpackagist repository, from which it required nothing. Composer still fetched its metadata on every install, so a wpackagist network error failed the build — measured: the nightly of 2026-09-27 failed "Code Quality" on `curl error 56` from wpackagist.org
- The installed package no longer carries the test suite, the CI workflows and the tooling configs: `.gitattributes` leaves them out of the archive Composer installs. The browser tests stay reachable by cloning the repository, which is how theme CI fetches them

### Removed
- `get`, a grep output committed by mistake

## [v13.32.0-beta.8](https://github.com/Pollora/framework/compare/v13.32.0-beta.7...v13.32.0-beta.8) - 2026-09-25

No change to the framework's code: this beta ships the browser test suite that now guards it, 73 tests per browser.

### Added
- Browser tests of the template hierarchy. Two fixture themes: one has a template for every case, the other `index.blade.php` alone. Each URL is read in the framework's template marker, in the template's own output and in the HTTP status: `front-page`, `home`, `page-{slug}`, `page-{id}`, a custom page template, `page`, `single`, `single-{type}`, `archive-{type}`, `archive`, `taxonomy-{tax}-{term}`, `taxonomy-{tax}`, `category-{slug}`, `tag`, `author-{nicename}`, `date`, `search`, and `404` with a 404 status; a Blade view over a PHP template of the same name; `Route::wp()` and Laravel routes over the hierarchy. With `index` alone every case falls back to it, and an unknown URL still answers 404. Replayed: without the 404 view, or with the marker disabled, the suite fails
- Browser tests of the framework's features, through a fixture plugin that declares each by attribute and checks it by its effect: `#[Filter]` and `#[Action]`, a `#[PostType]` (REST type, archive, admin menu), `#[WpRestRoute]` (a public route; an `IsAdmin` one that refuses a visitor and answers an administrator), `#[Ajax]` for visitors and logged-in users, a script enqueued through the `Asset` facade, every script and stylesheet of the home page loading with nothing over plain `http://`, `get_theme_file_uri()` giving the active theme's built entry a URL that answers, no server path in the page a visitor gets, the theme's menu locations and login screen, and `__()` sending a text domain to WordPress's catalogues and replacements to Laravel. Replayed: with the v13.32.0-beta.6 theme URI fix undone, or with WordPress's side of `__()` disabled, the suite fails
- The browser tests run in Firefox and WebKit as well as Chromium every night, and on a manual run of the workflow (`E2E_BROWSERS`). Pull requests keep Chromium alone. The schedule runs from `main`, so it starts with this release

## [v13.32.0-beta.7](https://github.com/Pollora/framework/compare/v13.32.0-beta.6...v13.32.0-beta.7) - 2026-09-24

### Fixed
- `pollora:make:block` refuses a theme or plugin that cannot build a block, and says why. A plugin made without assets has neither `package.json` nor `vite.config.js`: the block was written anyway, could not be built, and the `npm install` the command advised walked up to the site's own `package.json` and installed there. It now stops before writing anything and names the missing files
- `pollora:make:plugin --asset` includes the asset files. The option takes a value, so the bare flag read as null, which the defaults applied without a terminal turned into `false`: `--asset` alone left the assets out. `--asset=false` still leaves them out, and the other options keep their defaults
- A block title holding a quote (`--title="Owner's Hero"`) no longer breaks the JavaScript `pollora:make:block` writes. `render.blade.php` escaped it; `edit.jsx` and `save.jsx` printed it raw inside a single-quoted string, so the block failed to build
- A block's editor script depends on every WordPress script its code imports. `BlockRegistrar` gave each editor script a fixed list — `wp-blocks`, `wp-element`, `wp-block-editor`, `wp-i18n` — while `@roots/vite-plugin` turns every `@wordpress/*` import into a global and records the matching handles in `editor.deps.json`. A block importing `@wordpress/components` or `@wordpress/server-side-render` worked only when something else happened to load that script. The handles in the build's `editor.deps.json` are now added to the defaults; in dev mode, which has no build, the defaults stand
- Blocks shipped by themes, plugins and modules exist in the block editor, on the page and in the REST API. They were registered by a `BlocksServiceProvider` of the module's own, and over HTTP no shape of that provider works: WordPress is loaded, and `init` has fired, before theme and plugin providers boot, so the provider `pollora:make:block` wrote — hooking `init` from `boot()` — was never called, and a REST request is answered before those providers boot at all. Blocks existed in WP-CLI only, which is where they had been checked. Measured on a fresh install: theme-default's `hero` and `call-to-action` were missing from the editor and from `/wp/v2/block-types`. The framework now registers every module's `resources/views/blocks` (and the former `resources/blocks`) itself on `init`, from a hook set while it boots — before WordPress loads — and creates the asset container of a Laravel module that ships blocks. `pollora:make:block` no longer writes a provider. A `BlocksServiceProvider` kept from an earlier release is harmless: a block WordPress already holds is skipped. Covered by browser tests that insert each block in the editor, reload it, and find it on the published page
- Themes and plugins keep working on a site whose autoloader was dumped with `--classmap-authoritative`. Their namespaces (`Theme\…`, `Plugin\…`) were added at runtime to Composer's root loader, and an authoritative loader answers from its classmap alone: it never looked at them, so every front-end page answered 404 (`Class "Theme\Apiary\Walkers\MenuPrimary" not found`) while `wp-login.php` still worked. Module namespaces now live in a class loader of their own, registered after Composer's, and shared by the theme and plugin autoloaders under the `ModuleAutoloader::CLASS_LOADER` container key. It has no vendor directory, so it stays out of `ClassLoader::getRegisteredLoaders()` and cannot move `Application::inferBasePath()`. Measured on a live site in both modes: pages, the PHPUnit and HTTP suites, and the theme sweep all pass. The root loader is no longer bound to `Composer\Autoload\ClassLoader` in the container as a side effect
- `php artisan test`, and anything else that asks Laravel for its base path, no longer resolves it to a plugin's directory. `ModuleAutoloader::register()` registered the root Composer loader again although it was already active, and Composer answers that by moving it to the end of `ClassLoader::getRegisteredLoaders()` — behind the loader of any plugin shipping its own `vendor/`, such as Query Monitor. `Application::inferBasePath()` reads the first entry of that list, so on such a site 38 of 40 of its PHPUnit tests failed opening `plugins/query-monitor/bootstrap/app.php`. Composer's `InstalledVersions::getRootPackage()` read the same list and reported the plugin as the root package, over HTTP, in artisan and in WP-CLI. Namespaces added with `addPsr4()` take effect on an active loader, so the loader is now only registered when it is not already. The bug dates back to June 2025 and shipped in v13.4.4
- `patches/mockery-php84-nullable.patch` is back on `main`, byte for byte as v13.4.4 shipped it. The v13.4.x line declares that patch by the URL `refs/heads/main/patches/mockery-php84-nullable.patch`, and a released `composer.json` cannot change; removing the file from `main` on 2026-09-17 made the URL answer 404, and composer-patches v1 skips a patch it cannot fetch with only a warning — so every v13.4.x install since went without it. Nothing on the 13.32 line references the file: its patch URLs are pinned to commits
- The patches this package asks its consumers to apply are pinned to the commit that produced them, instead of being served from `refs/heads/main`. A released `composer.json` cannot be changed, so a branch URL moves under every version already published: removing `mockery-php84-nullable.patch` from `main` on 2026-09-17 turned it into a 404 for every v13.4.x install, which composer-patches v1 skips with only a warning. With composer-patches v2 a moved patch is worse — its checksum no longer matches `patches.lock.json` and the install fails. A **Patches** CI job now proves each URL pins a commit in the branch's history whose file matches the working tree. Versions already released keep the URLs they shipped with
- Generators write a theme's, plugin's or module's classes where its autoloader reads them. The autoloaders map a namespace onto `app/` when it exists and onto `src/` otherwise — one directory, never both — while every `--plugin`, `--theme` and `--module` generator wrote into `app/` unconditionally. On a target keeping its classes in `src/`, that was worse than one misplaced file: creating `app/` flipped the autoloader and discovery over to it, and every class already in `src/` stopped being loaded. They now resolve the directory with the autoloaders' own precedence, and a target with neither still gets `app/`
- The `BlocksServiceProvider` the scaffolder writes registers its blocks on `init`, which is when WordPress accepts block registrations at all. It called `registerDirectory()` straight from `boot()`, and providers boot before WordPress has defined `register_block_type()` — `BlockRegistrar` answers that by returning immediately, with no error and no notice, so the blocks simply never existed. `theme-default` hit this, shipped v1.4.0 with it, and fixed its own copy by hand; the stub that writes every other provider kept the broken shape, so each new theme and plugin was born with the bug
- `pollora:make:block` writes the `BlocksServiceProvider` when the target has none. A blocks directory that is not empty was taken as proof that the infrastructure was in place, and it usually is — but blocks registered by something else live there too, and none of them needs the provider that registers Vite-built blocks. In a target like `theme-apiary`, whose only shipped block is an ACF one, every block ever scaffolded was written to disk, built by Vite, and registered by nobody: nothing failed, nothing reached the log, and the block simply never appeared in the editor — permanently, since the directory only gets less empty. Measured on a live site, then pinned end to end in the theme's CI. Only the provider is created; the npm dependencies and the initial Vite patch still belong to a genuinely first block
- `composer install` and `composer update` no longer fail on a Pollora site that has no terminal. `pollora:env:setup` runs from composer's `post-autoload-dump` hook, so it fires inside container builds, deployments and CI; when the database was not reachable it reached for Laravel Prompts, which throws where there is nothing to prompt, and composer exited 1 reporting a prompting problem rather than a database one. Nothing in that command is a gate — `pollora:install` is what refuses to continue without a database — so with no terminal it now names what is wrong, names the command to run once the database is up, and lets composer finish

### Changed
- The block editor shows what the page shows for a block made by `pollora:make:block`. A dynamic block's `edit.jsx` renders `render.blade.php` through `ServerSideRender` for the block's current attributes, and a static block's mirrors the markup `save.jsx` writes; both used to show a placeholder ("… – Block Editor") the page never displayed. A block with `--inner-blocks` keeps the `InnerBlocks` editor, which a server render cannot provide. `@wordpress/server-side-render` joins the npm dependencies added with a first block. Checked in a browser: the preview holds the rendered text, and the old stub fails the same test
- `cweagans/composer-patches` moves from `^1.7` to `^2.0`. It is the plugin that applies the WordPress l10n patch — renaming WordPress's `__()` to `__wp()` so Laravel's helper can have the name — on every Pollora site. Measured before merging: on a fresh install, v2 resolves the framework's patch as a dependency patch and applies it, and the skeleton's check that WordPress core carries it passes. Two things change for a project. v2 writes a `patches.lock.json` recording each patch's sha256, and on a machine without the patch cached it downloads the patch again and fails with `HashMismatchException` when the file no longer matches — so a patch served from a moving URL can break fresh installs of a project that locked an earlier version. And the skeleton's `enable-patching` option is no longer read: v1.7.3 checked it and defaulted to off, v2 resolves dependency patches unconditionally
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