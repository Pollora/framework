<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Pollora\Route\Infrastructure\Middleware\WordPressTemplateEnhancement;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    $this->middleware = new WordPressTemplateEnhancement;
    $this->actions = [];

    Brain\Monkey\Functions\when('wp_styles')->justReturn();
    Brain\Monkey\Functions\when('do_action')->alias(function (string $hook, mixed ...$args): void {
        $this->actions[] = $hook;
    });
});

describe('when WordPress wants the output enhanced', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\when('wp_should_output_buffer_template_for_enhancement')->justReturn(true);
    });

    it('announces the buffer before the view renders', function (): void {
        Brain\Monkey\Functions\when('apply_filters')->returnArg(2);

        $this->middleware->handle(Request::create('/'), function (): Response {
            expect($this->actions)->toBe(['wp_template_enhancement_output_buffer_started']);

            return new Response('<html><head></head><body></body></html>');
        });

        expect($this->actions)->toBe([
            'wp_template_enhancement_output_buffer_started',
            'wp_finalized_template_enhancement_output_buffer',
        ]);
    });

    it('sends the HTML through the enhancement filter', function (): void {
        Brain\Monkey\Functions\when('apply_filters')->alias(
            fn (string $hook, string $html): string => $hook === 'wp_template_enhancement_output_buffer'
                ? str_replace('<head>', '<head><style id="hoisted"></style>', $html)
                : $html
        );

        $response = $this->middleware->handle(
            Request::create('/'),
            fn (): Response => new Response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8'])
        );

        expect($response->getContent())->toBe('<html><head><style id="hoisted"></style></head><body></body></html>');
    });

    it('leaves JSON, redirects and streamed responses alone', function (Closure $makeResponse): void {
        Brain\Monkey\Functions\expect('apply_filters')->never();

        $this->middleware->handle(Request::create('/'), $makeResponse);

        expect($this->actions)->toBe(['wp_template_enhancement_output_buffer_started']);
    })->with([
        'json' => [fn (): Response => new Response('{"a":1}', 200, ['Content-Type' => 'application/json'])],
        'redirect' => [fn (): Response => new RedirectResponse('/elsewhere')],
        'streamed' => [fn (): Response => new StreamedResponse(fn (): null => null)],
    ]);

    it('keeps the rendered page when a filter callback throws', function (): void {
        Brain\Monkey\Functions\when('apply_filters')->alias(function (): never {
            throw new RuntimeException('broken optimiser');
        });
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::type(RuntimeException::class));
        app()->instance(ExceptionHandler::class, $handler);

        $response = $this->middleware->handle(
            Request::create('/'),
            fn (): Response => new Response('<html><body>page</body></html>')
        );

        expect($response->getContent())->toBe('<html><body>page</body></html>');
    });
});

it('does nothing when the site opted out of the buffer', function (): void {
    Brain\Monkey\Functions\when('wp_should_output_buffer_template_for_enhancement')->justReturn(false);
    Brain\Monkey\Functions\expect('apply_filters')->never();

    $response = $this->middleware->handle(
        Request::create('/'),
        fn (): Response => new Response('<html><body>page</body></html>')
    );

    expect($response->getContent())->toBe('<html><body>page</body></html>')
        ->and($this->actions)->toBe([]);
});
