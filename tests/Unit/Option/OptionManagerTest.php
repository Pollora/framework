<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Pollora\Logging\Application\Services\WordPressErrorLoggingService;
use Pollora\Logging\Domain\Contracts\WordPressErrorReporterInterface;
use Pollora\Logging\Infrastructure\Providers\LoggingServiceProvider;
use Pollora\Option\Application\Service\OptionService;
use Pollora\Option\Application\Services\OptionManager;
use Pollora\Option\Domain\Contract\OptionRepositoryInterface;
use Pollora\Option\Domain\Contracts\OptionManagerInterface;
use Pollora\Option\Domain\Model\Option as OptionModel;
use Pollora\Option\Domain\Service\OptionValidationService;
use Pollora\Option\Infrastructure\Providers\OptionServiceProvider;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Providers\PostTypeServiceProvider;
use Pollora\Support\Facades\Option;
use Pollora\Support\Facades\PostType;
use Pollora\Support\Facades\Taxonomy;
use Pollora\Support\Facades\WordPressError;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Taxonomy\Infrastructure\Providers\TaxonomyServiceProvider;

/**
 * An in-memory option repository that records the keys it deletes.
 */
function optionManagerWithStore(bool $deleteResult = true): array
{
    $repository = new class($deleteResult) implements OptionRepositoryInterface
    {
        /** @var array<string, mixed> */
        public array $values = [];

        /** @var list<string> */
        public array $deleted = [];

        public function __construct(private readonly bool $deleteResult) {}

        public function get(string $key): ?OptionModel
        {
            return array_key_exists($key, $this->values) ? new OptionModel($key, $this->values[$key]) : null;
        }

        public function store(OptionModel $option): bool
        {
            $this->values[$option->key] = $option->value;

            return true;
        }

        public function update(OptionModel $option): bool
        {
            $this->values[$option->key] = $option->value;

            return true;
        }

        public function delete(string $key): bool
        {
            $this->deleted[] = $key;

            return $this->deleteResult;
        }

        public function exists(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };

    return [new OptionManager(new OptionService($repository, new OptionValidationService)), $repository];
}

describe('OptionManager', function (): void {
    it('delegates get, set, update and exists to the option service', function (): void {
        [$manager, $repository] = optionManagerWithStore();

        expect($manager->get('blogname', 'fallback'))->toBe('fallback')
            ->and($manager->exists('blogname'))->toBeFalse()
            ->and($manager->set('blogname', 'Pollora'))->toBeTrue()
            ->and($manager->get('blogname'))->toBe('Pollora')
            ->and($manager->exists('blogname'))->toBeTrue()
            ->and($manager->update('blogname', 'Other'))->toBeTrue()
            ->and($repository->values)->toBe(['blogname' => 'Other']);
    });

    it('deletes an option and reports the result', function (bool $deleted): void {
        [$manager, $repository] = optionManagerWithStore($deleted);

        expect($manager->delete('blogname'))->toBe($deleted)
            ->and($repository->deleted)->toBe(['blogname']);
    })->with([true, false]);

    it('forgets an option by deleting it, whatever the result', function (bool $deleted): void {
        [$manager, $repository] = optionManagerWithStore($deleted);

        expect($manager->forget('blogname'))->toBe($deleted)
            ->and($repository->deleted)->toBe(['blogname']);
    })->with([true, false]);
});

describe('facades resolve contracts from the container', function (): void {
    beforeEach(function (): void {
        $this->app = new Application(sys_get_temp_dir());
        $this->app->bind(OptionRepositoryInterface::class, fn () => optionManagerWithStore()[1]);
    });

    it('resolves the option manager contract to the OptionManager singleton', function (): void {
        (new OptionServiceProvider($this->app))->register();

        expect($this->app->make(OptionManagerInterface::class))->toBeInstanceOf(OptionManager::class)
            ->and($this->app->make(OptionManagerInterface::class))->toBe($this->app->make(OptionManagerInterface::class));
    });

    it('resolves the post type and taxonomy factory contracts', function (): void {
        (new PostTypeServiceProvider($this->app))->register();
        (new TaxonomyServiceProvider($this->app))->register();

        expect($this->app->make(PostTypeFactoryInterface::class))->toBeInstanceOf(PostTypeFactory::class)
            ->and($this->app->make(TaxonomyFactoryInterface::class))->toBeInstanceOf(TaxonomyFactory::class);
    });

    it('resolves the WordPress error reporter contract to the logging service singleton', function (): void {
        $this->app->instance('config', new Repository);
        $this->app->instance(Request::class, Request::create('/'));
        (new LoggingServiceProvider($this->app))->register();

        $reporter = $this->app->make(WordPressErrorReporterInterface::class);

        expect($reporter)->toBeInstanceOf(WordPressErrorLoggingService::class)
            ->and($reporter)->toBe($this->app->make(WordPressErrorLoggingService::class))
            ->and($reporter)->toBe($this->app->make(WordPressErrorReporterInterface::class));
    });

    it('makes each facade accessor a contract (an interface)', function (string $facade, string $contract): void {
        $accessor = (new ReflectionMethod($facade, 'getFacadeAccessor'))->invoke(null);

        expect($accessor)->toBe($contract)
            ->and(interface_exists($accessor))->toBeTrue();
    })->with([
        'Option' => [Option::class, OptionManagerInterface::class],
        'PostType' => [PostType::class, PostTypeFactoryInterface::class],
        'Taxonomy' => [Taxonomy::class, TaxonomyFactoryInterface::class],
        'WordPressError' => [WordPressError::class, WordPressErrorReporterInterface::class],
    ]);

    it('implements the reporter contract in the logging service', function (): void {
        expect(is_subclass_of(WordPressErrorLoggingService::class, WordPressErrorReporterInterface::class))->toBeTrue();
    });
});
