<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Illuminate\Support\Facades\Http;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Throwable;

/**
 * The web server's document root is public/, not the project folder.
 *
 * MAMP, Apache and nginx are often pointed at the project folder. The site
 * then answers 404 and, worse, `/.env` is served as a text file with the
 * application key and the database password. Valet, Herd and `artisan serve`
 * always use public/, so only a manual setup can get this wrong.
 *
 * Only a web request can tell which folder the server exposes, so the check
 * runs in Site Health. A server that cannot answer its own request (PHP's
 * built-in server with a single worker) skips it rather than guess.
 */
final readonly class PublicRootCheck implements CheckInterface
{
    public function id(): string
    {
        return 'public-root';
    }

    public function label(): string
    {
        return 'Document root';
    }

    public function runsIn(): array
    {
        return [RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $url = rtrim(trim((string) config('app.url')), '/');

        if ($url === '') {
            return CheckResult::skipped('APP_URL is not set.');
        }

        try {
            $response = Http::timeout(3)->withoutRedirecting()->get($url.'/.env');
        } catch (Throwable) {
            return CheckResult::skipped('The site could not request itself.');
        }

        if ($response->ok() && str_contains($response->body(), 'APP_KEY=')) {
            return CheckResult::error(
                '.env is served over HTTP: the web server exposes the project folder, not public/.',
                [sprintf('GET %s/.env answered 200 with the application settings', $url)],
                'Point the document root to the public/ folder of the project (MAMP: Preferences › Server › Document root), then change APP_KEY and the passwords in .env',
            );
        }

        return CheckResult::ok('.env is not served; the document root is public/.');
    }
}
