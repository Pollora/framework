<?php

declare(strict_types=1);

namespace Pollen\Support;

/**
 * The ArgumentHelper class is a trait that provides methods to extract arguments from properties using getter methods.
 */
trait ExtendedCpt
{
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
    public $description;

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
    public $public;

    /**
     * Whether queries can be performed on the front end for the post type as part of `parse_request()` for post types or whether a taxonomy is intended for use publicly either via the admin interface or by front-end users
     *
     * @var bool
     */
    public $publiclyQueryable;

    /**
     * Rewrites information for this post type or taxonomy.
     *
     * @since 4.7.0
     *
     * @var array|false
     */
    public $rewrite;

    /**
     * Whether to generate and allow a UI for managing this post type or taxonomy in the admin.
     *
     * Default is the value of $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showUi;

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
    public $showInMenu;

    /**
     * Makes this post type available for selection in navigation menus.
     *
     * Default is the value $public.
     *
     * @since 4.6.0
     *
     * @var bool
     */
    public $showInNavMenus;

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
     * @var \WP_REST_Controller
     */
    public $restController;

    /**
     * Whether to show this post type or taxonomy on the 'At a Glance' section of the admin dashboard.
     *
     * Default false.
     */
    public $dashboardGlance;

    /**
     * Associative array of admin screen columns to show for this post type or taxonomy.
     *
     * @var array<string,mixed>
     */
    public $adminCols;

    /**
     * Names of the post type or taxonomy.
     *
     * @var array
     */
    public $names;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabels(): ?stdClass
    {
        return $this->labels;
    }

    public function setLabels(stdClass $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->public;
    }

    public function public(): self
    {
        $this->public = true;

        return $this;
    }

    public function private(): self
    {
        $this->public = false;

        return $this;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function getPubliclyQueryable(): ?bool
    {
        return $this->publiclyQueryable;
    }

    public function publiclyQueryable(): self
    {
        $this->publiclyQueryable = true;

        return $this;
    }

    public function setPubliclyQueryable(?bool $publiclyQueryable): self
    {
        $this->publiclyQueryable = $publiclyQueryable;

        return $this;
    }

    public function getShowUi(): ?bool
    {
        return $this->showUi;
    }

    public function showUi(): self
    {
        $this->showUi = true;

        return $this;
    }

    public function setShowUi(?bool $showUi): self
    {
        $this->showUi = $showUi;

        return $this;
    }

    public function isShowInMenu(): bool|string|null
    {
        return $this->showInMenu;
    }

    public function showInMenu()
    {
        $this->showInMenu = true;

        return $this;
    }

    public function setShowInMenu(bool|string $showInMenu): self
    {
        $this->showInMenu = $showInMenu;

        return $this;
    }

    public function getShowInNavMenus(): ?bool
    {
        return $this->showInNavMenus;
    }

    public function setShowInNavMenus(?bool $showInNavMenus): self
    {
        $this->showInNavMenus = $showInNavMenus;

        return $this;
    }

    public function getQueryVar(): bool|string|null
    {
        return $this->queryVar;
    }

    public function setQueryVar(bool|string $queryVar): self
    {
        $this->queryVar = $queryVar;

        return $this;
    }

    public function getRewrite(): bool|array|null
    {
        return $this->rewrite;
    }

    public function setRewrite(bool|array $rewrite): self
    {
        $this->rewrite = $rewrite;

        return $this;
    }

    public function getShowInRest(): bool|null
    {
        return $this->showInRest;
    }

    public function showInRest(): self
    {
        $this->showInRest = true;

        return $this;
    }

    public function setShowInRest(bool $showInRest): self
    {
        $this->showInRest = $showInRest;

        return $this;
    }

    public function getRestBase(): bool|string|null
    {
        return $this->restBase;
    }

    public function setRestBase(bool|string $restBase): self
    {
        $this->restBase = $restBase;

        return $this;
    }

    public function getRestNamespace(): bool|string|null
    {
        return $this->restNamespace;
    }

    public function setRestNamespace(bool|string $restNamespace): self
    {
        $this->restNamespace = $restNamespace;

        return $this;
    }

    public function getRestControllerClass(): bool|string|null
    {
        return $this->restControllerClass;
    }

    public function setRestControllerClass(bool|string $restControllerClass): self
    {
        $this->restControllerClass = $restControllerClass;

        return $this;
    }

    public function getRestController(): \WP_REST_Controller|null
    {
        return $this->restController;
    }

    public function setRestController(\WP_REST_Controller $restController): self
    {
        $this->restController = $restController;

        return $this;
    }

    public function setSingular(string|null $singular): self
    {
        $this->names['singular'] = $singular;

        return $this;
    }

    public function setPlural(string|null $plural): self
    {
        $this->names['plural'] = $plural;

        return $this;
    }

    public function setSlug(string|null $slug): self
    {
        $this->names['slug'] = $slug;

        return $this;
    }

    public function setNames(array $names): self
    {
        $this->names = $names;

        return $this;
    }

    public function getNames(): array
    {
        return $this->names ?? [];
    }

    public function isDashboardGlance(): bool|null
    {
        return $this->dashboardGlance;
    }

    public function enableDashboardGlance(): self
    {
        $this->dashboardGlance = true;

        return $this;
    }

    public function setDashboardGlance(bool $dashboardGlance): self
    {
        $this->dashboardGlance = $dashboardGlance;

        return $this;
    }

    public function getAdminCols(): array|null
    {
        return $this->adminCols;
    }

    public function setAdminCols(array $adminCols): self
    {
        $this->adminCols = $adminCols;

        return $this;
    }
}
