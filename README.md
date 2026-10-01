<p align="center">
  <a href="https://pollora.dev">
    <img src="resources/images/pollora-logo.svg" width="400" alt="Pollora">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/framework"><img src="https://img.shields.io/packagist/v/pollora/framework?include_prereleases" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/pollora/framework"><img src="https://img.shields.io/packagist/dt/pollora/framework" alt="Total Downloads"></a>
  <a href="https://codecov.io/gh/Pollora/framework"><img src="https://codecov.io/gh/Pollora/framework/branch/main/graph/badge.svg" alt="Coverage"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/framework" alt="License"></a>
</p>

## About Pollora

**Pollora is the Laravel framework for WordPress.** WordPress runs inside a Laravel application: the front end uses Laravel routing, controllers, Blade and Eloquent, while the WordPress admin, database, editors' workflow and plugins keep working as usual. Hooks, post types, taxonomies and REST routes are declared with PHP 8 attributes and registered by auto-discovery.

[Website](https://pollora.dev) · [Documentation](https://pollora.dev/getting-started/installation/) · [Why Pollora](https://pollora.dev/why/) · [How Pollora compares with Acorn, Sage, Radicle and Corcel](https://pollora.dev/compare/) · [1-minute tour](https://www.youtube.com/watch?v=Wk1VzPapqM8)

```php
use Pollora\Attributes\Filter;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\Supports;

#[PostType]
#[HasArchive]
#[Supports(['title', 'editor', 'thumbnail'])]
class Book {}

class Seo
{
    #[Filter('document_title_parts')]
    public function title(array $parts): array { /* ... */ return $parts; }
}
```

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

Full documentation is available at **[pollora.dev](https://pollora.dev)**.

## Installation

Create a project with the [Pollora CLI](https://github.com/Pollora/cli), or with Composer through the [skeleton](https://github.com/Pollora/pollora). See the [installation guide](https://pollora.dev/getting-started/installation/):

```bash
composer global require pollora/cli
pollora new my-project --ddev

# or
composer create-project pollora/pollora my-project
```

## Requirements

- PHP 8.4+ for a new project (the skeleton's lock file ships Symfony 8; this package alone accepts PHP 8.3)
- Laravel 13.34 (Pollora's version numbers follow the Laravel release it is built on)
- WordPress 7.1+

## Learn more

- [WordPress hooks with PHP 8 attributes](https://pollora.dev/guides/wordpress-hooks-php-attributes/)
- [Custom post types and taxonomies with PHP attributes](https://pollora.dev/guides/custom-post-types-php-attributes/)
- [How Pollora runs WordPress inside Laravel](https://pollora.dev/guides/how-pollora-runs-wordpress-inside-laravel/)
- [AI coding agents for WordPress projects (Nectar)](https://pollora.dev/guides/ai-coding-agents-wordpress/)

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

Pollora is open-sourced software licensed under the [MIT license](LICENSE).

Parts of the WordPress integration (`Pollora\Support\WordPress`, `Pollora\Hashing\WordPressHasher`) originate from work by Jordan Doyle, released under the 0BSD and WTFPL licenses.
