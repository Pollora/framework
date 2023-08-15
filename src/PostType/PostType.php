<?php

declare(strict_types=1);

namespace Pollen\PostType;

use Pollen\Support\ExtendedCpt;
use Pollen\Support\Facades\Action;
use Pollen\Support\WordPressArgumentHelper;

class PostType
{
    use WordPressArgumentHelper;
    use ExtendedCpt;

    /**
     * Whether to exclude posts with this post type from front end search
     * results.
     *
     * Default is the opposite value of $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $excludeFromSearch;

    /**
     * Whether the post type is hierarchical (e.g. page).
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $hierarchical;

    /**
     * The position in the menu order the post type should appear.
     *
     * To work, $show_in_menu must be true. Default null (at the bottom).
     *
     * @since 4.6.0
     *
     * @var int
     */
    public $menuPosition;

    /**
     * Makes this post type available via the admin bar.
     *
     * Default is the value of $show_in_menu.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showInAdminBar;

    /**
     * The URL or reference to the icon to be used for this menu.
     *
     * Pass a base64-encoded SVG using a data URI, which will be colored to match the color scheme.
     * This should begin with 'data:image/svg+xml;base64,'. Pass the name of a Dashicons helper class
     * to use a font icon, e.g. 'dashicons-chart-pie'. Pass 'none' to leave div.wp-menu-image empty
     * so an icon can be added via CSS.
     *
     * Defaults to use the posts icon.
     *
     * @since 4.6.0
     *
     * @var string
     */
    public $menuIcon;

    /**
     * The string to use to build the read, edit, and delete capabilities.
     *
     * May be passed as an array to allow for alternative plurals when using
     * this argument as a base to construct the capabilities, e.g.
     * array( 'story', 'stories' ). Default 'post'.
     *
     * @since 4.6.0
     *
     * @var string
     */
    public $capabilityType;

    /**
     * Whether to use the internal default meta capability handling.
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $mapMetaCap;

    /**
     * Provide a callback function that sets up the meta boxes for the edit form.
     *
     * Do `remove_meta_box()` and `add_meta_box()` calls in the callback. Default null.
     *
     * @since 4.6.0
     *
     * @var callable
     */
    public $registerMetaBoxCb;

    /**
     * An array of taxonomy identifiers that will be registered for the post type.
     *
     * Taxonomies can be registered later with `register_taxonomy()` or `register_taxonomy_for_object_type()`.
     *
     * Default empty array.
     *
     * @since 4.6.0
     *
     * @var string[]
     */
    public $taxonomies;

    /**
     * Whether there should be post type archives, or if a string, the archive slug to use.
     *
     * Will generate the proper rewrite rules if $rewrite is enabled. Default false.
     *
     * @since 4.6.0
     *
     * @var bool|string
     */
    public $hasArchive;

    /**
     * Whether to allow this post type to be exported.
     *
     * Default true.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $canExport;

    /**
     * Whether to delete posts of this type when deleting a user.
     *
     * - If true, posts of this type belonging to the user will be moved to Trash when the user is deleted.
     * - If false, posts of this type belonging to the user will *not* be trashed or deleted.
     * - If not set (the default), posts are trashed if post type supports the 'author' feature.
     *   Otherwise posts are not trashed or deleted.
     *
     * Default null.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $deleteWithUser;

    /**
     * Array of blocks to use as the default initial state for an editor session.
     *
     * Each item should be an array containing block name and optional attributes.
     *
     * Default empty array.
     *
     * @link https://developer.wordpress.org/block-editor/developers/block-api/block-templates/
     * @since 5.0.0
     *
     * @var array[]
     */
    public $template;

    /**
     * Whether the block template should be locked if $template is set.
     *
     * - If set to 'all', the user is unable to insert new blocks, move existing blocks
     *   and delete blocks.
     * - If set to 'insert', the user is able to move existing blocks but is unable to insert
     *   new blocks and delete blocks.
     *
     * Default false.
     *
     * @link https://developer.wordpress.org/block-editor/developers/block-api/block-templates/
     * @since 5.0.0
     *
     * @var string|false
     */
    public $templateLock;

    /**
     * Post type capabilities.
     *
     * @since 4.6.0
     *
     * @var stdClass
     */
    public $cap;

    /**
     * The features supported by the post type.
     *
     * @since 4.6.0
     *
     * @var array|bool
     */
    public $supports;

    /**
     * Associative array of admin screen filters to show for this post type.
     *
     * @var array<string,mixed>
     */
    public $adminFilters;

    /**
     * Associative array of query vars to override on this post type's archive.
     *
     * @var array<string,mixed>
     */
    public $archive;

    /**
     * Force the use of the block editor for this post type. Must be used in
     * combination with the `show_in_rest` argument.
     *
     * The primary use of this argument
     * is to prevent the block editor from being used by setting it to false when
     * `show_in_rest` is set to true.
     */
    public $blockEditor;

    /**
     * Whether to show this post type on the 'Recently Published' section of the
     * admin dashboard.
     *
     * Default true.
     */
    public $dashboardActivity;

    /**
     * Placeholder text which appears in the title field for this post type.
     */
    public $enterTitleHere;

    /**
     * Text which replaces the 'Featured Image' phrase for this post type.
     */
    public $featuredImage;

    /**
     * Whether to show Quick Edit links for this post type.
     *
     * Default true.
     */
    public $quickEdit;

    /**
     * Whether to include this post type in the site's main feed.
     *
     * Default false.
     */
    public $showInFeed;

    /**
     * Associative array of query vars and their parameters for front end filtering.
     *
     * @var array<string,mixed>
     */
    public $siteFilters;

    /**
     * Associative array of query vars and their parameters for front end sorting.
     *
     * @var array<string,mixed>
     */
    public $siteSortables;

    public function getExcludeFromSearch(): ?bool
    {
        return $this->excludeFromSearch;
    }

    public function excludeFromSearch(): self
    {
        $this->excludeFromSearch = true;

        return $this;
    }

    public function setExcludeFromSearch(?bool $excludeFromSearch): self
    {
        $this->excludeFromSearch = $excludeFromSearch;

        return $this;
    }

    public function isHierarchical(): ?bool
    {
        return $this->hierarchical;
    }

    public function hierarchical(): self
    {
        $this->hierarchical = true;

        return $this;
    }

    public function chronological(): self
    {
        $this->hierarchical = false;

        return $this;
    }

    public function setHierarchical(bool $hierarchical): self
    {
        $this->hierarchical = $hierarchical;

        return $this;
    }

    public function getShowInAdminBar(): ?bool
    {
        return $this->showInAdminBar;
    }

    public function showInAdminBar(): self
    {
        $this->showInAdminBar = true;

        return $this;
    }

    public function setShowInAdminBar(?bool $showInAdminBar): self
    {
        $this->showInAdminBar = $showInAdminBar;

        return $this;
    }

    public function getMenuPosition(): ?int
    {
        return $this->menuPosition;
    }

    public function setMenuPosition(?int $menuPosition): self
    {
        $this->menuPosition = $menuPosition;

        return $this;
    }

    public function getMenuIcon(): ?string
    {
        return $this->menuIcon;
    }

    public function setMenuIcon(?string $menuIcon): self
    {
        $this->menuIcon = $menuIcon;

        return $this;
    }

    public function getCapabilityType(): ?string
    {
        return $this->capabilityType;
    }

    public function setCapabilityType(string $capabilityType): self
    {
        $this->capabilityType = $capabilityType;

        return $this;
    }

    public function isMapMetaCap(): ?bool
    {
        return $this->mapMetaCap;
    }

    public function mapMetaCap(): self
    {
        $this->mapMetaCap = true;

        return $this;
    }

    public function setMapMetaCap(bool $mapMetaCap): self
    {
        $this->mapMetaCap = $mapMetaCap;

        return $this;
    }

    public function getRegisterMetaBoxCb(): ?callable
    {
        return $this->registerMetaBoxCb;
    }

    public function setRegisterMetaBoxCb(?callable $registerMetaBoxCb): self
    {
        $this->registerMetaBoxCb = $registerMetaBoxCb;

        return $this;
    }

    public function getTaxonomies(): ?array
    {
        return $this->taxonomies;
    }

    public function setTaxonomies(array $taxonomies): ?self
    {
        $this->taxonomies = $taxonomies;

        return $this;
    }

    public function getHasArchive(): bool|string|null
    {
        return $this->hasArchive;
    }

    public function hasArchive(bool|string $hasArchive = true): self
    {
        $this->hasArchive = $hasArchive;

        return $this;
    }

    public function getCanExport(): ?bool
    {
        return $this->canExport;
    }

    public function canExport(): self
    {
        $this->canExport = true;

        return $this;
    }

    public function setCanExport(bool $canExport): self
    {
        $this->canExport = $canExport;

        return $this;
    }

    public function getDeleteWithUser(): ?bool
    {
        return $this->deleteWithUser;
    }

    public function deletedWithUser(): self
    {
        $this->deleteWithUser = true;

        return $this;
    }

    public function setDeleteWithUser(?bool $deleteWithUser): self
    {
        $this->deleteWithUser = $deleteWithUser;

        return $this;
    }

    public function getTemplate(): ?array
    {
        return $this->template;
    }

    public function setTemplate(array $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplateLock(): bool|string|null
    {
        return $this->templateLock;
    }

    public function setTemplateLock(bool|string $templateLock): self
    {
        $this->templateLock = $templateLock;

        return $this;
    }

    public function getCap(): stdClass|null
    {
        return $this->cap;
    }

    public function setCap(stdClass $cap): self
    {
        $this->cap = $cap;

        return $this;
    }

    public function getSupports(): bool|array|null
    {
        return $this->supports;
    }

    public function supports(bool|array $supports): self
    {
        $this->supports = $supports;

        return $this;
    }

    public function getAdminFilters(): ?array
    {
        return $this->adminFilters;
    }

    public function adminFilters(array $adminFilters): self
    {
        $this->adminFilters = $adminFilters;

        return $this;
    }

    public function getArchive(): ?array
    {
        return $this->archive;
    }

    public function setArchive(array $archive): self
    {
        $this->archive = $archive;

        return $this;
    }

    public function isBlockEditor(): ?bool
    {
        return $this->blockEditor;
    }

    public function enableBlockEditor(): self
    {
        $this->blockEditor = true;

        return $this;
    }

    public function setBlockEditor(bool $blockEditor): self
    {
        $this->blockEditor = $blockEditor;

        return $this;
    }

    public function isDashboardActivity(): ?bool
    {
        return $this->dashboardActivity;
    }

    public function enableDashboardActivity(): self
    {
        $this->dashboardActivity = true;

        return $this;
    }

    public function setDashboardActivity(bool $dashboardActivity): self
    {
        $this->dashboardActivity = $dashboardActivity;

        return $this;
    }

    public function getEnterTitleHere(): ?string
    {
        return $this->enterTitleHere;
    }

    public function titlePlaceholder(string $enterTitleHere): self
    {
        $this->enterTitleHere = $enterTitleHere;

        return $this;
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(string $featuredImage): self
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function isQuickEdit(): ?bool
    {
        return $this->quickEdit;
    }

    public function setQuickEdit(bool $quickEdit): self
    {
        $this->quickEdit = $quickEdit;

        return $this;
    }

    public function enableQuickEdit(): self
    {
        $this->quickEdit = true;

        return $this;
    }

    public function isShowInFeed(): ?bool
    {
        return $this->showInFeed;
    }

    public function setShowInFeed(bool $showInFeed): self
    {
        $this->showInFeed = $showInFeed;

        return $this;
    }

    public function showInFeed(): self
    {
        $this->showInFeed = true;

        return $this;
    }

    public function getSiteFilters(): ?array
    {
        return $this->siteFilters;
    }

    public function siteFilters(array $siteFilters): self
    {
        $this->siteFilters = $siteFilters;

        return $this;
    }

    public function getSiteSortables(): ?array
    {
        return $this->siteSortables;
    }

    public function siteSortables(array $siteSortables): self
    {
        $this->siteSortables = $siteSortables;

        return $this;
    }

    public function __construct(
        public string $slug,
        string $singular = null,
        string $plural = null
    ) {
        $this->setSingular($singular);
        $this->setPlural($plural);
        $this->register();
    }

    public function register()
    {
        Action::add('init', function () {

            $args = $this->buildArguments();
            $args = $this->translateArguments($args, 'post-types');

            $names = $args['names'];

            // Unset names from item
            unset($args['names']);

            register_extended_post_type($this->slug, $args, $names);
        }, 99);

    }
}
