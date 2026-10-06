<?php

declare(strict_types=1);

namespace Pollora\BlockBinding\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\BlockBinding\Application\Services\BindingResolver;
use Pollora\BlockBinding\Application\Services\BindingSourceBuilder;
use Pollora\BlockBinding\Application\Services\BindingSourceRegistry;
use Pollora\BlockBinding\Domain\Contracts\ContentVisibilityInterface;
use Pollora\BlockBinding\Domain\Contracts\ValuePresenterInterface;
use Pollora\BlockBinding\Infrastructure\Adapters\WordPressBindingEditorScript;
use Pollora\BlockBinding\Infrastructure\Adapters\WordPressBindingRegistry;
use Pollora\BlockBinding\Infrastructure\Adapters\WordPressContentVisibility;
use Pollora\BlockBinding\Infrastructure\Adapters\WordPressValuePresenter;
use Pollora\BlockBinding\Infrastructure\Services\BindingEditorData;
use Pollora\BlockBinding\Infrastructure\Services\BindingFormatter;
use Pollora\BlockBinding\Infrastructure\Services\BlockBindingDiscovery;
use Pollora\BlockBinding\Infrastructure\Sources\AuthorMetaSource;
use Pollora\BlockBinding\Infrastructure\Sources\OptionSource;
use Pollora\BlockBinding\Infrastructure\Sources\PostMetaSource;
use Pollora\BlockBinding\Infrastructure\Sources\TermMetaSource;
use Pollora\BlockBinding\Infrastructure\Sources\TypedMetaReader;
use Pollora\BlockBinding\UI\Console\MakeBindingCommand;
use Pollora\BlockBinding\UI\Http\ResolveBindingsController;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Meta\Application\Services\MetaAccessor;
use Psr\Log\LoggerInterface;

/**
 * Block Bindings: `#[BlockBinding]` discovery, the sources Pollora ships
 * (`pollora/post-meta`, `term-meta`, `author-meta`, `option`), the
 * resolver every source answers through, and their editor side: the fields
 * each source offers and the preview of bound values.
 *
 * Bindings:
 *  - {@see BindingResolver} (singleton: values are kept for the request)
 *  - {@see BlockBindingDiscovery} (singleton, picked up by the discovery engine)
 */
class BlockBindingServiceProvider extends ServiceProvider
{
    /**
     * The sources Pollora ships, registered whatever the project declares.
     *
     * @var list<class-string>
     */
    public const array SOURCES = [PostMetaSource::class, TermMetaSource::class, AuthorMetaSource::class, OptionSource::class];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/block-bindings.php', 'block-bindings');

        $this->app->singleton(BindingSourceBuilder::class);
        $this->app->singleton(BindingSourceRegistry::class);
        $this->app->singleton(ContentVisibilityInterface::class, WordPressContentVisibility::class);
        $this->app->singleton(ValuePresenterInterface::class, WordPressValuePresenter::class);
        $this->app->singleton(BindingFormatter::class);
        $this->app->singleton(TypedMetaReader::class);
        $this->app->singleton(BindingEditorData::class);
        $this->app->singleton(WordPressBindingEditorScript::class);
        $this->app->singleton(ResolveBindingsController::class);

        $this->app->singleton(BindingResolver::class, fn (Application $app): BindingResolver => new BindingResolver(
            $app,
            $app->make(ContentVisibilityInterface::class),
            $app->make(ValuePresenterInterface::class),
            $app->make(MetaAccessor::class),
            (bool) $app->make('config')->get('app.debug', false),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(WordPressBindingRegistry::class, fn (Application $app): WordPressBindingRegistry => new WordPressBindingRegistry(
            $app->make(Action::class),
            $app->make(BindingResolver::class),
        ));

        $this->app->singleton(BlockBindingDiscovery::class, fn (Application $app): BlockBindingDiscovery => new BlockBindingDiscovery(
            $app->make(BindingSourceBuilder::class),
            $app->make(BindingSourceRegistry::class),
            $app->make(WordPressBindingRegistry::class),
            $app->make(LoggerInterface::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([MakeBindingCommand::class]);
        }
    }

    public function boot(): void
    {
        $builder = $this->app->make(BindingSourceBuilder::class);
        $sources = $this->app->make(BindingSourceRegistry::class);
        $registry = $this->app->make(WordPressBindingRegistry::class);

        foreach (self::SOURCES as $class) {
            $source = $builder->build($class);
            $sources->add($source);
            $registry->register($source);
        }

        $action = $this->app->make(Action::class);
        $action->add('enqueue_block_editor_assets', fn () => $this->app->make(WordPressBindingEditorScript::class)->enqueue());
        $action->add('rest_api_init', fn () => $this->app->make(ResolveBindingsController::class)->register());
    }
}
