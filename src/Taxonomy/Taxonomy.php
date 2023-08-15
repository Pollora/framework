<?php

declare(strict_types=1);

namespace Pollen\Taxonomy;

use Pollen\Support\ExtendedCpt;
use Pollen\Support\Facades\Action;
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
    public $showTagcloud;

    /**
     * Whether to show the taxonomy in the quick/bulk edit panel.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $showInQuickEdit;

    /**
     * Whether to display a column for the taxonomy on its post type listing screens.
     *
     * @since 4.7.0
     *
     * @var bool
     */
    public $showAdminColumn;

    /**
     * The callback function for the meta box display.
     *
     * @since 4.7.0
     *
     * @var bool|callable
     */
    public $metaBoxCb;

    /**
     * The callback function for sanitizing taxonomy data saved from a meta box.
     *
     * @since 5.1.0
     *
     * @var callable
     */
    public $metaBoxSanitizeCb;

    /**
     * Capabilities for this taxonomy.
     *
     * @since 4.7.0
     *
     * @var stdClass
     */
    public $cap;

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
    public $sort;

    /**
     * Array of arguments to automatically use inside `wp_get_object_terms()` for this taxonomy.
     *
     * @since 2.6.0
     *
     * @var array|null
     */
    public $args;

    /**
     * This allows you to override WordPress' default behaviour if necessary.
     *
     * Default false if you're using a custom meta box (see the `$meta_box` argument), default true otherwise.
     */
    public $checkedOntop;

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
    public $exclusive;

    /**
     * All this does currently is disable hierarchy in the taxonomy's rewrite rules.
     *
     * Default false.
     */
    public $allowHierarchy;

    public function isShowTagcloud(): ?bool
    {
        return $this->showTagcloud;
    }

    public function showTagcloud(): Taxonomy
    {
        $this->showTagcloud = true;

        return $this;
    }

    public function setShowTagcloud(bool $showTagcloud): Taxonomy
    {
        $this->showTagcloud = $showTagcloud;

        return $this;
    }

    public function isShowInQuickEdit(): ?bool
    {
        return $this->showInQuickEdit;
    }

    public function showInQuickEdit(): Taxonomy
    {
        $this->showInQuickEdit = true;

        return $this;
    }

    public function setShowInQuickEdit(bool $showInQuickEdit): Taxonomy
    {
        $this->showInQuickEdit = $showInQuickEdit;

        return $this;
    }

    public function isShowAdminColumn(): ?bool
    {
        return $this->showAdminColumn;
    }

    public function showAdminColumn(): Taxonomy
    {
        $this->showAdminColumn = true;

        return $this;
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

    public function getCap(): ?stdClass
    {
        return $this->cap;
    }

    public function setCap(stdClass $cap): Taxonomy
    {
        $this->cap = $cap;

        return $this;
    }

    public function getUpdateCountCallback(): ?callable
    {
        return $this->updateCountCallback;
    }

    public function setUpdateCountCallback(callable $updateCountCallback): Taxonomy
    {
        $this->updateCountCallback = $updateCountCallback;

        return $this;
    }

    public function getDefaultTerm(): array|string|null
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

    public function sort(): Taxonomy
    {
        $this->sort = true;

        return $this;
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

    public function isCheckedOntop(): ?bool
    {
        return $this->checkedOntop;
    }

    public function checkedOntop(): Taxonomy
    {
        $this->checkedOntop = true;

        return $this;
    }

    public function setCheckedOntop(bool $checkedOntop): Taxonomy
    {
        $this->checkedOntop = $checkedOntop;

        return $this;
    }

    public function isExclusive(): ?bool
    {
        return $this->exclusive;
    }

    public function exclusive(): Taxonomy
    {
        $this->exclusive = true;

        return $this;
    }

    public function setExclusive(bool $exclusive): Taxonomy
    {
        $this->exclusive = $exclusive;

        return $this;
    }

    public function isAllowHierarchy(): ?bool
    {
        return $this->allowHierarchy;
    }

    public function allowHierarchy(): Taxonomy
    {
        $this->allowHierarchy = true;

        return $this;
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
        $this->register();
    }

    public function register()
    {
        Action::add('init', function () {

            $args = $this->buildArguments();
            $args = $this->translateArguments($args, 'taxonomies');

            $names = $args['names'];

            // Unset names from item
            unset($args['names']);

            // Unset links from item
            unset($args['links']);

            register_extended_taxonomy($this->slug, $this->objectType, $args, $names);
        }, 99);
    }
}
