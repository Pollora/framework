# Template Hierarchy System

The Template Hierarchy system provides a fully extensible and framework-agnostic solution for resolving WordPress template hierarchies and rendering templates using different technologies.

## Features

- Core WordPress routing logic
- Override capabilities for plugin-specific templates (e.g., WooCommerce)
- Support for PHP and Blade view templates
- Full testability with unit tests
- Extensible through plugins and custom components

## Architecture

The system follows a hexagonal architecture pattern to ensure clean separation of concerns:

```
src/TemplateHierarchy/
├── Domain/               # Domain entities, contracts, and business logic
├── Application/          # Application services
├── Infrastructure/       # Adapters for WordPress, WooCommerce, etc.
└── TemplateHierarchy.php # Main facade
```

## Usage

### Basic Usage

The `TemplateHierarchy` class is automatically registered by the framework and hooks into WordPress's template system. It provides a more powerful and flexible alternative to WordPress's built-in template hierarchy.

### Registering Custom Template Sources

You can register custom template sources for plugins or custom post types:

```php
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateResolverInterface;
use Pollora\TemplateHierarchy\Infrastructure\Services\AbstractTemplateSource;

class MyPluginTemplateSource extends AbstractTemplateSource
{
    public function __construct()
    {
        $this->name = 'my-plugin';
        $this->priority = 5; // Higher priority than even WooCommerce
    }

    public function getResolvers(): array
    {
        return [
            new MyCustomResolver(),
        ];
    }
}

// Register with the template hierarchy
$app->make(\Pollora\TemplateHierarchy\TemplateHierarchy::class)
    ->registerSource(new MyPluginTemplateSource());
```

### Custom Template Resolvers

Create a custom template resolver by extending `AbstractTemplateResolver`:

```php
use Pollora\TemplateHierarchy\Infrastructure\Services\AbstractTemplateResolver;

class MyCustomResolver extends AbstractTemplateResolver
{
    public function __construct()
    {
        $this->origin = 'my-plugin';
    }

    public function applies(): bool
    {
        return is_my_custom_page();
    }

    public function getCandidates(): array
    {
        $templates = [
            'my-plugin/custom-template.php',
            'my-plugin/fallback.php',
        ];

        $candidates = [];
        foreach ($templates as $template) {
            // Create both PHP and Blade candidates for each template
            $candidates = array_merge(
                $candidates,
                $this->createPhpAndBladeCandidates($template)
            );
        }

        return $candidates;
    }
}
```

### Simplifying Custom Template Registration

For simple cases, you can register a template handler function without creating a full resolver class:

```php
$app->make(\Pollora\TemplateHierarchy\TemplateHierarchy::class)
    ->registerTemplateHandler('my_custom_type', function ($queriedObject) {
        return [
            "my-plugin/template-{$queriedObject->slug}.php",
            'my-plugin/template.php',
        ];
    });
```

### Custom Renderers

You can add support for additional template engines by creating a custom renderer:

```php
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

class TwigTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(private readonly \Twig\Environment $twig)
    {
    }

    public function supports(string $type): bool
    {
        return $type === 'twig';
    }

    public function resolve(TemplateCandidate $candidate): ?string
    {
        if (!$this->supports($candidate->type)) {
            return null;
        }

        try {
            $this->twig->load($candidate->templatePath);
            return $candidate->templatePath;
        } catch (\Twig\Error\LoaderError $e) {
            return null;
        }
    }
}

// Register with the template hierarchy
$app->make(\Pollora\TemplateHierarchy\TemplateHierarchy::class)
    ->registerRenderer(new TwigTemplateRenderer($twig));
```

## Blade Templates

The system automatically supports Blade templates. If you have a template at:

```
themes/my-theme/woocommerce/archive-product.blade.php
```

It will be resolved as the Blade view:

```
woocommerce.archive-product
```

## Template Hierarchy

The system follows the same template hierarchy as WordPress but allows for more flexibility:

1. Plugin-specific templates (WooCommerce, etc.)
2. Theme-specific templates
3. WordPress core templates
4. Fallback to index.php

## Integration with WooCommerce

The system seamlessly integrates with WooCommerce's template hierarchy, allowing you to use WooCommerce templates in your theme with Blade support.

## Extending the System

You can listen for various actions and filters to extend the template hierarchy:

- `pollora/template_hierarchy/register_sources` - Register custom template sources
- `pollora/template_hierarchy/register_renderers` - Register custom renderers
- `pollora/template_hierarchy/template_paths` - Modify the template search paths
- `pollora/template_hierarchy/candidates` - Modify the final template candidates
- `pollora/template_hierarchy/hierarchy` - Modify the full template hierarchy
- `pollora/template_hierarchy/conditions` - Add custom WordPress conditional tags
- `pollora/template_hierarchy/{type}_templates` - Filter templates for a specific type 