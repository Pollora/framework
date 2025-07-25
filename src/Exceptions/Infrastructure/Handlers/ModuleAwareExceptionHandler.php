<?php

declare(strict_types=1);

namespace Pollora\Exceptions\Infrastructure\Handlers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Module-aware exception handler for enhanced error view resolution.
 *
 * This exception handler extends Laravel's default exception handling to provide
 * module-aware error view resolution. It prioritizes error views from registered
 * modules over framework defaults, allowing modules to provide custom error pages.
 *
 * The handler integrates seamlessly with the existing WordPress-Laravel bridge
 * while providing enhanced error view discovery through the ModuleAssetManager's
 * view registration system.
 *
 * Key features:
 * - Module error view prioritization
 * - Fallback to Laravel default error views
 * - Exception-type specific view resolution
 * - HTTP status code based view selection
 * - Debug information for development
 *
 * @author Pollora Framework
 */
class ModuleAwareExceptionHandler extends Handler
{
    protected ModuleAwareErrorViewResolver $errorViewResolver;

    /**
     * Create a new exception handler instance.
     *
     * @param  Container  $container  The service container instance
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        // Initialize the module-aware error view resolver
        $this->initializeErrorViewResolver();
    }

    /**
     * Initialize the module-aware error view resolver.
     *
     * Sets up the error view resolver with the view factory from the container.
     * This method ensures the resolver is available even if dependency injection
     * is not fully configured.
     */
    protected function initializeErrorViewResolver(): void
    {
        if (! isset($this->errorViewResolver)) {
            try {
                /** @var ViewFactory $viewFactory */
                $viewFactory = $this->container->make('view');
                $this->errorViewResolver = new ModuleAwareErrorViewResolver($this->container, $viewFactory);
            } catch (Throwable $e) {
                // Log error but don't fail - fall back to default behavior
                if (function_exists('error_log')) {
                    error_log('Failed to initialize ModuleAwareErrorViewResolver: '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * This method extends Laravel's default exception rendering to use
     * module-aware error view resolution when handling HTTP exceptions.
     *
     * @param  Request  $request  The HTTP request that triggered the exception
     * @param  Throwable  $e  The exception to render
     * @return SymfonyResponse The HTTP response for the exception
     */
    public function render($request, Throwable $e): SymfonyResponse
    {
        // Only handle HTTP exceptions with module-aware view resolution
        if ($e instanceof HttpExceptionInterface && $this->shouldRenderHtmlResponse($request)) {
            $response = $this->renderHttpExceptionWithModuleViews($e, $request);
            if ($response !== null) {
                return $response;
            }
        }

        // Fall back to Laravel's default exception rendering
        return parent::render($request, $e);
    }

    /**
     * Render HTTP exceptions using module-aware view resolution.
     *
     * Attempts to render HTTP exceptions using views from registered modules,
     * falling back to Laravel's default behavior if no module views are found.
     *
     * @param  HttpExceptionInterface  $e  The HTTP exception to render
     * @param  Request  $request  The HTTP request that triggered the exception
     * @return Response|null HTTP response if module view found, null otherwise
     */
    protected function renderHttpExceptionWithModuleViews(HttpExceptionInterface $e, Request $request): ?Response
    {
        // Ensure error view resolver is initialized
        $this->initializeErrorViewResolver();

        if (! isset($this->errorViewResolver)) {
            return null;
        }

        try {
            $statusCode = $e->getStatusCode();
            $viewName = $this->errorViewResolver->resolveErrorView($e, $request, $statusCode);

            if ($viewName === null) {
                return null;
            }

            // Prepare view data with exception and request context
            $viewData = $this->prepareErrorViewData($e, $request, $statusCode);

            // Render the view with appropriate status code
            return response()->view($viewName, $viewData, $statusCode, $e->getHeaders());

        } catch (Throwable $renderException) {
            // Log rendering error but don't fail - fall back to default behavior
            if (function_exists('error_log')) {
                error_log('Failed to render module error view: '.$renderException->getMessage());
            }

            return null;
        }
    }

    /**
     * Prepare view data for error view rendering.
     *
     * Creates a standardized data array for error views, including exception
     * details, request context, and debug information when appropriate.
     *
     * @param  HttpExceptionInterface  $exception  The HTTP exception
     * @param  Request  $request  The HTTP request
     * @param  int  $statusCode  The HTTP status code
     * @return array<string, mixed> View data array
     */
    protected function prepareErrorViewData(HttpExceptionInterface $exception, Request $request, int $statusCode): array
    {
        $data = [
            'exception' => $exception,
            'statusCode' => $statusCode,
            'message' => $this->getErrorMessage($exception, $statusCode),
        ];

        // Add debug information in development environment
        if (config('app.debug', false) && isset($this->errorViewResolver)) {
            $data['debug'] = $this->errorViewResolver->getDebugInfo($statusCode, $exception);
        }

        // Add request context for view customization
        $data['request'] = [
            'url' => $request->url(),
            'method' => $request->method(),
            'userAgent' => $request->userAgent(),
            'ip' => $request->ip(),
        ];

        return $data;
    }

    /**
     * Get appropriate error message for the given exception and status code.
     *
     * Provides user-friendly error messages while respecting security
     * considerations and debug mode settings.
     *
     * @param  HttpExceptionInterface  $exception  The HTTP exception
     * @param  int  $statusCode  The HTTP status code
     * @return string User-friendly error message
     */
    protected function getErrorMessage(HttpExceptionInterface $exception, int $statusCode): string
    {
        // Use exception message if provided and not in production
        $exceptionMessage = $exception->getMessage();
        $debug = false;
        try {
            $debug = $this->container->make('config')->get('app.debug', false);
        } catch (Throwable) {
            // Silent fail
        }

        if ($exceptionMessage !== '' && $debug) {
            return $exceptionMessage;
        }

        // Provide default messages based on status code
        return match ($statusCode) {
            404 => 'The requested page could not be found.',
            403 => 'Access to this resource is forbidden.',
            401 => 'Authentication is required to access this resource.',
            500 => 'An internal server error occurred.',
            503 => 'The service is temporarily unavailable.',
            default => 'An error occurred while processing your request.',
        };
    }

    /**
     * Determine if the exception should be reported.
     *
     * Extends Laravel's default reporting logic to handle module-specific
     * exception reporting configuration.
     *
     * @param  Throwable  $e  The exception to check
     * @return bool True if the exception should be reported
     */
    public function shouldReport(Throwable $e): bool
    {
        // Allow modules to define their own reporting rules
        $moduleReportingResult = $this->shouldReportForModules($e);
        if ($moduleReportingResult !== null) {
            return $moduleReportingResult;
        }

        // Fall back to Laravel's default reporting logic
        return parent::shouldReport($e);
    }

    /**
     * Check module-specific exception reporting rules.
     *
     * Allows modules to define custom logic for determining whether
     * specific exceptions should be reported.
     *
     * @param  Throwable  $e  The exception to check
     * @return bool|null True/false for module decision, null for default behavior
     */
    protected function shouldReportForModules(Throwable $e): ?bool
    {
        try {
            // Check if any modules have registered custom reporting rules
            $reportingRules = [];
            try {
                $reportingRules = $this->container->make('config')->get('pollora.exceptions.reporting', []);
            } catch (Throwable) {
                return null;
            }

            if (empty($reportingRules)) {
                return null;
            }

            $exceptionClass = get_class($e);

            foreach ($reportingRules as $rule) {
                if (isset($rule['exception']) && $rule['exception'] === $exceptionClass) {
                    return $rule['report'] ?? null;
                }
            }

            return null;

        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get debug information about error view resolution.
     *
     * Provides detailed debugging information about the error view resolution
     * process. Only available in debug mode for security reasons.
     *
     * @param  Throwable  $exception  The exception that triggered the error
     * @param  Request  $request  The HTTP request
     * @return array<string, mixed> Debug information array
     */
    public function getErrorViewDebugInfo(Throwable $exception, Request $request): array
    {
        try {
            $debug = $this->container->make('config')->get('app.debug', false);
            if (! $debug) {
                return ['debug' => false, 'message' => 'Debug mode is disabled'];
            }
        } catch (Throwable) {
            return ['debug' => false, 'message' => 'Config not available'];
        }

        $this->initializeErrorViewResolver();

        if (! isset($this->errorViewResolver)) {
            return ['error' => 'ModuleAwareErrorViewResolver not available'];
        }

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        return $this->errorViewResolver->getDebugInfo($statusCode, $exception);
    }

    /**
     * Determine if the request expects an HTML response.
     *
     * This method provides a compatible way to check if the request expects
     * HTML content across different Laravel versions.
     *
     * @param  Request  $request  The HTTP request to check
     * @return bool True if the request expects HTML content
     */
    protected function shouldRenderHtmlResponse(Request $request): bool
    {
        // Check if the request accepts HTML content
        if (method_exists($request, 'expectsHtml')) {
            return $request->expectsHtml();
        }

        // Fallback for older Laravel versions
        return $request->accepts(['text/html', 'application/xhtml+xml']) !== null;
    }
}
