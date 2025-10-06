<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Factories;

use Illuminate\Support\Str;
use Pollora\Entity\Adapter\Out\WordPress\PostTypeRegistryAdapter;
use Pollora\Entity\Application\Service\EntityRegistrationService;
use Pollora\Entity\Domain\Model\PostType as EntityPostType;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;

/**
 * Implementation of the PostTypeFactory interface.
 *
 * This factory creates PostType instances from the Entity namespace,
 * ensuring consistency across the framework while maintaining the
 * hexagonal architecture principles.
 */
class PostTypeFactory implements PostTypeFactoryInterface
{
    /**
     * Create a new post type instance.
     *
     * This method creates a PostType instance from the Entity namespace,
     * which provides the full WordPress functionality while maintaining
     * architectural separation.
     *
     * @param  string  $slug  The post type slug
     * @param  string|null  $singular  The singular label for the post type
     * @param  string|null  $plural  The plural label for the post type
     * @param  array<string, mixed>  $args  Additional arguments
     * @return object The created PostType instance
     */
    public function make(string $slug, ?string $singular = null, ?string $plural = null, array $args = []): object
    {
        // Generate singular name if not provided
        if ($singular === null) {
            $singular = $this->generateSingularName($slug);
        }

        // Generate plural name if not provided
        if ($plural === null) {
            $plural = $this->generatePluralName($singular);
        }

        // Create the Entity PostType instance directly (without auto-registration)
        $postType = new EntityPostType($slug, $singular, $plural);
        $postType->init();

        // Apply additional arguments if provided
        if ($args !== []) {
            $postType->setRawArgs($args);
        }

        // Register with WordPress using the entity registration service
        $registry = new PostTypeRegistryAdapter;
        $registrationService = new EntityRegistrationService($registry);
        $registrationService->registerEntity($postType);

        return $postType;
    }

    /**
     * Generate a singular name from a slug.
     *
     * @param  string  $slug  The post type slug
     * @return string The generated singular name
     */
    private function generateSingularName(string $slug): string
    {
        // Convert to snake_case first
        $snakeCase = Str::snake($slug);

        // Then humanize it (convert snake_case to words with spaces and capitalize first letter)
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));

        // Ensure it's singular
        return Str::singular($humanized);
    }

    /**
     * Generate a plural name from a singular name.
     *
     * @param  string  $singular  The singular name
     * @return string The generated plural name
     */
    private function generatePluralName(string $singular): string
    {
        return Str::plural($singular);
    }
}
