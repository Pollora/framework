<?php

declare(strict_types=1);

namespace Pollora\Exceptions\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\View\ViewFinderInterface;
use Throwable;

/**
 * Module-aware error view resolver for enhanced error view discovery.
 *
 * This service extends Laravel's default error view resolution to prioritize
 * module-specific error views over framework defaults. It searches for error
 * views in the following order:
 * 1. Module-registered view paths (highest priority)
 * 2. Application error views (theme overrides)
 * 3. Laravel framework defaults (lowest priority)
 *
 * The resolver integrates with the ModuleAssetManager's view registration
 * system to automatically discover error views from modules.
 *
 * @author Pollora Framework
 */
class ModuleAwareErrorViewResolver
{
    public function __construct(
        protected Container $container,
        protected ViewFactory $viewFactory
    ) {}

    /**
     * Resolve the appropriate error view for the given exception.
     *
     * Attempts to find error views using module-aware resolution, falling back
     * to Laravel's default behavior if no custom views are found.
     *
     * @param  Throwable  $exception  The exception that triggered the error
     * @param  Request  $request  The current HTTP request
     * @param  int  $statusCode  HTTP status code for the error
     * @return string|null  The resolved view name, null if no view found
     */
    public function resolveErrorView(Throwable $exception, Request $request, int $statusCode): ?string
    {
        $viewCandidates = $this->getErrorViewCandidates($statusCode, $exception);

        foreach ($viewCandidates as $viewName) {
            if ($this->viewFactory->exists($viewName)) {
                return $viewName;
            }
        }

        return null;
    }

    /**
     * Get ordered list of error view candidates to check.
     *
     * Returns view names in order of priority, allowing modules to override
     * standard error views by providing their own implementations.
     *
     * @param  int  $statusCode  HTTP status code for the error
     * @param  Throwable  $exception  The exception that triggered the error
     * @return array<int, string>  Array of view names to attempt resolving
     */
    protected function getErrorViewCandidates(int $statusCode, Throwable $exception): array
    {
        $candidates = [];

        // Primary candidate based on HTTP status code
        $candidates[] = "errors.{$statusCode}";

        // Fallback candidates for common error types
        $candidates = array_merge($candidates, $this->getCommonErrorViewCandidates($statusCode));

        // Exception-specific candidates
        $candidates = array_merge($candidates, $this->getExceptionSpecificCandidates($exception));

        // Remove duplicates while preserving order
        return array_unique($candidates);
    }

    /**
     * Get common error view candidates based on status code ranges.
     *
     * Provides fallback views for common HTTP error categories when specific
     * status code views are not available.
     *
     * @param  int  $statusCode  HTTP status code for the error
     * @return array<int, string>  Array of fallback view names
     */
    protected function getCommonErrorViewCandidates(int $statusCode): array
    {
        return match (true) {
            $statusCode >= 400 && $statusCode < 500 => [
                'errors.4xx',
                'errors.client-error',
            ],
            $statusCode >= 500 && $statusCode < 600 => [
                'errors.5xx',
                'errors.server-error',
            ],
            default => [],
        };
    }

    /**
     * Generate error view candidates based on exception type.
     *
     * Creates view name candidates from the exception class name, allowing
     * modules to provide specialized error views for specific exception types.
     *
     * @param  Throwable  $exception  The exception that triggered the error
     * @return array<int, string>  Array of exception-based view names
     */
    protected function getExceptionSpecificCandidates(Throwable $exception): array
    {
        $exceptionClass = get_class($exception);
        $shortName = class_basename($exceptionClass);

        $candidates = [];

        // Convert exception class name to kebab-case view name
        $kebabCaseName = $this->convertToKebabCase($shortName);
        if ($kebabCaseName !== '') {
            $candidates[] = "errors.{$kebabCaseName}";
        }

        // Remove common suffixes for cleaner view names
        $cleanName = $this->removeCommonSuffixes($shortName);
        if ($cleanName !== $shortName && $cleanName !== '') {
            $kebabCleanName = $this->convertToKebabCase($cleanName);
            if ($kebabCleanName !== '') {
                $candidates[] = "errors.{$kebabCleanName}";
            }
        }

        return $candidates;
    }

    /**
     * Convert PascalCase to kebab-case for view naming.
     *
     * Transforms exception class names into view-friendly kebab-case format.
     *
     * @param  string  $input  PascalCase string to convert
     * @return string  kebab-case formatted string
     */
    protected function convertToKebabCase(string $input): string
    {
        if ($input === '') {
            return '';
        }

        // Insert hyphens before uppercase letters (except the first)
        $kebabCase = preg_replace('/(?<!^)[A-Z]/', '-$0', $input);
        
        if ($kebabCase === null) {
            return '';
        }

        return strtolower($kebabCase);
    }

    /**
     * Remove common exception suffixes for cleaner view names.
     *
     * Strips standard suffixes like 'Exception', 'Error', etc. to create
     * more semantic view names.
     *
     * @param  string  $exceptionName  Original exception class name
     * @return string  Clean name without common suffixes
     */
    protected function removeCommonSuffixes(string $exceptionName): string
    {
        $suffixes = ['Exception', 'Error', 'HttpException'];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($exceptionName, $suffix)) {
                $cleanName = substr($exceptionName, 0, -strlen($suffix));
                if ($cleanName !== '') {
                    return $cleanName;
                }
            }
        }

        return $exceptionName;
    }

    /**
     * Check if a view exists in any of the registered view paths.
     *
     * This method enhances the standard view existence check by considering
     * all module-registered view paths, not just the default application paths.
     *
     * @param  string  $viewName  Name of the view to check
     * @return bool  True if the view exists in any registered path
     */
    public function viewExistsInModules(string $viewName): bool
    {
        try {
            $viewFinder = $this->viewFactory->getFinder();
            
            if (! $viewFinder instanceof ViewFinderInterface) {
                return false;
            }

            // Laravel's exists() method already checks all registered paths
            // including those registered by modules via ModuleAssetManager
            return $this->viewFactory->exists($viewName);
            
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get debug information about view resolution.
     *
     * Provides detailed information about the view resolution process
     * for debugging purposes. Only used in development environments.
     *
     * @param  int  $statusCode  HTTP status code for the error
     * @param  Throwable  $exception  The exception that triggered the error
     * @return array<string, mixed>  Debug information array
     */
    public function getDebugInfo(int $statusCode, Throwable $exception): array
    {
        try {
            $debug = $this->container->make('config')->get('app.debug', false);
            if (! $debug) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $candidates = $this->getErrorViewCandidates($statusCode, $exception);
        $existingViews = [];
        $missingViews = [];

        foreach ($candidates as $candidate) {
            if ($this->viewFactory->exists($candidate)) {
                $existingViews[] = $candidate;
            } else {
                $missingViews[] = $candidate;
            }
        }

        $viewFinder = $this->viewFactory->getFinder();
        $viewPaths = $viewFinder instanceof ViewFinderInterface ? $viewFinder->getPaths() : [];

        return [
            'status_code' => $statusCode,
            'exception_class' => get_class($exception),
            'view_candidates' => $candidates,
            'existing_views' => $existingViews,
            'missing_views' => $missingViews,
            'registered_view_paths' => $viewPaths,
            'resolved_view' => $this->resolveErrorView($exception, request(), $statusCode),
        ];
    }
}