<?php

declare(strict_types=1);

namespace Pollen\PostType;

use Pollen\Services\Translater;
use Pollen\Support\WordPressArgumentHelper;

class PostType
{
    use WordPressArgumentHelper;

    /**
     * Raw post type args.
     *
     * @var args
     */
    protected $args;

    /**
     * Name of the post type shown in the menu. Usually plural.
     *
     * @since 4.6.0
     *
     * @var string
     */
    public $label;

    /**
     * Labels object for this post type.
     *
     * If not set, post labels are inherited for non-hierarchical types
     * and page labels for hierarchical ones.
     *
     * @see get_post_type_labels()
     * @since 4.6.0
     *
     * @var stdClass
     */
    public $labels;

    /**
     * A short descriptive summary of what the post type is.
     *
     * Default empty.
     *
     * @since 4.6.0
     *
     * @var string
     */
    public $description = '';

    /**
     * Whether a post type is intended for use publicly either via the admin interface or by front-end users.
     *
     * While the default settings of $exclude_from_search, $publicly_queryable, $show_ui, and $show_in_nav_menus
     * are inherited from public, each does not rely on this relationship and controls a very specific intention.
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $public = false;

    /**
     * Whether the post type is hierarchical (e.g. page).
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $hierarchical = false;

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
    public $excludeFromSearch = null;

    /**
     * Whether queries can be performed on the front end for the post type as part of `parse_request()`.
     *
     * Endpoints would include:
     *
     * - `?post_type={post_type_key}`
     * - `?{post_type_key}={single_post_slug}`
     * - `?{post_type_query_var}={single_post_slug}`
     *
     * Default is the value of $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $publiclyQueryable = null;

    /**
     * Whether to generate and allow a UI for managing this post type in the admin.
     *
     * Default is the value of $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showUi = null;

    /**
     * Where to show the post type in the admin menu.
     *
     * To work, $show_ui must be true. If true, the post type is shown in its own top level menu. If false, no menu is
     * shown. If a string of an existing top level menu ('tools.php' or 'edit.php?post_type=page', for example), the
     * post type will be placed as a sub-menu of that.
     *
     * Default is the value of $show_ui.
     *
     * @since 4.6.0
     *
     * @var bool|string
     */
    public $showInMenu = null;

    /**
     * Makes this post type available for selection in navigation menus.
     *
     * Default is the value $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showInNavMenus = null;

    /**
     * Makes this post type available via the admin bar.
     *
     * Default is the value of $show_in_menu.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showInAdmin_bar = null;

    /**
     * The position in the menu order the post type should appear.
     *
     * To work, $show_in_menu must be true. Default null (at the bottom).
     *
     * @since 4.6.0
     *
     * @var int
     */
    public $menuPosition = null;

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
    public $menuIcon = null;

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
    public $capabilityType = 'post';

    /**
     * Whether to use the internal default meta capability handling.
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $mapMetaCap = false;

    /**
     * Provide a callback function that sets up the meta boxes for the edit form.
     *
     * Do `remove_meta_box()` and `add_meta_box()` calls in the callback. Default null.
     *
     * @since 4.6.0
     *
     * @var callable
     */
    public $registerMetaBoxCb = null;

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
    public $taxonomies = [];

    /**
     * Whether there should be post type archives, or if a string, the archive slug to use.
     *
     * Will generate the proper rewrite rules if $rewrite is enabled. Default false.
     *
     * @since 4.6.0
     *
     * @var bool|string
     */
    public $hasArchive = false;

    /**
     * Sets the query_var key for this post type.
     *
     * Defaults to $post_type key. If false, a post type cannot be loaded at `?{query_var}={post_slug}`.
     * If specified as a string, the query `?{query_var_string}={post_slug}` will be valid.
     *
     * @since 4.6.0
     *
     * @var string|bool
     */
    public $queryVar;

    /**
     * Whether to allow this post type to be exported.
     *
     * Default true.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $canExport = true;

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
    public $deleteWithUser = null;

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
    public $template = [];

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
    public $templateLock = false;

    /**
     * Whether this post type is a native or "built-in" post_type.
     *
     * Default false.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $_builtin = false;

    /**
     * URL segment to use for edit link of this post type.
     *
     * Default 'post.php?post=%d'.
     *
     * @since 4.6.0
     *
     * @var string
     */
    public $_editLink = 'post.php?post=%d';

    /**
     * Post type capabilities.
     *
     * @since 4.6.0
     *
     * @var stdClass
     */
    public $cap;

    /**
     * Triggers the handling of rewrites for this post type.
     *
     * Defaults to true, using $post_type as slug.
     *
     * @since 4.6.0
     *
     * @var array|false
     */
    public $rewrite;

    /**
     * The features supported by the post type.
     *
     * @since 4.6.0
     *
     * @var array|bool
     */
    public $supports;

    /**
     * Whether this post type should appear in the REST API.
     *
     * Default false. If true, standard endpoints will be registered with
     * respect to $rest_base and $rest_controller_class.
     *
     * @since 4.7.4
     *
     * @var bool
     */
    public $showInRest;

    /**
     * The base path for this post type's REST API endpoints.
     *
     * @since 4.7.4
     *
     * @var string|bool
     */
    public $restBase;

    /**
     * The namespace for this post type's REST API endpoints.
     *
     * @since 5.9.0
     *
     * @var string|bool
     */
    public $restNamespace;

    /**
     * The controller for this post type's REST API endpoints.
     *
     * Custom controllers must extend WP_REST_Controller.
     *
     * @since 4.7.4
     *
     * @var string|bool
     */
    public $restControllerClass;

    /**
     * The controller instance for this post type's REST API endpoints.
     *
     * Lazily computed. Should be accessed using {@see WP_Post_Type::get_rest_controller()}.
     *
     * @since 5.3.0
     *
     * @var WP_REST_Controller
     */
    public $restController;

    /**
     * Associative array of admin screen columns to show for this post type.
     *
     * @var array<string,mixed>
     */
    public $adminCols;

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
     * Whether to show this post type on the 'At a Glance' section of the admin
     * dashboard.
     *
     * Default true.
     */
    public $dashboardGlance;

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

    public function setArgs(array $args): PostType
    {
        $this->args = $args;

        return $this;
    }

    public function getArgs(): array|null
    {
        return $this->args;
    }

    public function getLabel(): string|null
    {
        return $this->label;
    }

    public function setSingular(string $singular): PostType
    {
        $this->names['singular'] = $singular;

        return $this;
    }

    public function setPlural(string $plural): PostType
    {
        $this->names['plural'] = $plural;

        return $this;
    }

    public function setSlug(string $slug): PostType
    {
        $this->names['slug'] = $slug;

        return $this;
    }

    public function setNames(array $names): PostType
    {
        $this->names = $names;

        return $this;
    }

    public function getNames(): array
    {
        return $this->names ?? [];
    }

    public function setLabel(string $label): PostType
    {
        $this->label = $label;

        return $this;
    }

    public function getLabels(): stdClass|null
    {
        return $this->labels;
    }

    public function setLabels(stdClass $labels): PostType
    {
        $this->labels = $labels;

        return $this;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function setDescription(string $description): PostType
    {
        $this->description = $description;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): PostType
    {
        $this->public = $public;

        return $this;
    }

    public function isHierarchical(): bool
    {
        return $this->hierarchical;
    }

    public function setHierarchical(bool $hierarchical): PostType
    {
        $this->hierarchical = $hierarchical;

        return $this;
    }

    public function getExcludeFromSearch(): ?bool
    {
        return $this->excludeFromSearch;
    }

    public function setExcludeFromSearch(?bool $excludeFromSearch): PostType
    {
        $this->excludeFromSearch = $excludeFromSearch;

        return $this;
    }

    public function getPubliclyQueryable(): ?bool
    {
        return $this->publiclyQueryable;
    }

    public function setPubliclyQueryable(?bool $publiclyQueryable): PostType
    {
        $this->publiclyQueryable = $publiclyQueryable;

        return $this;
    }

    public function getShowUi(): ?bool
    {
        return $this->showUi;
    }

    public function setShowUi(?bool $showUi): PostType
    {
        $this->showUi = $showUi;

        return $this;
    }

    public function getShowInMenu(): bool|string|null
    {
        return $this->showInMenu;
    }

    public function setShowInMenu(bool|string|null $showInMenu): PostType
    {
        $this->showInMenu = $showInMenu;

        return $this;
    }

    public function getShowInNavMenus(): ?bool
    {
        return $this->showInNavMenus;
    }

    public function setShowInNavMenus(?bool $showInNavMenus): PostType
    {
        $this->showInNavMenus = $showInNavMenus;

        return $this;
    }

    public function getShowInAdminBar(): ?bool
    {
        return $this->showInAdmin_bar;
    }

    public function setShowInAdminBar(?bool $showInAdmin_bar): PostType
    {
        $this->showInAdmin_bar = $showInAdmin_bar;

        return $this;
    }

    public function getMenuPosition(): ?int
    {
        return $this->menuPosition;
    }

    public function setMenuPosition(?int $menuPosition): PostType
    {
        $this->menuPosition = $menuPosition;

        return $this;
    }

    public function getMenuIcon(): ?string
    {
        return $this->menuIcon;
    }

    public function setMenuIcon(?string $menuIcon): PostType
    {
        $this->menuIcon = $menuIcon;

        return $this;
    }

    public function getCapabilityType(): string
    {
        return $this->capabilityType;
    }

    public function setCapabilityType(string $capabilityType): PostType
    {
        $this->capabilityType = $capabilityType;

        return $this;
    }

    public function isMapMetaCap(): bool
    {
        return $this->mapMetaCap;
    }

    public function setMapMetaCap(bool $mapMetaCap): PostType
    {
        $this->mapMetaCap = $mapMetaCap;

        return $this;
    }

    public function getRegisterMetaBoxCb(): ?callable
    {
        return $this->registerMetaBoxCb;
    }

    public function setRegisterMetaBoxCb(?callable $registerMetaBoxCb): PostType
    {
        $this->registerMetaBoxCb = $registerMetaBoxCb;

        return $this;
    }

    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    public function setTaxonomies(array $taxonomies): PostType
    {
        $this->taxonomies = $taxonomies;

        return $this;
    }

    public function getHasArchive(): bool|string
    {
        return $this->hasArchive;
    }

    public function setHasArchive(bool|string $hasArchive): PostType
    {
        $this->hasArchive = $hasArchive;

        return $this;
    }

    public function getQueryVar(): bool|string|null
    {
        return $this->queryVar;
    }

    public function setQueryVar(bool|string $queryVar): PostType
    {
        $this->queryVar = $queryVar;

        return $this;
    }

    public function isCanExport(): bool
    {
        return $this->canExport;
    }

    public function setCanExport(bool $canExport): PostType
    {
        $this->canExport = $canExport;

        return $this;
    }

    public function getDeleteWithUser(): ?bool
    {
        return $this->deleteWithUser;
    }

    public function setDeleteWithUser(?bool $deleteWithUser): PostType
    {
        $this->deleteWithUser = $deleteWithUser;

        return $this;
    }

    public function getTemplate(): array
    {
        return $this->template;
    }

    public function setTemplate(array $template): PostType
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplateLock(): bool|string
    {
        return $this->templateLock;
    }

    public function setTemplateLock(bool|string $templateLock): PostType
    {
        $this->templateLock = $templateLock;

        return $this;
    }

    public function isBuiltin(): bool
    {
        return $this->_builtin;
    }

    public function setBuiltin(bool $builtin): PostType
    {
        $this->_builtin = $builtin;

        return $this;
    }

    public function getEditLink(): string
    {
        return $this->_editLink;
    }

    public function setEditLink(string $editLink): PostType
    {
        $this->_editLink = $editLink;

        return $this;
    }

    public function getCap(): stdClass|null
    {
        return $this->cap;
    }

    public function setCap(stdClass $cap): PostType
    {
        $this->cap = $cap;

        return $this;
    }

    public function getRewrite(): bool|array|null
    {
        return $this->rewrite;
    }

    public function setRewrite(bool|array $rewrite): PostType
    {
        $this->rewrite = $rewrite;

        return $this;
    }

    public function getSupports(): bool|array|null
    {
        return $this->supports;
    }

    public function setSupports(bool|array $supports): PostType
    {
        $this->supports = $supports;

        return $this;
    }

    public function isShowInRest(): bool
    {
        return $this->showInRest;
    }

    public function setShowInRest(bool $showInRest): PostType
    {
        $this->showInRest = $showInRest;

        return $this;
    }

    public function getRestBase(): bool|string|null
    {
        return $this->restBase;
    }

    public function setRestBase(bool|string $restBase): PostType
    {
        $this->restBase = $restBase;

        return $this;
    }

    public function getRestNamespace(): bool|string|null
    {
        return $this->restNamespace;
    }

    public function setRestNamespace(bool|string $restNamespace): PostType
    {
        $this->restNamespace = $restNamespace;

        return $this;
    }

    public function getRestControllerClass(): bool|string|null
    {
        return $this->restControllerClass;
    }

    public function setRestControllerClass(bool|string $restControllerClass): PostType
    {
        $this->restControllerClass = $restControllerClass;

        return $this;
    }

    public function getRestController(): WP_REST_Controller|null
    {
        return $this->restController;
    }

    public function setRestController(WP_REST_Controller $restController): PostType
    {
        $this->restController = $restController;

        return $this;
    }

    public function getAdminCols(): array|null
    {
        return $this->adminCols;
    }

    public function setAdminCols(array $adminCols): PostType
    {
        $this->adminCols = $adminCols;

        return $this;
    }

    public function getAdminFilters(): array|null
    {
        return $this->adminFilters;
    }

    public function setAdminFilters(array $adminFilters): PostType
    {
        $this->adminFilters = $adminFilters;

        return $this;
    }

    public function getArchive(): array|null
    {
        return $this->archive;
    }

    public function setArchive(array $archive): PostType
    {
        $this->archive = $archive;

        return $this;
    }

    public function isBlockEditor(): bool
    {
        return $this->blockEditor;
    }

    public function setBlockEditor(bool $blockEditor): PostType
    {
        $this->blockEditor = $blockEditor;

        return $this;
    }

    public function isDashboardGlance(): bool
    {
        return $this->dashboardGlance;
    }

    public function setDashboardGlance(bool $dashboardGlance): PostType
    {
        $this->dashboardGlance = $dashboardGlance;

        return $this;
    }

    public function isDashboardActivity(): bool
    {
        return $this->dashboardActivity;
    }

    public function setDashboardActivity(bool $dashboardActivity): PostType
    {
        $this->dashboardActivity = $dashboardActivity;

        return $this;
    }

    public function getEnterTitleHere(): string|null
    {
        return $this->enterTitleHere;
    }

    public function setEnterTitleHere(string $enterTitleHere): PostType
    {
        $this->enterTitleHere = $enterTitleHere;

        return $this;
    }

    public function getFeaturedImage(): string|null
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(string $featuredImage): PostType
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function isQuickEdit(): bool
    {
        return $this->quickEdit;
    }

    public function setQuickEdit(bool $quickEdit): PostType
    {
        $this->quickEdit = $quickEdit;

        return $this;
    }

    public function isShowInFeed(): bool
    {
        return $this->showInFeed;
    }

    public function setShowInFeed(bool $showInFeed): PostType
    {
        $this->showInFeed = $showInFeed;

        return $this;
    }

    public function getSiteFilters(): array|null
    {
        return $this->siteFilters;
    }

    public function setSiteFilters(array $siteFilters): PostType
    {
        $this->siteFilters = $siteFilters;

        return $this;
    }

    public function getSiteSortables(): array|null
    {
        return $this->siteSortables;
    }

    public function setSiteSortables(array $siteSortables): PostType
    {
        $this->siteSortables = $siteSortables;

        return $this;
    }

    public function __construct(
        public string $slug,
        public array $names = [])
    {
    }

    public function __destruct()
    {
        $args = $this->getArgs() ?? $this->extractArgumentFromProperties();

        $args['names'] = $this->getNames();

        $translater = new Translater($args, 'post-types');
        $args = $translater->translate([
            'label',
            'labels.*',
            'names.singular',
            'names.plural',
        ]);

        $names = $args['names'];

        // Unset names from item
        unset($args['names']);

        register_extended_post_type($this->slug, $args, $names);
    }
}
