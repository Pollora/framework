<?php

declare(strict_types=1);

// Bootstrap file specifically for Pest unit tests
// This file loads the existing WordPress mock system WITHOUT wordpress-stubs
// The WordPress functions will be defined in helpers.php instead

// Load our WordPress helper functions FIRST, before composer autoloader
// This ensures our function definitions take precedence over Laravel's
require_once __DIR__ . '/Unit/helpers.php';

// Now load the composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants that may be used in tests
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/var/www/html/wp-content/plugins');
}

if (!defined('WP_CLI_VERSION')) {
    define('WP_CLI_VERSION', '2.13.0');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/var/www/html/wp-content');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Define Laravel helper functions for tests
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        return $default;
    }
}

if (!function_exists('request')) {
    function request($key = null, $default = null) {
        return $default;
    }
}

if (!function_exists('url')) {
    function url($path = null) {
        return 'http://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        return '/var/www/html/storage' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('now')) {
    function now() {
        return new \Illuminate\Support\Carbon();
    }
}



// Setup extended container for tests
$container = \Illuminate\Container\Container::getInstance();

// Add missing methods to the container
if (!method_exists($container, 'publicPath')) {
    $container->instance('public_path_function', function ($path = '') {
        return '/var/www/html/public' . ($path ? '/' . ltrim($path, '/') : '');
    });
    
    // Override the container's call to publicPath and abort
    $originalContainer = $container;
    $customContainer = new class($originalContainer) extends \Illuminate\Container\Container {
        private $original;
        
        public function __construct($original) {
            $this->original = $original;
            // Copy all properties
            if (property_exists($original, 'bindings')) {
                $this->bindings = $original->bindings ?? [];
            }
            if (property_exists($original, 'instances')) {
                $this->instances = $original->instances ?? [];
            }
            if (property_exists($original, 'aliases')) {
                $this->aliases = $original->aliases ?? [];
            }
            if (property_exists($original, 'abstractAliases')) {
                $this->abstractAliases = $original->abstractAliases ?? [];
            }
        }
        
        public function publicPath($path = '') {
            return '/var/www/html/public' . ($path ? '/' . ltrim($path, '/') : '');
        }
        
        public function abort($code = 404, $message = '') {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message);
        }
        
        // Delegate all other method calls to original container if they exist
        public function __call($method, $arguments) {
            if (method_exists($this->original, $method)) {
                return call_user_func_array([$this->original, $method], $arguments);
            }
            return parent::__call($method, $arguments);
        }
    };
    
    \Illuminate\Container\Container::setInstance($customContainer);
    $container = $customContainer;
}

if (!$container->bound(\Illuminate\Contracts\Routing\ResponseFactory::class)) {
    $container->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, function () {
        return new class implements \Illuminate\Contracts\Routing\ResponseFactory {
            public function make($content = '', $status = 200, array $headers = [])
            {
                return new \Illuminate\Http\Response($content, $status, $headers);
            }

            public function view($view, $data = [], $status = 200, array $headers = [])
            {
                return new \Illuminate\Http\Response($view, $status, $headers);
            }

            public function json($data = [], $status = 200, array $headers = [], $options = 0)
            {
                return new \Illuminate\Http\JsonResponse($data, $status, $headers, $options);
            }

            public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
            {
                return $this->json($data, $status, $headers, $options)->setCallback($callback);
            }

            public function stream($callback, $status = 200, array $headers = [])
            {
                return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, $status, $headers);
            }

            public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
            {
                return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, array_merge($headers, [
                    'Content-Disposition' => "{$disposition}; filename=\"{$name}\""
                ]));
            }

            public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
            {
                return new \Symfony\Component\HttpFoundation\BinaryFileResponse($file, 200, $headers, true, $disposition);
            }

            public function file($file, array $headers = [])
            {
                return new \Symfony\Component\HttpFoundation\BinaryFileResponse($file, 200, $headers);
            }

            public function redirectTo($path, $status = 302, $headers = [], $secure = null)
            {
                return new \Illuminate\Http\RedirectResponse($path, $status, $headers);
            }

            public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
            {
                return $this->redirectTo($route, $status, $headers);
            }

            public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
            {
                return $this->redirectTo($action, $status, $headers);
            }

            public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
            {
                return $this->redirectTo($path, $status, $headers);
            }

            public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
            {
                return $this->redirectTo($default, $status, $headers);
            }
            
            public function noContent($status = 204, array $headers = [])
            {
                return new \Illuminate\Http\Response('', $status, $headers);
            }
            
            public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
            {
                return new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($data, $encodingOptions) {
                    echo json_encode($data, $encodingOptions);
                }, $status, array_merge($headers, ['Content-Type' => 'application/json']));
            }
        };
    });
}


// WooCommerce function stub  
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id = null) {
        return null;
    }
}