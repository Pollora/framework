<?php

declare(strict_types=1);

use Pollora\Attributes\Action as ActionAttribute;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Adapter\Out\WordPress\Filter;
use Pollora\Hook\Application\Services\AsyncDeclarationFailures;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Infrastructure\Services\AsyncAttributeRegistrar;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Psr\Log\AbstractLogger;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

require_once __DIR__.'/Fixtures/AsyncAttributeFixtures.php';

beforeEach(function (): void {
    Async::flush();
    $this->fake = Async::fake();
    $this->actions = new Action;
    $this->logger = new class extends AbstractLogger
    {
        /** @var list<string> */
        public array $errors = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->errors[] = (string) $message;
        }
    };
    $this->failures = new AsyncDeclarationFailures;
    $this->registrar = new AsyncAttributeRegistrar($this->actions, $this->logger, $this->failures);
    $this->register = function (string $class, string $method, ?string $hook = null): void {
        $reflection = new ReflectionMethod($class, $method);
        foreach ($reflection->getAttributes(ActionAttribute::class) as $action) {
            $action = $action->newInstance();
            if ($hook === null || $hook === $action->hook) {
                $this->registrar->register($action->hook, new $class, $reflection, $action->priority);
            }
        }
    };
});

afterEach(function (): void {
    Async::flush();
});

/**
 * The callback registered on a hook, as WordPress would call it.
 */
function registeredCallback(Action $actions, string $hook): mixed
{
    return $actions->callbacks($hook)[0]['callback'] ?? null;
}

it('makes an #[Action] method asynchronous with the options of its #[Async]', function (): void {
    ($this->register)(AsyncAttributeHooks::class, 'syncToCrm');

    $queued = registeredCallback($this->actions, 'save_post_event');
    expect($queued)->toBeInstanceOf(QueuedHandler::class)
        ->and($queued->priority)->toBe(20);

    $queued(42, null);

    Async::assertDispatched(AsyncAttributeHooks::class.'@syncToCrm', fn (AsyncPayload $payload, int $delay): bool => $delay === 60
        && $payload->driver === 'queue'
        && $payload->queue === 'integrations'
        && $payload->uniqueKey !== null
        && $payload->tries === 3
        && $payload->backoff === [5]
        && $payload->asUser
        && $payload->keepMissing);
});

it('keeps the hooks listed in except synchronous', function (): void {
    ($this->register)(AsyncAttributeHooks::class, 'handle');

    expect(registeredCallback($this->actions, 'save_post_event'))->toBeInstanceOf(QueuedHandler::class)
        ->and(registeredCallback($this->actions, 'save_post_page'))->toBeArray()
        ->and($this->logger->errors)->toBe([]);
});

it('records values with the capture method and queues only when the when method agrees', function (): void {
    ($this->register)(AsyncAttributeHooks::class, 'notify');
    $queued = registeredCallback($this->actions, 'transition_post_status');

    $queued('draft', 'pending');
    $queued('publish', 'draft');

    Async::assertDispatchedTimes(AsyncAttributeHooks::class, 1);
    Async::assertDispatched(AsyncAttributeHooks::class, fn (AsyncPayload $payload): bool => $payload->captured === ['from' => 'draft']);
});

it('registers a method without #[Async] synchronously', function (): void {
    ($this->register)(AsyncAttributeHooks::class, 'synchronous');

    expect(registeredCallback($this->actions, 'wp_loaded'))->toBeArray();
});

it('applies a class #[Async] to every #[Action] method, a method #[Async] replacing it', function (): void {
    ($this->register)(AsyncAttributeClassHooks::class, 'welcome');
    ($this->register)(AsyncAttributeClassHooks::class, 'audit');

    registeredCallback($this->actions, 'user_register')(1);
    registeredCallback($this->actions, 'profile_update')(1);

    Async::assertDispatched(AsyncAttributeClassHooks::class.'@welcome', fn (AsyncPayload $payload): bool => $payload->tries === 2);
    Async::assertDispatched(AsyncAttributeClassHooks::class.'@audit', fn (AsyncPayload $payload): bool => $payload->tries === 5);
});

it('registers synchronously, and logs and records why, a declaration it cannot honour', function (string $method, string $reason): void {
    ($this->register)(AsyncAttributeInvalidHooks::class, $method);

    expect(registeredCallback($this->actions, 'save_post'))->toBeArray()
        ->and($this->failures->all())->toHaveKey(AsyncAttributeInvalidHooks::class.'::'.$method.'()')
        ->and($this->failures->all()[AsyncAttributeInvalidHooks::class.'::'.$method.'()'])->toContain($reason)
        ->and($this->logger->errors)->toHaveCount(1)
        ->and($this->logger->errors[0])->toContain(sprintf('#[Async] on %s::%s() is ignored, the action runs synchronously', AsyncAttributeInvalidHooks::class, $method))
        ->and($this->logger->errors[0])->toContain($reason);
})->with([
    'unknown hook in except' => ['typo', "except names 'save_psot', which is not a hook of its #[Action] (save_post)"],
    'missing capture method' => ['missingCapture', "capture names 'missing', which is not a public method of the class"],
    'private when method' => ['privateCondition', "when names 'hidden', which is not a public method of the class"],
    'no attempt' => ['noAttempt', 'at least 1 attempt'],
    'negative backoff' => ['negativeBackoff', 'one or more delays'],
    'empty lock' => ['emptyLock', 'unique expects true or a lock of at least 1 second, 0 given'],
]);

describe('Discovery', function (): void {
    beforeEach(function (): void {
        $this->discovery = new HookDiscovery($this->actions, new Filter, $this->logger, $this->failures);
        $this->discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(AsyncAttributeHooks::class)), new ReflectionCache);
        $this->discovery->apply();
    });

    it('registers #[Action] methods asynchronously or not, as declared', function (): void {
        expect(registeredCallback($this->actions, 'transition_post_status'))->toBeInstanceOf(QueuedHandler::class)
            ->and(registeredCallback($this->actions, 'wp_loaded'))->toBeArray();
    });

    it('logs and records an #[Async] on a filter, which still registers, and an #[Async] without #[Action]', function (): void {
        expect(array_keys($this->failures->all()))->toContain(AsyncAttributeHooks::class.'::filterContent()', AsyncAttributeHooks::class.'::orphan()')
            ->and($this->logger->errors)->toContain(sprintf('#[Async] on %s::filterContent() is ignored: a filter returns a value to its caller and cannot be deferred.', AsyncAttributeHooks::class))
            ->and($this->logger->errors)->toContain(sprintf('#[Async] on %s::orphan() is ignored: the method has no #[Action] to make asynchronous.', AsyncAttributeHooks::class));
    });
});

describe('Action::handle()', function (): void {
    it('registers through the registrar, asynchronously when #[Async] says so', function (): void {
        $locator = new readonly class($this->actions, $this->logger)
        {
            public function __construct(private ActionContract $actions, private AbstractLogger $logger) {}

            public function get(string $id): mixed
            {
                return $id === ActionContract::class ? $this->actions : $this->logger;
            }
        };
        $method = new ReflectionMethod(AsyncAttributeHooks::class, 'notify');
        $attribute = new ActionAttribute('transition_post_status');

        $attribute->handle($locator, new AsyncAttributeHooks, $method, $attribute);

        expect(registeredCallback($this->actions, 'transition_post_status'))->toBeInstanceOf(QueuedHandler::class);
    });

    it('works without a logger in the service locator, and ignores a class context', function (): void {
        $locator = new readonly class($this->actions)
        {
            public function __construct(private ActionContract $actions) {}

            public function get(string $id): mixed
            {
                return $id === ActionContract::class ? $this->actions : throw new RuntimeException('Not bound');
            }
        };
        $attribute = new ActionAttribute('save_post');

        $attribute->handle($locator, new AsyncAttributeInvalidHooks, new ReflectionMethod(AsyncAttributeInvalidHooks::class, 'typo'), $attribute);
        $attribute->handle($locator, new AsyncAttributeHooks, new ReflectionClass(AsyncAttributeHooks::class), new ActionAttribute('init'));

        expect(registeredCallback($this->actions, 'save_post'))->toBeArray()
            ->and($this->actions->callbacks('init'))->toBeNull();
    });
});
