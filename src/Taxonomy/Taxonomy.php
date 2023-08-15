<?php

declare(strict_types=1);

namespace Pollen\Taxonomy;

use Pollen\Services\Translater;
use Pollen\Support\ExtendedCpt;
use Pollen\Support\WordPressArgumentHelper;

class Taxonomy
{
    use WordPressArgumentHelper;
    use ExtendedCpt;

    /**
     * Whether to list the taxonomy in the tag cloud widget controls.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $showTagcloud = true;

    /**
     * Whether to show the taxonomy in the quick/bulk edit panel.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $showInQuickEdit = true;

    /**
     * Whether to display a column for the taxonomy on its post type listing screens.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $showAdminColumn = false;

    /**
     * The callback function for the meta box display.
     *
     * @since 4.7.0
     *
     * @var bool|callable
     */
    public $metaBoxCb = null;

    /**
     * The callback function for sanitizing taxonomy data saved from a meta box.
     *
     * @since 5.1.0
     *
     * @var callable
     */
    public $metaBoxSanitizeCb = null;

    /**
     * Capabilities for this taxonomy.
     *
     * @since 4.7.0
     *
     * @var stdClass
     */
    public $cap;

    /**
     * Rewrites information for this taxonomy.
     *
     * @since 4.7.0
     *
     * @var array|false
     */
    public $rewrite;

    /**
     * Query var string for this taxonomy.
     *
     * @since 4.7.0
     *
     * @var string|false
     */
    public $queryVar;

    /**
     * Function that will be called when the count is updated.
     *
     * @since 4.7.0
     *
     * @var callable
     */
    public $updateCountCallback;

    /**
     * The default term name for this taxonomy. If you pass an array you have
     * to set 'name' and optionally 'slug' and 'description'.
     *
     * @since 5.5.0
     *
     * @var array|string
     */
    public $defaultTerm;

    /**
     * Whether terms in this taxonomy should be sorted in the order they are provided to `wp_set_object_terms()`.
     *
     * Use this in combination with `'orderby' => 'term_order'` when fetching terms.
     *
     * @since 2.5.0
     *
     * @var bool|null
     */
    public $sort = null;

    /**
     * Array of arguments to automatically use inside `wp_get_object_terms()` for this taxonomy.
     *
     * @since 2.6.0
     *
     * @var array|null
     */
    public $args = null;

    /**
     * Whether it is a built-in taxonomy.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $_builtin;

    /**
     * This allows you to override WordPress' default behaviour if necessary.
     *
     * Default false if you're using a custom meta box (see the `$meta_box` argument), default true otherwise.
     */
    public bool $checkedOntop;

    /**
     * Whether to show this taxonomy on the 'At a Glance' section of the admin dashboard.
     *
     * Default false.
     */
    public bool $dashboardGlance;

    /**
     * This parameter isn't feature complete. All it does currently is set the meta box
     * to the 'radio' meta box, thus meaning any given post can only have one term
     * associated with it for that taxonomy.
     *
     * 'exclusive' isn't really the right name for this, as terms aren't exclusive to a
     * post, but rather each post can exclusively have only one term. It's not feature
     * complete because you can edit a post in Quick Edit and give it more than one term
     * from the taxonomy.
     */
    public bool $exclusive;

    /**
     * All this does currently is disable hierarchy in the taxonomy's rewrite rules.
     *
     * Default false.
     */
    public bool $allowHierarchy;

    public function isShowTagcloud(): bool
    {
        return $this->showTagcloud;
    }

    public function setShowTagcloud(bool $showTagcloud): Taxonomy
    {
        $this->showTagcloud = $showTagcloud;

        return $this;
    }

    public function isShowInQuickEdit(): bool
    {
        return $this->showInQuickEdit;
    }

    public function setShowInQuickEdit(bool $showInQuickEdit): Taxonomy
    {
        $this->showInQuickEdit = $showInQuickEdit;

        return $this;
    }

    public function isShowAdminColumn(): bool
    {
        return $this->showAdminColumn;
    }

    public function setShowAdminColumn(bool $showAdminColumn): Taxonomy
    {
        $this->showAdminColumn = $showAdminColumn;

        return $this;
    }

    public function getMetaBoxCb(): callable|bool|null
    {
        return $this->metaBoxCb;
    }

    public function setMetaBoxCb(callable|bool|null $metaBoxCb): Taxonomy
    {
        $this->metaBoxCb = $metaBoxCb;

        return $this;
    }

    public function getMetaBoxSanitizeCb(): ?callable
    {
        return $this->metaBoxSanitizeCb;
    }

    public function setMetaBoxSanitizeCb(?callable $metaBoxSanitizeCb): Taxonomy
    {
        $this->metaBoxSanitizeCb = $metaBoxSanitizeCb;

        return $this;
    }

    public function getCap(): stdClass
    {
        return $this->cap;
    }

    public function setCap(stdClass $cap): Taxonomy
    {
        $this->cap = $cap;

        return $this;
    }

    public function getRewrite(): bool|array
    {
        return $this->rewrite;
    }

    public function setRewrite(bool|array $rewrite): Taxonomy
    {
        $this->rewrite = $rewrite;

        return $this;
    }

    public function getQueryVar(): bool|string
    {
        return $this->queryVar;
    }

    public function setQueryVar(bool|string $queryVar): Taxonomy
    {
        $this->queryVar = $queryVar;

        return $this;
    }

    public function getUpdateCountCallback(): callable
    {
        return $this->updateCountCallback;
    }

    public function setUpdateCountCallback(callable $updateCountCallback): Taxonomy
    {
        $this->updateCountCallback = $updateCountCallback;

        return $this;
    }

    public function getDefaultTerm(): array|string
    {
        return $this->defaultTerm;
    }

    public function setDefaultTerm(array|string $defaultTerm): Taxonomy
    {
        $this->defaultTerm = $defaultTerm;

        return $this;
    }

    public function getSort(): ?bool
    {
        return $this->sort;
    }

    public function setSort(?bool $sort): Taxonomy
    {
        $this->sort = $sort;

        return $this;
    }

    public function getArgs(): ?array
    {
        return $this->args;
    }

    public function setArgs(?array $args): Taxonomy
    {
        $this->args = $args;

        return $this;
    }

    public function isBuiltin(): bool
    {
        return $this->_builtin;
    }

    public function setBuiltin(bool $builtin): Taxonomy
    {
        $this->_builtin = $builtin;

        return $this;
    }

    public function isCheckedOntop(): bool
    {
        return $this->checkedOntop;
    }

    public function setCheckedOntop(bool $checkedOntop): Taxonomy
    {
        $this->checkedOntop = $checkedOntop;

        return $this;
    }

    public function getDashboardGlance(): bool
    {
        return $this->dashboardGlance;
    }

    public function setDashboardGlance(bool $dashboardGlance): Taxonomy
    {
        $this->dashboardGlance = $dashboardGlance;

        return $this;
    }

    public function getExclusive(): bool
    {
        return $this->exclusive;
    }

    public function setExclusive(bool $exclusive): Taxonomy
    {
        $this->exclusive = $exclusive;

        return $this;
    }

    public function getAllowHierarchy(): bool
    {
        return $this->allowHierarchy;
    }

    public function setAllowHierarchy(bool $allowHierarchy): Taxonomy
    {
        $this->allowHierarchy = $allowHierarchy;

        return $this;
    }

    public function __construct(
        public string $slug,
        public string|array $objectType,
        string $singular = null,
        string $plural = null
    ) {
        $this->setSingular($singular);
        $this->setPlural($plural);
    }

    public function __destruct()
    {
        $args = $this->getRawArgs() ?? $this->extractArgumentFromProperties();

        $args['names'] = $this->getNames();

        $translater = new Translater($args, 'taxonomies');
        $args = $translater->translate([
            'label',
            'labels.*',
            'names.singular',
            'names.plural',
        ]);

        $names = $args['names'];

        // Unset names from item
        unset($args['names']);

        // Unset links from item
        unset($args['links']);

        register_extended_taxonomy($this->slug, $this->objectType, $args, $names);
    }
}
