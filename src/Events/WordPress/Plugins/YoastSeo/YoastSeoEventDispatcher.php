<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\YoastSeo;

use Pollora\Events\WordPress\AbstractEventDispatcher;

/**
 * Event dispatcher for Yoast SEO plugin events.
 *
 * This class handles the dispatching of Laravel events for Yoast SEO actions
 * such as settings updates, meta updates, and import/export operations.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class YoastSeoEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'added_post_meta',
        'updated_post_meta',
        'deleted_post_meta',
        'wpseo_handle_import',
        'wpseo_import',
        'seo_page_wpseo_files',
    ];

    /**
     * Handle post meta addition for Yoast SEO.
     *
     * @param  int  $meta_id  ID of the metadata entry
     * @param  int  $object_id  ID of the object the metadata is for
     * @param  string  $meta_key  Metadata key
     * @param  mixed  $meta_value  Metadata value
     */
    public function handleAddedPostMeta(int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void
    {
        if (! $this->isYoastSeoMeta($meta_key)) {
            return;
        }

        $this->dispatch(MetaAdded::class, [$object_id, $meta_key, $meta_value]);
    }

    /**
     * Handle post meta update for Yoast SEO.
     *
     * @param  int  $meta_id  ID of the metadata entry
     * @param  int  $object_id  ID of the object the metadata is for
     * @param  string  $meta_key  Metadata key
     * @param  mixed  $meta_value  Metadata value
     */
    public function handleUpdatedPostMeta(int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void
    {
        if (! $this->isYoastSeoMeta($meta_key)) {
            return;
        }

        $this->dispatch(MetaUpdated::class, [$object_id, $meta_key, $meta_value]);
    }

    /**
     * Handle post meta deletion for Yoast SEO.
     *
     * @param  int  $meta_id  ID of the metadata entry
     * @param  int  $object_id  ID of the object the metadata is for
     * @param  string  $meta_key  Metadata key
     * @param  mixed  $meta_value  Metadata value
     */
    public function handleDeletedPostMeta(int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void
    {
        if (! $this->isYoastSeoMeta($meta_key)) {
            return;
        }

        $this->dispatch(MetaDeleted::class, [$object_id, $meta_key, $meta_value]);
    }

    /**
     * Handle Yoast SEO settings import.
     */
    public function handleWpseoHandleImport(): void
    {
        $imports = [
            'importheadspace' => 'HeadSpace2',
            'importaioseo' => 'All-in-One SEO',
            'importaioseoold' => 'OLD All-in-One SEO',
            'importwoo' => 'WooThemes SEO framework',
            'importrobotsmeta' => 'Robots Meta (by Yoast)',
            'importrssfooter' => 'RSS Footer (by Yoast)',
            'importbreadcrumbs' => 'Yoast Breadcrumbs',
        ];

        $options = $_POST['wpseo'] ?? [];

        foreach ($imports as $key => $name) {
            if (! empty($options[$key])) {
                $this->dispatch(SettingsImported::class, [
                    $name,
                    ! empty($options['deleteolddata']),
                ]);
            }
        }
    }

    /**
     * Handle Yoast SEO settings export.
     */
    public function handleWpseoImport(): void
    {
        $options = $_POST['wpseo'] ?? [];

        if (! empty($options['export'])) {
            $this->dispatch(SettingsExported::class, [
                ! empty($options['include_taxonomy_meta']),
            ]);
        }
    }

    /**
     * Handle Yoast SEO file operations.
     */
    public function handleSeoPageWpseoFiles(): void
    {
        $action = '';

        if (! empty($_POST['create_robots'])) {
            $action = 'create_robots';
        } elseif (! empty($_POST['submitrobots'])) {
            $action = 'update_robots';
        } elseif (! empty($_POST['submithtaccess'])) {
            $action = 'update_htaccess';
        }

        if ($action) {
            $this->dispatch(FileUpdated::class, [$action]);
        }
    }

    /**
     * Check if the given meta key is a Yoast SEO meta key.
     *
     * @param  string  $meta_key  Meta key to check
     */
    private function isYoastSeoMeta(string $meta_key): bool
    {
        return str_starts_with($meta_key, '_yoast_wpseo_');
    }
}
