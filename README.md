<p align="center">
  <a href="https://github.com/Pollora/framework">
    <img src="resources/images/pollora-logo.svg" width="400" alt="Pollora">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/framework"><img src="https://img.shields.io/packagist/v/pollora/framework" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/pollora/framework"><img src="https://img.shields.io/packagist/dt/pollora/framework" alt="Total Downloads"></a>
  <a href="https://codecov.io/gh/Pollora/framework"><img src="https://codecov.io/gh/Pollora/framework/branch/main/graph/badge.svg" alt="Coverage"></a>
  <a href="https://packagist.org/packages/pollora/framework"><img src="https://img.shields.io/packagist/l/pollora/framework" alt="License"></a>
</p>

## About Pollora Framework

Pollora is a framework that bridges **Laravel** and **WordPress**, combining Laravel's architecture patterns with WordPress's content management capabilities. It allows developers to use Laravel's service providers, dependency injection, Blade templates, and Eloquent ORM while maintaining full WordPress functionality.

### Key Features

- **WordPress routing** via `Route::wp()` with template hierarchy support
- **PHP attributes** for hooks (`#[Action]`, `#[Filter]`), post types (`#[PostType]`), taxonomies (`#[Taxonomy]`), scheduling (`#[Schedule]`), and REST routes (`#[WpRestRoute]`)
- **Auto-discovery system** that scans and registers components automatically
- **Blade templates** with [Sage Directives](https://log1x.github.io/sage-directives-docs/) for WordPress data
- **Theme system** with dynamic PSR-4 autoloading, parent/child theme support, and Vite asset management
- **WordPress authentication** guard and password hashing integration
- **Event dispatching** for WordPress core, WooCommerce, Gravity Forms, and Yoast SEO
- **Module system** via [nwidart/laravel-modules](https://github.com/nwidart/laravel-modules)

## Documentation

Full documentation is available at [github.com/Pollora/documentation](https://github.com/Pollora/documentation).

## Installation

Pollora is installed via the [skeleton project](https://github.com/Pollora/pollora):

```bash
composer create-project pollora/pollora my-project
```

See the [skeleton README](https://github.com/Pollora/pollora) for detailed setup instructions.

## Requirements

- PHP ^8.3
- Laravel 13.x
- WordPress 6.9+

## Testing

```bash
composer test          # Run all checks (Rector, Pint, PHPStan, Pest)
composer test:unit     # Run Pest tests with 100% coverage requirement
composer test:types    # Run PHPStan static analysis
composer test:lint     # Check code style with Pint
composer test:refacto  # Check refactoring rules with Rector
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please report it via [GitHub Security Advisories](https://github.com/Pollora/framework/security/advisories/new). See [SECURITY.md](SECURITY.md) for details.

## Changelog

All notable changes are documented in [CHANGELOG.md](CHANGELOG.md).

## License

Pollora is open-sourced software licensed under the [GPL-2.0-or-later](LICENSE).