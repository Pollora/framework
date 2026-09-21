<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\WordPress\QueryTrait;

/**
 * Whether Pollora should resolve the URL as a content request.
 *
 * WordPress runs entry points of its own — wp-login.php, wp-admin, xmlrpc.php
 * — and Pollora is only loaded along the way through wp-config.php. Running
 * the query there sent a 404 header before the login form rendered, because
 * `/cms/wp-login.php` matches the attachment rewrite rule and no such
 * attachment exists.
 */
function servingProbe(): object
{
    return new class
    {
        use QueryTrait;

        public function serving(): bool
        {
            $method = (new ReflectionClass($this))->getMethod('laravelIsServingTheRequest');
            $method->setAccessible(true);

            return $method->invoke($this);
        }
    };
}

beforeEach(function (): void {
    $this->originalScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
    $this->originalContainer = Container::getInstance();

    $this->dir = sys_get_temp_dir().'/pollora-front-'.bin2hex(random_bytes(6));
    mkdir($this->dir.'/public/cms', 0777, true);
    touch($this->dir.'/public/index.php');
    touch($this->dir.'/public/cms/wp-login.php');

    // public_path() asks the application; a bare container has no such method.
    $app = new class extends Container
    {
        public string $base = '';

        public function publicPath(string $path = ''): string
        {
            return $this->base.'/public'.($path === '' ? '' : '/'.$path);
        }
    };
    $app->base = $this->dir;
    Container::setInstance($app);
});

afterEach(function (): void {
    Container::setInstance($this->originalContainer);

    if ($this->originalScript === null) {
        unset($_SERVER['SCRIPT_FILENAME']);
    } else {
        $_SERVER['SCRIPT_FILENAME'] = $this->originalScript;
    }
    exec('rm -rf '.escapeshellarg($this->dir));
});

describe('QueryTrait::laravelIsServingTheRequest()', function (): void {
    it('says yes when the front controller is the running script', function (): void {
        $_SERVER['SCRIPT_FILENAME'] = $this->dir.'/public/index.php';

        expect(servingProbe()->serving())->toBeTrue();
    });

    it('says no when WordPress is running one of its own entry points', function (): void {
        $_SERVER['SCRIPT_FILENAME'] = $this->dir.'/public/cms/wp-login.php';

        expect(servingProbe()->serving())->toBeFalse();
    });

    it('says yes when there is nothing to compare against', function (): void {
        // Better to do the work than to skip something the request needs.
        unset($_SERVER['SCRIPT_FILENAME']);

        expect(servingProbe()->serving())->toBeTrue();
    });

    it('says yes when no application is bound to ask', function (): void {
        $_SERVER['SCRIPT_FILENAME'] = $this->dir.'/public/cms/wp-login.php';
        Container::setInstance(null);

        // public_path() throws without one; that is not a reason to skip work.
        expect(servingProbe()->serving())->toBeTrue();
    });
});
