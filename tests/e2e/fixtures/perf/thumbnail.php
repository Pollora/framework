<?php

declare(strict_types=1);

/**
 * Time the generation of the thumbnails of one image, run through `wp eval`.
 *
 * Draws a 2400x1600 picture with GD (no file to ship), registers it as an attachment,
 * generates every registered size as the media library does on upload, prints the elapsed
 * milliseconds as JSON and deletes the attachment and its files. Throws when GD is absent:
 * a number measured without the work would be a lie.
 */
if (! extension_loaded('gd')) {
    throw new RuntimeException('The GD extension is needed to draw the test image.');
}

require_once ABSPATH.'wp-admin/includes/image.php';
require_once ABSPATH.'wp-admin/includes/file.php';

$upload = wp_upload_dir();
$file = $upload['path'].'/e2e-perf-'.wp_generate_uuid4().'.jpg';

$image = imagecreatetruecolor(2400, 1600);

// A gradient and some noise, so the encoder has real work to do.
for ($y = 0; $y < 1600; $y += 8) {
    imagefilledrectangle($image, 0, $y, 2399, $y + 7, imagecolorallocate($image, ($y / 8) % 256, (int) ($y / 8 * 3) % 256, 128));
}

for ($i = 0; $i < 4000; $i++) {
    imagesetpixel($image, random_int(0, 2399), random_int(0, 1599), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
}

imagejpeg($image, $file, 90);
unset($image);

$id = wp_insert_attachment([
    'post_mime_type' => 'image/jpeg',
    'post_title' => 'e2e perf thumbnail',
    'post_status' => 'inherit',
], $file);

try {
    $start = hrtime(true);
    $metadata = wp_generate_attachment_metadata($id, $file);
    $milliseconds = (hrtime(true) - $start) / 1_000_000;

    echo json_encode([
        'thumbnail_ms' => round($milliseconds, 1),
        'sizes' => is_array($metadata) ? count($metadata['sizes'] ?? []) : 0,
    ], JSON_THROW_ON_ERROR);
} finally {
    wp_delete_attachment($id, true);
}
