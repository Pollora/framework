<?php

declare(strict_types=1);

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Pollora\Auth\AuthServiceProvider;
use Pollora\Auth\WordPressGuard;
use Pollora\Auth\WordPressUserProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Mail\Mailer;
use Pollora\Mail\WordPressMailServiceProvider;
use Pollora\Option\Application\Service\OptionService;
use Pollora\Option\Domain\Contract\OptionRepositoryInterface;
use Pollora\Option\Domain\Service\OptionValidationService;
use Pollora\Option\Infrastructure\Providers\OptionServiceProvider;
use Pollora\Permalink\Domain\Contracts\UrlNormalizerInterface;
use Pollora\Permalink\Infrastructure\Providers\PermalinkServiceProvider;
use Pollora\Permalink\Infrastructure\Services\PermalinkManager;

afterEach(function (): void {
    Mockery::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

describe('OptionServiceProvider', function (): void {
    it('binds the repository and builds the option service from the repository and the validator', function (): void {
        $app = new Application(sys_get_temp_dir());
        (new OptionServiceProvider($app))->register();

        expect($app->make(OptionRepositoryInterface::class))->toBeInstanceOf(OptionRepositoryInterface::class)
            ->and($app->make(OptionValidationService::class))->toBe($app->make(OptionValidationService::class))
            ->and($app->make(OptionService::class))->toBeInstanceOf(OptionService::class)
            ->and($app->make(OptionService::class))->toBe($app->make(OptionService::class));
    });
});

describe('PermalinkServiceProvider', function (): void {
    it('registers the normalizer and the manager as singletons', function (): void {
        $app = new Application(sys_get_temp_dir());
        (new PermalinkServiceProvider($app))->register();

        expect($app->make(UrlNormalizerInterface::class))->toBe($app->make(UrlNormalizerInterface::class))
            ->and($app->make(PermalinkManager::class))->toBeInstanceOf(PermalinkManager::class)
            ->and($app->make(PermalinkManager::class))->toBe($app->make(PermalinkManager::class));
    });

    it('hooks the manager into the trailing slash, canonical redirect and structure change', function (): void {
        $app = new Application(sys_get_temp_dir());
        $provider = new PermalinkServiceProvider($app);
        $provider->register();

        $manager = Mockery::mock(PermalinkManager::class);
        $manager->shouldReceive('handleUserTrailingSlash')->byDefault();
        $manager->shouldReceive('updateStructure')->once()->with('/%postname%');
        $app->instance(PermalinkManager::class, $manager);

        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->once()->with('user_trailingslashit', Mockery::type(Closure::class), 10);
        $filter->shouldReceive('add')->once()->with('redirect_canonical', Mockery::type(Closure::class));
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->once()->with('permalink_structure_changed', Mockery::on(function (Closure $callback): bool {
            $callback('/%postname%/', '/%postname%');

            return true;
        }), 90);

        $provider->boot($filter, $action);
    });
});

describe('WordPressMailServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->app = new Application(sys_get_temp_dir());
        $this->app->instance('config', new Repository);
        $this->app->instance(Filter::class, Mockery::mock(Filter::class));
        $this->provider = new WordPressMailServiceProvider($this->app);
        $this->provider->register();
    });

    it('registers the mailer as a singleton under wp.mail', function (): void {
        expect($this->app->make('wp.mail'))->toBeInstanceOf(Mailer::class)
            ->and($this->app->make('wp.mail'))->toBe($this->app->make('wp.mail'));
    });

    it('does not take over pre_wp_mail when mail handling is turned off', function (): void {
        $this->app['config']->set('wordpress.enable_mail_handling', false);
        $filter = Mockery::mock(Filter::class);
        $filter->shouldNotReceive('add');

        $this->provider->boot($filter);
    });

    it('sends through the Laravel mailer from pre_wp_mail, answering whether it worked', function (bool $works): void {
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->with('a@example.test', 'Hi', 'Body', 'X-Test: 1', ['/tmp/f'])
            ->andReturn($works ? Mockery::mock(Illuminate\Mail\SentMessage::class) : null);
        $this->app->instance('wp.mail', $mailer);

        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->once()->with('pre_wp_mail', Mockery::on(fn (Closure $callback): bool => $callback(null, ['to' => 'a@example.test', 'subject' => 'Hi', 'message' => 'Body', 'headers' => 'X-Test: 1', 'attachments' => ['/tmp/f']]) === $works), 10, 2);

        $this->provider->boot($filter);
    })->with([
        'sent' => [true],
        'failed' => [false],
    ]);
});

describe('AuthServiceProvider', function (): void {
    it('registers the wp guard driver and user provider, and the capability gate', function (): void {
        $app = new Application(sys_get_temp_dir());
        $app->instance('config', new Repository(['auth' => ['defaults' => ['guard' => 'wp'], 'guards' => ['wp' => ['driver' => 'wp', 'provider' => 'wpusers']], 'providers' => ['wpusers' => ['driver' => 'wp']]]]));
        $app->instance('auth', $manager = new AuthManager($app));
        $app->instance(AuthManager::class, $manager);
        Facade::setFacadeApplication($app);
        $app->instance(Illuminate\Contracts\Auth\Access\Gate::class, $gate = Mockery::mock(Illuminate\Contracts\Auth\Access\Gate::class));
        $gate->shouldReceive('after')->once();

        (new AuthServiceProvider($app))->register();

        expect($manager->guard('wp'))->toBeInstanceOf(WordPressGuard::class)
            ->and($manager->createUserProvider('wpusers'))->toBeInstanceOf(WordPressUserProvider::class);
    });

    it('registers nothing when the application has no auth service', function (): void {
        $app = new Application(sys_get_temp_dir());
        $app->forgetInstance('auth');
        unset($app['auth']);

        (new AuthServiceProvider($app))->register();

        expect($app->bound('auth'))->toBeFalse();
    });
});
