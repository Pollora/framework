# Changelog

All notable changes to the Pollora framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/Pollora/framework/compare/v13.2.0...develop)

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