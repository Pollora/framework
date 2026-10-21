<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pollora\Route\UI\Http\Responses\PublicDirectoryIndexRedirect;

beforeEach(function (): void {
    $this->public = sys_get_temp_dir().'/pollora-public-'.bin2hex(random_bytes(6));
    mkdir($this->public.'/cms/wp-admin', 0777, true);
    mkdir($this->public.'/cms/empty', 0777, true);
    touch($this->public.'/index.php');
    touch($this->public.'/cms/wp-admin/index.php');
    touch($this->public.'/cms/wp-login.php');
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->public));
});

describe('PublicDirectoryIndexRedirect', function (): void {
    it('sends a directory URL to the index.php inside it', function (): void {
        $response = PublicDirectoryIndexRedirect::for(Request::create('/cms/wp-admin/'), $this->public);

        expect($response?->getTargetUrl())->toBe('/cms/wp-admin/index.php');
    });

    it('keeps the query string', function (): void {
        $response = PublicDirectoryIndexRedirect::for(Request::create('/cms/wp-admin/?page=x'), $this->public);

        expect($response?->getTargetUrl())->toBe('/cms/wp-admin/index.php?page=x');
    });

    it('leaves alone what is not a directory with an index.php', function (string $uri): void {
        expect(PublicDirectoryIndexRedirect::for(Request::create($uri), $this->public))->toBeNull();
    })->with([
        'directory without index' => '/cms/empty/',
        'no trailing slash' => '/cms/wp-admin',
        'a file' => '/cms/wp-login.php',
        'the root' => '/',
        'a WordPress page' => '/sample-page/',
        'outside public' => '/../',
    ]);

    it('only redirects reads', function (): void {
        expect(PublicDirectoryIndexRedirect::for(Request::create('/cms/wp-admin/', 'POST'), $this->public))->toBeNull();
    });
});
