<?php

declare(strict_types=1);

use Pollora\Attributes\Action as ActionAttribute;
use Pollora\Attributes\Filter as FilterAttribute;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Adapter\Out\WordPress\Filter;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

final class DiscoveredHooks
{
    /** @var list<string> */
    public array $calls = [];

    #[ActionAttribute('init')]
    public function boot(): void
    {
        $this->calls[] = 'boot';
    }

    #[ActionAttribute('save_post', priority: 99)]
    #[ActionAttribute('edit_post', priority: 20)]
    public function syncPost(int $postId, object $post): void
    {
        $this->calls[] = sprintf('syncPost(%d, %s)', $postId, $post::class);
    }

    #[FilterAttribute('the_title')]
    public function title(string $title): string
    {
        return 'modified_'.$title;
    }

    #[FilterAttribute('the_content', priority: 5)]
    public function content(string $content): string
    {
        return $content;
    }

    public function notAHook(): void
    {
        $this->calls[] = 'notAHook';
    }
}

beforeEach(function (): void {
    $this->actions = new Action;
    $this->filters = new Filter;
    $discovery = new HookDiscovery($this->actions, $this->filters);
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(DiscoveredHooks::class)), new ReflectionCache);
    $discovery->apply();
});

/**
 * The registration made on a hook: its method, priority and accepted arguments.
 *
 * @return array{method: string, priority: int, args: int}
 */
function hookRegistration(Action|Filter $hooks, string $hook): array
{
    $registration = $hooks->callbacks($hook)[0];

    return ['method' => $registration['callback'][1], 'priority' => $registration['priority'], 'args' => $registration['args']];
}

it('registers each #[Action] with its priority, 10 by default', function (): void {
    expect(hookRegistration($this->actions, 'init'))->toBe(['method' => 'boot', 'priority' => 10, 'args' => 0])
        ->and(hookRegistration($this->actions, 'save_post'))->toBe(['method' => 'syncPost', 'priority' => 99, 'args' => 2])
        ->and(hookRegistration($this->actions, 'edit_post'))->toBe(['method' => 'syncPost', 'priority' => 20, 'args' => 2]);
});

it('registers each #[Filter] with its priority, 10 by default', function (): void {
    expect(hookRegistration($this->filters, 'the_title'))->toBe(['method' => 'title', 'priority' => 10, 'args' => 1])
        ->and(hookRegistration($this->filters, 'the_content'))->toBe(['method' => 'content', 'priority' => 5, 'args' => 1]);
});

it('calls the method of an instance of the declaring class', function (): void {
    $callback = $this->filters->callbacks('the_title')[0]['callback'];

    expect($callback[0])->toBeInstanceOf(DiscoveredHooks::class)
        ->and($callback('Hello'))->toBe('modified_Hello');
});

it('registers nothing for a method without hook attribute', function (): void {
    $registrations = array_merge(...array_values($this->actions->all()), ...array_values($this->filters->all()));

    expect(array_map(fn (array $registration): string => $registration['callback'][1], $registrations))
        ->toHaveCount(5)
        ->not->toContain('notAHook');
});
