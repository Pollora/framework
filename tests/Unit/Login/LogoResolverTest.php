<?php

declare(strict_types=1);

use Pollora\Login\Infrastructure\Services\LogoResolver;

/**
 * A logo file inside a theme has no URL on a Pollora site — measured:
 * `get_theme_file_uri()` answers an empty string, because only the Vite build
 * output is web-served. Reading the file and inlining it is the path that
 * works, and these cases pin what it must and must not inline.
 */
/**
 * The first two chunks of a PNG, which is all getimagesize() reads.
 */
function pngHeader(int $width, int $height): string
{
    $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
    $chunk = 'IHDR'.$header;

    return "\x89PNG\r\n\x1a\n".pack('N', strlen($header)).$chunk.pack('N', crc32($chunk));
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/pollora-login-'.bin2hex(random_bytes(6));
    mkdir($this->dir.'/images', 0o777, true);

    file_put_contents(
        $this->dir.'/images/wordmark.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 320"><path d="M0 0h1v1H0z"/></svg>'
    );

    $this->resolver = new LogoResolver($this->dir);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/images/*') ?: []);
    @rmdir($this->dir.'/images');
    @rmdir($this->dir);
});

describe('LogoResolver', function (): void {
    it('inlines a file that lives inside the theme', function (): void {
        $logo = $this->resolver->resolve('images/wordmark.svg');

        expect($logo)->not->toBeNull()
            ->and($logo->cssUrl)->toStartWith('url("data:image/svg+xml;base64,')
            ->and($logo->cssUrl)->toEndWith('")');
    });

    it('draws the file at the shape the file itself declares', function (): void {
        // A 800×320 wordmark dropped into WordPress's 84×84 anchor is squeezed
        // into a square; carrying the viewBox's ratio through is what stops it.
        $logo = $this->resolver->resolve('images/wordmark.svg');

        expect($logo->width)->toBe(LogoResolver::DEFAULT_WIDTH)
            ->and($logo->height)->toBe((int) round(LogoResolver::DEFAULT_WIDTH / 2.5));
    });

    it('derives the missing dimension from the one it was given', function (): void {
        expect($this->resolver->resolve('images/wordmark.svg', 400)->height)->toBe(160)
            ->and($this->resolver->resolve('images/wordmark.svg', null, 160)->width)->toBe(400);
    });

    it('obeys a theme that states both dimensions', function (): void {
        $logo = $this->resolver->resolve('images/wordmark.svg', 100, 100);

        expect($logo->width)->toBe(100)->and($logo->height)->toBe(100);
    });

    it('passes an address it does not have to read straight through', function (string $source): void {
        expect($this->resolver->resolve($source)->cssUrl)->toBe('url("'.$source.'")');
    })->with([
        'https' => ['https://example.test/logo.svg'],
        'protocol relative' => ['//example.test/logo.svg'],
        'site root' => ['/content/uploads/logo.png'],
        'already inline' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='],
    ]);

    it('gives up rather than guess', function (string $source): void {
        expect($this->resolver->resolve($source))->toBeNull();
    })->with([
        'no such file' => ['images/missing.svg'],
        'not an image' => ['images/wordmark.txt'],
        'no extension' => ['images/wordmark'],
    ]);

    it('refuses to inline a file too big to inline', function (): void {
        // base64 adds a third again, and this is printed on every login
        // request; past a point the screen pays more than the logo is worth.
        file_put_contents($this->dir.'/images/heavy.png', str_repeat('x', 200));

        expect((new LogoResolver($this->dir, 100))->resolve('images/heavy.png'))->toBeNull();
    });

    it('refuses to inline an empty file', function (): void {
        touch($this->dir.'/images/empty.svg');

        expect($this->resolver->resolve('images/empty.svg'))->toBeNull();
    });

    it('reads a size from width and height when there is no viewBox', function (): void {
        file_put_contents(
            $this->dir.'/images/sized.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200"></svg>'
        );

        expect($this->resolver->resolve('images/sized.svg', 300)->height)->toBe(100);
    });

    it('falls back to a square when the file declares no shape', function (): void {
        file_put_contents($this->dir.'/images/shapeless.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $logo = $this->resolver->resolve('images/shapeless.svg', 120);

        expect($logo->height)->toBe(120);
    });

    it('ignores a viewBox with no area', function (): void {
        file_put_contents(
            $this->dir.'/images/flat.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="300" height="100"></svg>'
        );

        expect($this->resolver->resolve('images/flat.svg', 300)->height)->toBe(100);
    });

    it('ignores width and height with no area either', function (): void {
        file_put_contents(
            $this->dir.'/images/zero.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0"></svg>'
        );

        expect($this->resolver->resolve('images/zero.svg', 90)->height)->toBe(90);
    });

    it('reads a raster logo at its real proportions', function (): void {
        // Written by hand rather than with GD: getimagesize() only reads the
        // header, and an extension that may not be installed is not worth a
        // skipped test — a skip that hides is how a suite stops measuring.
        file_put_contents($this->dir.'/images/raster.png', pngHeader(400, 100));

        expect($this->resolver->resolve('images/raster.png', 200)->height)->toBe(50);
    });

    it('falls back to a square for a raster file it cannot measure', function (): void {
        file_put_contents($this->dir.'/images/broken.png', 'not really a png');

        expect($this->resolver->resolve('images/broken.png', 80)->height)->toBe(80);
    });

    it('cannot be talked out of the style element', function (): void {
        // A quoted CSS string contains a `</style>` just fine; the HTML parser
        // does not care that it is quoted.
        $logo = $this->resolver->resolve('https://example.test/a</style><script>x</script>.svg');

        expect($logo->cssUrl)->not->toContain('<')
            ->and($logo->cssUrl)->not->toContain('>');
    });

    it('cannot be talked out of the url() either', function (): void {
        $logo = $this->resolver->resolve('https://example.test/a".png');

        expect($logo->cssUrl)->toBe('url("https://example.test/a\\".png")');
    });

    it('reads an attachment the media library already serves', function (): void {
        Brain\Monkey\Functions\when('wp_get_attachment_image_src')
            ->justReturn(['https://example.test/uploads/logo.png', 600, 200, false]);

        $logo = $this->resolver->resolve(12, 300);

        expect($logo->cssUrl)->toBe('url("https://example.test/uploads/logo.png")')
            ->and($logo->height)->toBe(100);
    });

    it('gives up on an attachment WordPress cannot find', function (mixed $answer): void {
        Brain\Monkey\Functions\when('wp_get_attachment_image_src')->justReturn($answer);

        expect($this->resolver->resolve(12))->toBeNull();
    })->with([
        'missing' => [false],
        'no url' => [[['', 0, 0]]],
    ]);

    it('falls back to a square for an attachment with no dimensions', function (): void {
        Brain\Monkey\Functions\when('wp_get_attachment_image_src')
            ->justReturn(['https://example.test/logo.png', 0, 0, false]);

        expect($this->resolver->resolve(12, 90)->height)->toBe(90);
    });
});
