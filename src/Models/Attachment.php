<?php

declare(strict_types=1);

namespace Pollen\Models;

use Corcel\Model\Collection\MetaCollection;
use Illuminate\Support\Arr;

/**
 * Class Attachment
 *
 * @property int $ID
 * @property int $post_author
 * @property string $post_date
 * @property string $post_date_gmt
 * @property string $post_content
 * @property string $post_title
 * @property string $post_excerpt
 * @property string $post_status
 * @property string $comment_status
 * @property string $ping_status
 * @property string $post_password
 * @property string $post_name
 * @property string $to_ping
 * @property string $pinged
 * @property string $post_modified
 * @property string $post_modified_gmt
 * @property string $post_content_filtered
 * @property int $post_parent
 * @property string $guid
 * @property int $menu_order
 * @property string $post_type
 * @property string $post_mime_type
 * @property int $comment_count
 * @property string|null $filter
 * @property string $title
 * @property string $url
 * @property string $type
 * @property string $description
 * @property string $caption
 * @property string|null $alt
 * @property MetaCollection $meta
 */
class Attachment extends \Corcel\Model\Attachment
{/**
     * Get a specific metadata value.
     *
     * @param string $key
     * @return mixed|null
     */
    protected function getMetaValue(string $key)
    {
        $meta = $this->meta->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : null;
    }

    /**
     * Get the file path of the attachment relative to the uploads directory.
     *
     * @return string|null
     */
    public function getFilePath(): ?string
    {
        return $this->getFileLocation();
    }

    /**
     * Get the file url of the attachment relative to the uploads directory.
     *
     * @return string|null
     */
    public function getFileUrl(): ?string
    {
        return $this->getFileLocation('baseurl');
    }

    /**
     * Get the file url of the attachment relative to the uploads directory.
     *
     * @param string $type
     * @return string|null
     */
    protected function getFileLocation(string $type = 'basedir'): ?string
    {
        $basePath = $this->getUploadPath($type);
        return $basePath . DIRECTORY_SEPARATOR . $this->getMetaValue('_wp_attached_file');
    }

    /**
     * Get the width of the attachment (for images).
     *
     * @return int|null
     */
    public function getWidth(): ?int
    {
        $metadata = $this->getAttachmentMetadata();
        return $metadata['width'] ?? null;
    }

    /**
     * Get the height of the attachment (for images).
     *
     * @return int|null
     */
    public function getHeight(): ?int
    {
        $metadata = $this->getAttachmentMetadata();
        return $metadata['height'] ?? null;
    }

    /**
     * Get the parsed attachment metadata.
     *
     * @return array
     */
    public function getAttachmentMetadata(): array
    {
        $metaValue = $this->getMetaValue('_wp_attachment_metadata');
        if (!$metaValue) {
            return [];
        }

        // WordPress stores this as a serialized array
        $unserialized = @unserialize($metaValue);
        return is_array($unserialized) ? $unserialized : [];
    }

    /**
     * Get all available sizes for the image.
     *
     * @return array
     */
    public function getSizes(): array
    {
        $metadata = $this->getAttachmentMetadata();
        return Arr::get($metadata, 'sizes', []);
    }

    /**
     * Get the path for a specific image size.
     *
     * @param string $size
     * @return string|null
     */
    public function getSizePath(string $size): ?string
    {
        return $this->getSizeLocation($size);
    }

    /**
     * Get the url for a specific image size.
     *
     * @param string $size
     * @return string|null
     */
    public function getSizeUrl(string $size): ?string
    {
        return $this->getSizeLocation($size, 'baseurl');
    }

    /**
     * Get the path for a specific image size.
     *
     * @param string $size
     * @param string $type
     * @return string|null
     */
    protected function getSizeLocation(string $size, string $type = 'basedir'): ?string
    {
        $sizes = $this->getSizes();
        if (!isset($sizes[$size])) {
            return null;
        }

        $baseUrl = $this->getUploadPath($type);
        $relativeFilePath = $this->getFilePath();
        $dirName = dirname($relativeFilePath);

        return $dirName . '/' . $sizes[$size]['file'];
    }

    protected function getUploadPath(string $type = 'basedir'): string
    {
        $uploadDir = wp_upload_dir();
        return $uploadDir[$type];
    }
}
