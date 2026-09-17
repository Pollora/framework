<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Abilities\Application\Service\RegisterAbilityService;
use Pollora\Abilities\Domain\Contracts\AbilityHandler;
use Pollora\Abilities\Domain\Model\Ability as AbilityModel;
use Pollora\Abilities\Domain\Model\AbilityCategory;
use Pollora\Abilities\Domain\Model\Behaviour;
use Pollora\Abilities\Factory\AbilityFactory;
use Pollora\Abilities\Factory\PendingAbility;
use Pollora\Ability\Infrastructure\Providers\AbilityServiceProvider;

/**
 * Laravel facade for the WordPress Abilities API.
 *
 * Resolves the {@see AbilityFactory} from the service container (key
 * `wp.abilities`) and proxies calls to it. Declarations are queued and published
 * on `wp_abilities_api_init`, so this may be called as early as a service
 * provider's `boot()`.
 *
 * Declare the category before the abilities that file under it:
 *
 *     Ability::category('acme-content', 'Editorial', 'Posts and pages.');
 *
 *     Ability::define('acme/get-posts')
 *         ->description('Returns the most recent posts, newest first.')
 *         ->category('acme-content')
 *         ->input(fn (SchemaBuilder $schema) => $schema
 *             ->integer('limit', 'How many posts to return.', default: 10, maximum: 100))
 *         ->can(fn (Input $input): bool => current_user_can('edit_posts'))
 *         ->using(fn (Input $input): array => …);
 *
 * For anything beyond a couple of lines, prefer a class implementing
 * {@see AbilityHandler} decorated with `#[Ability]` — it is discovered
 * automatically and is far easier to test.
 *
 * @method static PendingAbility define(string $name) Begin declaring an ability; nothing is queued until a body is supplied.
 * @method static AbilityCategory category(string $slug, string $label = '', string $description = '') Declare a category abilities can be filed under.
 * @method static AbilityModel handle(string $name, AbilityHandler $handler, string $category, string $label = '', string $description = '', Behaviour $behaviour = Behaviour::Reads) Declare an ability implemented by a handler object.
 * @method static RegisterAbilityService service() The service declarations are queued on.
 *
 * @see AbilityFactory
 * @see AbilityServiceProvider
 */
class Ability extends Facade
{
    /**
     * Get the container binding key for the underlying service.
     *
     * @return string The `wp.abilities` key resolving to {@see AbilityFactory}.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.abilities';
    }
}
