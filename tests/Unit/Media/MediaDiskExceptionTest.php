<?php

declare(strict_types=1);

use Pollora\Media\Domain\Exceptions\MediaDiskException;

it('says the media module is off without a disk, and where to set one', function (): void {
    $exception = MediaDiskException::notConfigured();

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toStartWith('config/media.php has no "disk": the media module is off.')
        ->and($exception->getMessage())->toContain('config/filesystems.php');
});

it('names the disk that has no Flysystem driver', function (): void {
    expect(MediaDiskException::withoutFlysystemDriver('custom')->getMessage())
        ->toStartWith('The disk "custom" has no Flysystem driver.');
});

it('asks for media.url, naming the disk it could have come from', function (): void {
    expect(MediaDiskException::withoutUrl('s3')->getMessage())
        ->toStartWith('Set media.url (MEDIA_URL), or the "url" of the disk "s3"');
});
