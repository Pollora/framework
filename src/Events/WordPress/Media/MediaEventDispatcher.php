<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Media;

use Pollora\Events\WordPress\AbstractEventDispatcher;

/**
 * Event dispatcher for WordPress media-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress media actions
 * such as upload, update, deletion, and image editing.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class MediaEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'add_attachment',
        'edit_attachment',
        'delete_attachment',
        //'wp_save_image_editor_file',
        //'wp_save_image_file',
    ];

    /**
     * Handle media attachment creation.
     *
     * @param  int  $post_id  Attachment post ID
     */
    public function handleAddAttachment(int $post_id): void
    {
        $attachment = get_post($post_id);

        if (! $attachment || $attachment->post_type !== 'attachment') {
            return;
        }

        $this->dispatch(MediaCreated::class, [$attachment]);
    }

    /**
     * Handle media attachment update.
     *
     * @param  int  $post_id  Attachment post ID
     */
    public function handleEditAttachment(int $post_id): void
    {
        $attachment = get_post($post_id);

        if (! $attachment || $attachment->post_type !== 'attachment') {
            return;
        }

        $this->dispatch(MediaUpdated::class, [$attachment]);
    }

    /**
     * Handle media attachment deletion.
     *
     * @param  int  $post_id  Attachment post ID
     */
    public function handleDeleteAttachment(int $post_id): void
    {
        $attachment = get_post($post_id);

        if (! $attachment || $attachment->post_type !== 'attachment') {
            return;
        }

        $this->dispatch(MediaDeleted::class, [$attachment]);
    }

    /**
     * Handle image editing.
     *
     * @param  string  $dummy  Unused parameter
     * @param  string  $filename  The edited image filename
     * @param  string  $image  Unused parameter
     * @param  string  $mime_type  Unused parameter
     * @param  int  $post_id  Attachment post ID
     */
    public function handleImageEdit(string $dummy, string $filename, string $image, string $mime_type, int $post_id): void
    {
        $attachment = get_post($post_id);

        if (! $attachment || $attachment->post_type !== 'attachment') {
            return;
        }

        $this->dispatch(MediaEdited::class, [$attachment, $filename]);
    }
}
