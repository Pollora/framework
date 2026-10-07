<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncFake;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\Exceptions\UnresolvableHandler;
use Pollora\Hook\Async\PendingAsync;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Infrastructure\Providers\AsyncServiceProvider;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class AsyncProviderService
{
    public string $name = 'from the container';
}

final class AsyncProviderModel extends Model
{
    public $exists = true;

    protected $attributes = ['id' => 7];
}

beforeEach(function (): void {
    Async::flush();
});

afterEach(function (): void {
    Async::flush();
});

function bootAsyncProvider(array $config = []): void
{
    config(['queue.default' => 'sync', 'queue.connections.sync' => ['driver' => 'sync'], 'queue.connections.database' => ['driver' => 'database'], ...$config]);
    app()->register(AsyncServiceProvider::class, force: true);
}

it('merges config/hooks.php and publishes it under the pollora-hooks tag', function (): void {
    bootAsyncProvider();

    expect(config('hooks.async.default'))->toBe('auto')
        ->and(config('hooks.async.tries'))->toBe(1)
        ->and(array_values(ServiceProvider::pathsToPublish(AsyncServiceProvider::class, 'pollora-hooks')))->toBe([config_path('hooks.php')]);
});

it('makes the Laravel queue the first driver auto tries, skipped on a sync connection', function (): void {
    bootAsyncProvider();

    expect(Async::defaultDriver())->toBe('wp-cron');

    config(['queue.default' => 'database']);

    expect(Async::defaultDriver())->toBe('queue');
});

it('sets the default driver from the configuration', function (): void {
    bootAsyncProvider(['hooks.async.default' => 'sync']);

    expect(Async::defaultDriver())->toBe('sync');
});

it('starts registrations from the configured attempts, backoff and user', function (): void {
    bootAsyncProvider(['hooks.async.tries' => 3, 'hooks.async.backoff' => [5, 50], 'hooks.async.as_user' => true]);

    expect(Async::defaults())->toBe(['tries' => 3, 'backoff' => [5, 50], 'asUser' => true]);
});

it('follows app.debug', function (bool $debug): void {
    bootAsyncProvider(['app.debug' => $debug]);

    expect(Async::isDebug())->toBe($debug);
})->with([true, false]);

it('signs closures with the application key', function (): void {
    bootAsyncProvider(['app.key' => 'base64:application-key']);

    expect(Async::closureKey())->toBe('base64:application-key');
});

it('does not sign closures with an empty application key', function (): void {
    bootAsyncProvider(['app.key' => '']);

    expect(fn () => Async::closureKey())->toThrow(UnresolvableHandler::class, 'without a signing key');
});

it('sends incidents to the Laravel log, as errors when they carry an exception', function (): void {
    $logger = new class extends AbstractLogger
    {
        /** @var list<array{0: mixed, 1: string|Stringable, 2: array<string, mixed>}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [$level, $message, $context];
        }
    };
    $exception = new RuntimeException('CRM unreachable');
    app()->instance(LoggerInterface::class, $logger);
    bootAsyncProvider();

    Async::report('Lock released', ['hook' => 'save_post']);
    Async::report($exception);

    expect($logger->records)->toBe([
        ['warning', 'Lock released', ['hook' => 'save_post']],
        ['error', 'CRM unreachable', ['exception' => $exception]],
    ]);
});

it('resolves the handler parameters that are not hook arguments from the container', function (): void {
    bootAsyncProvider();
    $parameter = (new ReflectionFunction(fn (int $id, AsyncProviderService $service): null => null))->getParameters()[1];

    expect(Async::isInjectable($parameter))->toBeTrue()
        ->and(Async::inject($parameter)->name)->toBe('from the container');
});

it('injects nothing for a parameter without a named type', function (): void {
    $resolve = (new ReflectionClass(AsyncServiceProvider::class))->getMethod('resolveParameter');
    $parameter = (new ReflectionFunction(fn (int|string $id): null => null))->getParameters()[0];

    expect($resolve->invoke(null, app(), $parameter))->toBeNull();
});

it('carries Eloquent models by reference', function (): void {
    bootAsyncProvider();
    $fake = Async::fake();
    $handler = new QueuedHandler('save_post', 10, 'App\Hooks\Sync@handle', 'App\Hooks\Sync@handle', 1, 1, new PendingAsync(static fn (): null => null));

    Async::dispatcher()->dispatch($handler, [new AsyncProviderModel]);

    expect($fake)->toBeInstanceOf(AsyncFake::class)
        ->and($fake->dispatched()[0]->arguments[0])->toBe(['@type' => 'ref', 'kind' => 'eloquent', 'ref' => ['class' => AsyncProviderModel::class, 'key' => 7]]);
});
