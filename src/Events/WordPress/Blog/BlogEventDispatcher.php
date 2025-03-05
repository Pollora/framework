<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Site;

/**
 * Event dispatcher for WordPress multisite blog-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress multisite blog actions
 * such as creation, deletion, archiving, and status changes.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'wp_initialize_site',
        'wp_delete_site',
        'archive_blog',
        'unarchive_blog',
        'make_spam_blog',
        'make_ham_blog',
        'mature_blog',
        'unmature_blog',
        'make_delete_blog',
        'make_undelete_blog',
        'update_blog_public',
    ];

    /**
     * Handle blog creation.
     *
     * @param WP_Site $new_site New site object
     * @param array $args Arguments for the initialization
     */
    public function handleWpInitializeSite(WP_Site $new_site, array $args): void
    {
        $this->dispatch(BlogCreated::class, [$new_site, $args]);
    }

    /**
     * Handle blog deletion.
     *
     * @param WP_Site $old_site Deleted site object
     */
    public function handleWpDeleteSite(WP_Site $old_site): void
    {
        $this->dispatch(BlogDeleted::class, [$old_site]);
    }

    /**
     * Handle blog archiving.
     *
     * @param int $blog_id Blog ID
     */
    public function handleArchiveBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogArchived::class, [$site]);
        }
    }

    /**
     * Handle blog unarchiving.
     *
     * @param int $blog_id Blog ID
     */
    public function handleUnarchiveBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogUnarchived::class, [$site]);
        }
    }

    /**
     * Handle blog marked as spam.
     *
     * @param int $blog_id Blog ID
     */
    public function handleMakeSpamBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogMarkedAsSpam::class, [$site]);
        }
    }

    /**
     * Handle blog marked as not spam.
     *
     * @param int $blog_id Blog ID
     */
    public function handleMakeHamBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogMarkedAsNotSpam::class, [$site]);
        }
    }

    /**
     * Handle blog marked as mature.
     *
     * @param int $blog_id Blog ID
     */
    public function handleMatureBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogMarkedAsMature::class, [$site]);
        }
    }

    /**
     * Handle blog marked as not mature.
     *
     * @param int $blog_id Blog ID
     */
    public function handleUnmatureBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogMarkedAsNotMature::class, [$site]);
        }
    }

    /**
     * Handle blog marked as deleted.
     *
     * @param int $blog_id Blog ID
     */
    public function handleMakeDeleteBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogTrashed::class, [$site]);
        }
    }

    /**
     * Handle blog restored from trash.
     *
     * @param int $blog_id Blog ID
     */
    public function handleMakeUndeleteBlog(int $blog_id): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogRestored::class, [$site]);
        }
    }

    /**
     * Handle blog visibility update.
     *
     * @param int $blog_id Blog ID
     * @param string $value New visibility value
     */
    public function handleUpdateBlogPublic(int $blog_id, string $value): void
    {
        if ($site = get_site($blog_id)) {
            $this->dispatch(BlogVisibilityUpdated::class, [$site, $value]);
        }
    }
} 