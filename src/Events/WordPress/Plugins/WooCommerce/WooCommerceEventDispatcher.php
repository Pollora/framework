<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\WooCommerce;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WC_Order;

/**
 * Event dispatcher for WooCommerce-related events.
 *
 * This class handles the dispatching of Laravel events for WooCommerce actions
 * such as order status changes, product updates, and settings modifications.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class WooCommerceEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'woocommerce_order_status_changed',
        'woocommerce_attribute_added',
        'woocommerce_attribute_updated',
        'woocommerce_attribute_deleted',
        'woocommerce_tax_rate_added',
        'woocommerce_tax_rate_updated',
        'woocommerce_tax_rate_deleted',
        'updated_option',
    ];

    /**
     * Handle order status changes.
     *
     * @param  int  $orderId  Order ID
     * @param  string  $oldStatus  Old order status
     * @param  string  $newStatus  New order status
     */
    public function handleWoocommerceOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return;
        }

        $this->dispatch(OrderStatusChanged::class, [$order, $oldStatus, $newStatus]);
    }

    /**
     * Handle product attribute creation.
     *
     * @param  int  $attributeId  Attribute ID
     * @param  array<string, mixed>  $attribute  Attribute data
     */
    public function handleWoocommerceAttributeAdded(int $attributeId, array $attribute): void
    {
        $this->dispatch(AttributeCreated::class, [$attributeId, $attribute]);
    }

    /**
     * Handle product attribute update.
     *
     * @param  int  $attributeId  Attribute ID
     * @param  array<string, mixed>  $attribute  Attribute data
     */
    public function handleWoocommerceAttributeUpdated(int $attributeId, array $attribute): void
    {
        $this->dispatch(AttributeUpdated::class, [$attributeId, $attribute]);
    }

    /**
     * Handle product attribute deletion.
     *
     * @param  int  $attributeId  Attribute ID
     * @param  string  $attributeName  Attribute name
     */
    public function handleWoocommerceAttributeDeleted(int $attributeId, string $attributeName): void
    {
        $this->dispatch(AttributeDeleted::class, [$attributeId, $attributeName]);
    }

    /**
     * Handle tax rate creation.
     *
     * @param  int  $taxRateId  Tax rate ID
     * @param  array<string, mixed>  $taxRate  Tax rate data
     */
    public function handleWoocommerceTaxRateAdded(int $taxRateId, array $taxRate): void
    {
        $this->dispatch(TaxRateCreated::class, [$taxRateId, $taxRate]);
    }

    /**
     * Handle tax rate update.
     *
     * @param  int  $taxRateId  Tax rate ID
     * @param  array<string, mixed>  $taxRate  Tax rate data
     */
    public function handleWoocommerceTaxRateUpdated(int $taxRateId, array $taxRate): void
    {
        $this->dispatch(TaxRateUpdated::class, [$taxRateId, $taxRate]);
    }

    /**
     * Handle tax rate deletion.
     *
     * @param  int  $taxRateId  Tax rate ID
     */
    public function handleWoocommerceTaxRateDeleted(int $taxRateId): void
    {
        $this->dispatch(TaxRateDeleted::class, [$taxRateId]);
    }

    /**
     * Handle WooCommerce option updates.
     *
     * @param  string  $optionName  Option name
     * @param  mixed  $oldValue  Old option value
     * @param  mixed  $newValue  New option value
     */
    public function handleUpdatedOption(string $optionName, mixed $oldValue, mixed $newValue): void
    {
        if (! str_starts_with($optionName, 'woocommerce_')) {
            return;
        }

        $this->dispatch(SettingUpdated::class, [$optionName, $oldValue, $newValue]);
    }
}
