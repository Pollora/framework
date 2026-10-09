<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Responses;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * What the WordPress fallback route answers while WordPress is not loaded.
 *
 * Pollora loads WordPress only once Laravel's database connection points to
 * MySQL with its settings filled in. Until then there is no template
 * hierarchy to ask, so the fallback route answers 503: with `app.debug`, a
 * page naming the missing settings and the commands to run; without it,
 * the application's or Laravel's own 503 page.
 */
class SetupRequiredResponse
{
    /**
     * The settings Pollora reads, keyed by the connection option they fill.
     *
     * @var array<string, string>
     */
    private const array SETTINGS = [
        'host' => 'DB_HOST',
        'database' => 'DB_DATABASE',
        'username' => 'DB_USERNAME',
        'password' => 'DB_PASSWORD',
    ];

    /**
     * The commands that set a project up, with what each one does.
     *
     * @var array<string, string>
     */
    private const array COMMANDS = [
        'php artisan pollora:env:setup' => 'asks for the database settings and writes them to .env',
        'php artisan pollora:install' => 'installs WordPress and the theme',
        'php artisan pollora:doctor' => 'checks the project and prints the fix for each problem',
    ];

    public static function make(): Response
    {
        if (config('app.debug')) {
            return response(self::page(self::missingSettings()), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        (new RegisterErrorViewPaths)();

        // The application's errors/503 view, else Laravel's own
        foreach (['errors.503', 'errors::503'] as $view) {
            if (View::exists($view)) {
                return response(View::make($view), Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }

        return response('Service Unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * The database settings standing between the project and WordPress.
     *
     * Read from the configuration, without connecting. While the default
     * connection is not MySQL, the settings are those of the `mysql`
     * connection, which `DB_CONNECTION=mysql` would use.
     *
     * @return list<string>
     */
    public static function missingSettings(): array
    {
        $default = config('database.default');
        $connection = (array) config("database.connections.{$default}", []);

        $missing = [];

        if (($connection['driver'] ?? null) !== 'mysql') {
            $missing[] = 'DB_CONNECTION=mysql';
            $connection = (array) config('database.connections.mysql', []);
        }

        foreach (self::SETTINGS as $option => $setting) {
            if (! isset($connection[$option]) || ($option !== 'password' && $connection[$option] === '')) {
                $missing[] = $setting;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $missing
     */
    private static function page(array $missing): string
    {
        $settings = $missing === []
            ? '<p>The database settings look complete, yet WordPress did not load: <code>php artisan pollora:doctor</code> says why.</p>'
            : '<p>Pollora reads Laravel’s database connection. Set these in <code>.env</code>:</p><ul>'
                .implode('', array_map(fn (string $setting): string => '<li><code>'.e($setting).'</code></li>', $missing))
                .'</ul>';

        $commands = implode('', array_map(
            fn (string $command, string $description): string => '<li><code>'.e($command).'</code> — '.e($description).'</li>',
            array_keys(self::COMMANDS),
            self::COMMANDS,
        ));

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>Pollora is not set up yet</title>
            <style>
            body { font: 16px/1.6 system-ui, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 3rem 1rem; }
            main { max-width: 40rem; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 2rem; }
            h1 { font-size: 1.5rem; margin-top: 0; }
            code { background: #f3f4f6; border-radius: .25rem; padding: .1rem .3rem; }
            small { color: #6b7280; }
            </style>
            </head>
            <body>
            <main>
            <h1>Pollora is not set up yet</h1>
            <p>WordPress is not loaded, so there is no page to show.</p>
            {$settings}
            <p>Then run:</p>
            <ul>{$commands}</ul>
            <small>This page shows because <code>APP_DEBUG</code> is on. Without it, visitors get a plain 503.</small>
            </main>
            </body>
            </html>
            HTML;
    }
}
