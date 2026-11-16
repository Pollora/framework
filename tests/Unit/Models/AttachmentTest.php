<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Models\Attachment;

/**
 * @param  array<string, mixed>  $meta  Meta rows by key
 */
function attachmentWithMeta(array $meta): Attachment
{
    $attachment = new Attachment;
    $attachment->setRelation('meta', collect(array_map(
        fn (string $key, mixed $value): object => (object) ['meta_key' => $key, 'meta_value' => $value],
        array_keys($meta),
        array_values($meta),
    )));

    return $attachment;
}

beforeEach(function (): void {
    Functions\when('wp_upload_dir')->justReturn(['basedir' => '/srv/uploads', 'baseurl' => 'https://example.test/uploads']);
});

describe('Attachment files', function (): void {
    it('puts the attached file under the uploads directory', function (): void {
        $attachment = attachmentWithMeta(['_wp_attached_file' => '2026/11/photo.jpg']);

        expect($attachment->getFilePath())->toBe('/srv/uploads/2026/11/photo.jpg')
            ->and($attachment->getFileUrl())->toBe('https://example.test/uploads/2026/11/photo.jpg');
    });
});

describe('Attachment metadata', function (): void {
    it('reads the width, the height and the sizes from the serialized metadata', function (): void {
        $attachment = attachmentWithMeta([
            '_wp_attachment_metadata' => serialize(['width' => 1200, 'height' => 800, 'sizes' => ['thumbnail' => ['file' => 'photo-150x150.jpg']]]),
        ]);

        expect($attachment->getWidth())->toBe(1200)
            ->and($attachment->getHeight())->toBe(800)
            ->and($attachment->getSizes())->toBe(['thumbnail' => ['file' => 'photo-150x150.jpg']]);
    });

    it('has no metadata, size or dimension when WordPress stored none', function (): void {
        $attachment = attachmentWithMeta([]);

        expect($attachment->getAttachmentMetadata())->toBe([])
            ->and($attachment->getWidth())->toBeNull()
            ->and($attachment->getHeight())->toBeNull()
            ->and($attachment->getSizes())->toBe([]);
    });

    it('ignores metadata that is not a serialized array, and never instantiates an object', function (mixed $stored): void {
        expect(attachmentWithMeta(['_wp_attachment_metadata' => $stored])->getAttachmentMetadata())->toBe([]);
    })->with([
        'a number' => [42],
        'a serialized string' => [serialize('text')],
        'a serialized object' => [serialize(new stdClass)],
    ]);
});

describe('Attachment sizes', function (): void {
    beforeEach(function (): void {
        $this->attachment = attachmentWithMeta([
            '_wp_attached_file' => '2026/11/photo.jpg',
            '_wp_attachment_metadata' => serialize(['sizes' => ['thumbnail' => ['file' => 'photo-150x150.jpg']]]),
        ]);
    });

    it('points to the file of a size next to the original', function (): void {
        expect($this->attachment->getSizePath('thumbnail'))->toBe('/srv/uploads/2026/11/photo-150x150.jpg');
    });

    it('has no path or URL for a size that does not exist', function (): void {
        expect($this->attachment->getSizePath('huge'))->toBeNull()
            ->and($this->attachment->getSizeUrl('huge'))->toBeNull();
    });
});
