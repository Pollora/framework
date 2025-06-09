# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Framework Overview

Pollora is a Laravel & WordPress integration framework that creates a "sweet blend" between the two platforms. It allows developers to use Laravel's architecture patterns, service providers, and dependency injection while maintaining WordPress functionality and compatibility.

## Development Commands

### Testing & Quality Assurance
```bash
# Run all tests and quality checks
ddev exec --dir /var/www/html/vendor/pollora/framework composer test

# Individual test commands
ddev exec --dir /var/www/html/vendor/pollora/framework composer test:unit          # Run PHPUnit tests with coverage (requires 100% coverage)
ddev exec --dir /var/www/html/vendor/pollora/framework composer test:types         # Run PHPStan static analysis
ddev exec --dir /var/www/html/vendor/pollora/framework composer test:lint          # Test code formatting with Pint
ddev exec --dir /var/www/html/vendor/pollora/framework composer test:refacto       # Test refactoring rules with Rector (dry-run)

# Development commands
ddev exec --dir /var/www/html/vendor/pollora/framework composer lint               # Fix code formatting with Pint
ddev exec --dir /var/www/html/vendor/pollora/framework composer refacto           # Apply refactoring rules with Rector
```

### Key Quality Standards
- **Test Coverage**: 100% test coverage is required (`--min=100`)
- **Static Analysis**: PHPStan level 5 with WordPress and Laravel extensions
- **Code Style**: Laravel Pint for PHP formatting, Prettier for JS/MD/YAML
- **Refactoring**: Rector with Laravel-specific rules

## Architecture & Design Patterns

### Domain-Driven Design Structure
The framework follows a strict DDD architecture with clear separation of concerns:

```
src/[Module]/
├── Application/Services/     # Application layer - use cases and orchestration
├── Domain/
│   ├── Contracts/           # Interfaces and contracts
│   ├── Models/             # Domain entities and value objects
│   ├── Services/           # Domain services and business logic
│   └── Exceptions/         # Domain-specific exceptions
├── Infrastructure/
│   ├── Providers/          # Service providers for DI container
│   ├── Repositories/       # Data persistence implementations
│   ├── Services/           # Infrastructure services (external concerns)
│   └── Adapters/          # Adapters for external systems
└── UI/
    ├── Console/           # Artisan commands
    └── Http/             # HTTP controllers and middleware
```

### Service Provider Pattern
The framework heavily relies on Laravel service providers for dependency injection and module registration. All modules register through `PolloraServiceProvider` which orchestrates the loading of:

- WordPress integration services
- Content management (PostTypes, Taxonomies)
- Theme and asset handling
- Block and pattern management
- Authentication and hashing
- Event dispatching and scheduling

### WordPress-Laravel Bridge
The `Bootstrap` class serves as the critical bridge between WordPress and Laravel:
- Manages WordPress constants and configuration
- Handles database configuration mapping
- Controls WordPress initialization flow
- Manages URL schemes and routing

### Attribute-Driven Configuration
The framework uses PHP 8 attributes extensively for declarative configuration:
- `#[PostType]` - Define custom post types
- `#[Taxonomy]` - Define custom taxonomies  
- `#[Action]` / `#[Filter]` - WordPress hooks
- `#[Schedule]` - Cron scheduling
- `#[WpRestRoute]` - REST API endpoints

### Discovery System
The `Discoverer` module automatically finds and registers components using Spatie's structure discovery:
- Scans for attributes on classes
- Registers hooks, post types, taxonomies automatically
- Enables convention-over-configuration approach

## Key Integration Points

### WordPress Integration
- **Bootstrap Process**: `WordPress\Bootstrap` manages the WordPress initialization
- **Database Bridge**: Laravel's database config maps to WordPress constants
- **Hooks System**: Laravel service container integration with WordPress hooks
- **Theme System**: Blade templates with WordPress template hierarchy

### Laravel Extensions
- **Custom Guards**: WordPress authentication integration
- **Mail Integration**: WordPress mail functions with Laravel's Mail facade
- **Hashing**: WordPress-compatible password hashing
- **Collections**: WordPress objects wrapped in Laravel collections

### Asset Management
- **Vite Integration**: Modern asset building with HMR support
- **WordPress Enqueuing**: Automatic script/style registration
- **Theme Assets**: Asset containers for theme-specific resources

## Module-Specific Notes

### Template Hierarchy
The `TemplateHierarchy` module provides WordPress template resolution with Blade support:
- Resolves templates following WordPress hierarchy rules
- Supports multiple template sources (WordPress, WooCommerce)
- Blade and PHP template rendering

### Events System
Comprehensive WordPress event dispatching for:
- Core WordPress events (posts, users, comments)
- Plugin-specific events (WooCommerce, Gravity Forms, Yoast SEO)
- Custom application events

### Content Management
- **PostTypes**: Attribute-driven custom post type registration
- **Taxonomies**: Custom taxonomy management with Laravel patterns
- **Block Patterns/Categories**: Gutenberg block integration

## Testing Approach

Tests are organized by architectural layer:
- **Unit Tests**: Domain logic and services
- **Feature Tests**: Integration testing across layers
- **Scouts**: Discovery system component testing

Use `testbench.yaml` for Laravel package testing configuration with WordPress integration.

## Important Conventions

### Namespace Structure
- All classes under `Pollora\` namespace
- Module-based organization following DDD patterns
- Strict interface segregation in Domain contracts

### Service Registration
- Each module has dedicated service providers
- Registration follows dependency order in `PolloraServiceProvider`
- WordPress services registered after Laravel core services

### WordPress Compatibility
- Patches applied to WordPress core for Laravel compatibility
- Custom function renaming to avoid conflicts (`__` function, `wp_mail`)
- Conditional WordPress loading based on environment (console vs web)

### Configuration Management
- WordPress constants managed through Laravel config
- Database configuration bridged between systems
- Environment-aware constant definition
