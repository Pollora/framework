<?php

declare(strict_types=1);

use Pollora\Support\Uri;

it('trims the trailing slashes of a URL that PHP cannot parse', function (): void {
    expect((new Uri)->removeTrailingSlash('http:///foo//'))->toBe('http:///foo');
});

it('keeps the query string and fragment when trimming the path', function (): void {
    expect((new Uri)->removeTrailingSlash('https://example.com:8080/a/b/?x=1&y=2#top'))
        ->toBe('https://example.com:8080/a/b?x=1&y=2#top');
});

it('turns a bare host with a trailing slash into the host alone', function (): void {
    expect((new Uri)->removeTrailingSlash('https://example.com/'))->toBe('https://example.com');
});

it('returns empty values untouched', function (?string $value): void {
    expect((new Uri)->removeTrailingSlash($value))->toBe($value);
})->with([null, '', '0']);
