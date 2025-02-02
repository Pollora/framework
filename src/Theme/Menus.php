<?php

declare(strict_types=1);

/**
 * Handles the registration and customization of WordPress navigation menus.
 *
 * This class provides functionality to:
 * - Register theme navigation menus
 * - Customize menu elements (links, items, submenus) through attributes
 * - Apply conditional styling based on menu depth and order
 */

namespace Pollora\Theme;

use /**
 * The Translater service class.
 */
Pollora\Services\Translater;
use /**
 * Class Action
 *
 *
 * @method static \Pollora\Support\Actions\Action make($action)
 * @method static bool has($action)
 * @method static \Pollora\Support\Actions\Action|array|null get($action = null, $default = null)
 * @method static \Pollora\Support\Actions\Action|array|null require ($action = null, $default = null)
 * @method static \Pollora\Support\Actions\Action|null remove($action = null)
 * @method static \Pollora\Support\Actions\Action clear()
 * @method static \Pollora\Support\Actions\Action register(string $name, callable|null $callback = null)
 * @method static \Pollora\Support\Actions\Action boot($action = null, array $params = [])
 * @method static \Pollora\Support\Actions\Action listen(string $event, callable $callback, int $priority = 0)
 * @method static \Pollora\Support\Actions\Action trigger(string $event, array $params = [], \Closure|null $before = null, \Closure|null $after = null)
 */
Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;
use Pollora\Theme\Contracts\ThemeComponent;

/**
 * Class Menus
 *
 * @implements ThemeComponent
 */
class Menus implements ThemeComponent
{
    /**
     * Defines the mapping between menu element types and their HTML representations.
     *
     * @var array<string, string>
     */
    protected const ELEMENT_TYPES = [
        'link' => 'a',
        'item' => 'li',
        'submenu' => 'submenu',
    ];

    /**
     * Default HTML attributes applied to menu elements.
     *
     * @var array<string, string>
     */
    protected array $defaultAttributes = [
        'class' => '',
    ];

    /**
     * Registers all necessary WordPress hooks for menu customization.
     */
    public function register(): void
    {
        Action::add('after_setup_theme', $this->registerMenus(...), 1);
        Filter::add('nav_menu_link_attributes', [$this, 'handleLinkAttributes'], 10, 4);
        Filter::add('nav_menu_item_attributes', [$this, 'handleItemAttributes'], 10, 4);
        Filter::add('nav_menu_submenu_attributes', [$this, 'handleSubmenuAttributes'], 10, 3);
    }

    /**
     * Registers navigation menus defined in the theme configuration.
     * Handles translation of menu labels using the Translater service.
     */
    public function registerMenus(): void
    {
        $menus = (array) config('theme.menus');
        $translater = new Translater($menus, 'menus');
        $menus = $translater->translate(['*']);

        register_nav_menus($menus);
    }

    /**
     * Customizes attributes for menu link elements.
     *
     * @param  array  $attributes  Current link attributes
     * @param  \WP_Post  $item  Menu item object
     * @param  object  $args  Menu arguments object
     * @param  int  $depth  Current menu item depth
     * @return array Modified attributes
     */
    public function handleLinkAttributes($attributes, $item, $args, $depth): array
    {
        return $this->processElementAttributes(
            self::ELEMENT_TYPES['link'],
            $attributes,
            $args,
            $depth,
            $item->menu_order
        );
    }

    /**
     * Customizes attributes for menu list item elements.
     *
     * @param  array  $attributes  Current item attributes
     * @param  \WP_Post  $item  Menu item object
     * @param  object  $args  Menu arguments object
     * @param  int  $depth  Current menu item depth
     * @return array Modified attributes
     */
    public function handleItemAttributes($attributes, $item, $args, $depth): array
    {
        return $this->processElementAttributes(
            self::ELEMENT_TYPES['item'],
            $attributes,
            $args,
            $depth,
            $item->menu_order
        );
    }

    /**
     * Customizes attributes for submenu container elements.
     *
     * @param  array  $attributes  Current submenu attributes
     * @param  object  $args  Menu arguments object
     * @param  int  $depth  Current menu depth
     * @return array Modified attributes
     */
    public function handleSubmenuAttributes($attributes, $args, $depth): array
    {
        return $this->processElementAttributes(
            self::ELEMENT_TYPES['submenu'],
            $attributes,
            $args,
            $depth
        );
    }

    /**
     * Processes and modifies attributes for any menu element based on configuration rules.
     *
     * @param  string  $elementType  Type of menu element (link, item, or submenu)
     * @param  array  $attributes  Current element attributes
     * @param  object  $args  Menu arguments object
     * @param  int  $depth  Current depth in menu hierarchy
     * @param  int  $order  Menu item order (-1 for submenus)
     * @return array Modified attributes
     */
    protected function processElementAttributes(
        string $elementType,
        array $attributes,
        object $args,
        int $depth,
        int $order = -1
    ): array {
        $attributes = array_merge($this->defaultAttributes, $attributes);

        // Get element configuration from args
        $config = $this->getElementConfig($elementType, $args);

        if ($config) {
            // Process each configuration rule
            foreach ($config as $rule) {
                if ($this->shouldApplyRule($rule, $depth, $order)) {
                    $attributes = $this->applyRule($attributes, $rule);
                }
            }
        }

        return $attributes;
    }

    /**
     * Retrieves element-specific configuration from menu arguments.
     *
     * @param  string  $elementType  Type of menu element
     * @param  object  $args  Menu arguments object
     * @return array|null Configuration array or null if not found
     */
    protected function getElementConfig(string $elementType, object $args): ?array
    {
        $elementTypes = array_flip(self::ELEMENT_TYPES);
        if (! isset($elementTypes[$elementType])) {
            return null;
        }

        $configKey = $elementTypes[$elementType].'_config';

        return property_exists($args, $configKey) ? (array) $args->{$configKey} : null;
    }

    /**
     * Determines if a configuration rule should be applied based on depth and order criteria.
     *
     * @param  array  $rule  Configuration rule to evaluate
     * @param  int  $depth  Current depth in menu hierarchy
     * @param  int  $order  Menu item order
     * @return bool True if rule should be applied
     */
    protected function shouldApplyRule(array $rule, int $depth, int $order): bool
    {
        // Check if depth matches
        if (! isset($rule['depth']) || $rule['depth'] !== $depth) {
            return false;
        }

        // If eq is specified, check if order matches
        if (isset($rule['eq']) && $order !== -1 && $rule['eq'] !== $order) {
            return false;
        }

        return true;
    }

    /**
     * Applies a configuration rule to modify element attributes.
     *
     * @param  array  $attributes  Current element attributes
     * @param  array  $rule  Configuration rule to apply
     * @return array Modified attributes
     */
    protected function applyRule(array $attributes, array $rule): array
    {
        // Handle classes
        if (isset($rule['class'])) {
            $newClasses = is_array($rule['class']) ? $rule['class'] : explode(' ', $rule['class']);
            $currentClasses = isset($attributes['class']) ? explode(' ', $attributes['class']) : [];
            $attributes['class'] = implode(' ', array_merge($currentClasses, $newClasses));
        }

        // Handle other attributes
        if (isset($rule['attrs'])) {
            $attributes = array_merge($attributes, $rule['attrs']);
        }

        return $attributes;
    }
}
