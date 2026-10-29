<?php

declare(strict_types=1);

namespace Bump\Service;

use Bump\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Renders the order-bump offer on the classic checkout and toggles the bump
 * product in and out of the cart over AJAX.
 *
 * Flow: the offer (a checkbox with the product's name and price) renders above
 * the payment methods. Ticking it POSTs to `bump_toggle`, which adds the product
 * to the cart tagged with a private `_plogins_bump` flag (so it is a distinct
 * line, never confused with a manually-added copy). The script then triggers
 * WooCommerce's own `update_checkout`, which recalculates every total, so the
 * amount handed to the gateway (Tpay/BLIK) already includes the bump before
 * payment starts. Unticking removes exactly that flagged line.
 *
 * ponytail: leans on WooCommerce's native cart + update_checkout recalculation
 * rather than reimplementing totals. Classic (shortcode) checkout only.
 */
final class BumpService implements HasHooks
{
    private const OPTION      = 'bump_settings';
    private const HANDLE      = 'propono';
    private const FLAG        = '_plogins_bump';
    private const NONCE       = 'plogins_bump_toggle';
    private const AJAX_ACTION = 'bump_toggle';

    public function registerHooks(): void
    {
        // The AJAX endpoint must exist even when the offer is not currently
        // renderable, so an in-flight toggle can still resolve.
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajaxToggle']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'ajaxToggle']);

        // Gate on `enabled` only. The bump product is resolved lazily inside each
        // callback, never here: registerHooks() runs at init priority 0, before
        // WooCommerce registers the `product` post type (init ~5), so a product
        // lookup at this point would always fail.
        if (empty($this->settings()['enabled'])) {
            return;
        }

        add_action('woocommerce_review_order_before_payment', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyBumpPrice'], 20);
    }

    private function bumpProduct(): ?\WC_Product
    {
        $id = (int) ($this->settings()['product_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        $product = wc_get_product($id);

        if (! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
            return null;
        }

        // Variable products need a chosen variation, out of scope for the bump.
        if ($product->is_type('variable')) {
            return null;
        }

        return $product;
    }

    /**
     * Render the offer box above the payment methods. Renders nothing when the
     * bump product is already the only purchasable item (so we never offer a
     * product that IS the cart).
     */
    public function render(): void
    {
        $product = $this->bumpProduct();

        if (! $product instanceof \WC_Product || ! function_exists('WC') || null === WC()->cart) {
            return;
        }

        // Do not offer a product the shopper is already buying as a normal line.
        if ($this->cartHasProductWithoutFlag($product->get_id())) {
            return;
        }

        $settings = $this->settings();

        $context = [
            'product'        => $product,
            'in_cart'        => null !== $this->findBumpCartKey(),
            'heading'        => $this->text($settings['heading'] ?? '', __('One-time offer', 'propono')),
            'description'    => $this->text($settings['description'] ?? '', ''),
            'checkbox_label' => $this->text($settings['checkbox_label'] ?? '', __('Yes, add this to my order!', 'propono')),
            'price_html'     => $this->priceHtml($product, $settings),
            'accent_color'   => (string) ($settings['accent_color'] ?? ''),
            'nonce'          => wp_create_nonce(self::NONCE),
        ];

        $this->renderTemplate('bump', $context);
    }

    public function enqueueAssets(): void
    {
        if (! is_checkout() || is_admin()) {
            return;
        }

        // Nothing to offer if the bump product is missing/unpurchasable.
        if (! $this->bumpProduct() instanceof \WC_Product) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE,
            \Bump\Plugin::instance()->url('assets/css/bump.css'),
            [],
            \Bump\VERSION,
        );

        wp_enqueue_script(
            self::HANDLE,
            \Bump\Plugin::instance()->url('assets/js/bump.js'),
            ['jquery'],
            \Bump\VERSION,
            true,
        );

        wp_localize_script(self::HANDLE, 'ploginsBump', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => self::AJAX_ACTION,
            'nonce'   => wp_create_nonce(self::NONCE),
        ]);
    }

    /**
     * Add or remove the flagged bump line, then let the caller trigger
     * update_checkout to recalculate totals.
     */
    public function ajaxToggle(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');

        if (! function_exists('WC') || null === WC()->cart) {
            wp_send_json_error(['message' => 'no-cart'], 400);
        }

        $product = $this->bumpProduct();

        if (! $product instanceof \WC_Product) {
            wp_send_json_error(['message' => 'no-product'], 400);
        }

        $add = isset($_POST['add']) && '1' === sanitize_text_field(wp_unslash((string) $_POST['add']));

        $existing = $this->findBumpCartKey();

        if ($add) {
            if (null === $existing) {
                WC()->cart->add_to_cart(
                    $product->get_id(),
                    1,
                    0,
                    [],
                    [self::FLAG => true],
                );
            }
        } elseif (null !== $existing) {
            WC()->cart->remove_cart_item($existing);
        }

        wp_send_json_success(['in_cart' => null !== $this->findBumpCartKey()]);
    }

    /**
     * Apply the optional price override to the bump line before totals are
     * calculated.
     */
    public function applyBumpPrice(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        $override = (string) ($this->settings()['bump_price'] ?? '');

        if ('' === $override || ! is_numeric($override)) {
            return;
        }

        // The special price is for one unit added to an order, so it never
        // applies to a raised quantity or to a cart holding only the bump line.
        $hasOtherLine = false;
        foreach ($cart->get_cart() as $item) {
            if (empty($item[self::FLAG])) {
                $hasOtherLine = true;
                break;
            }
        }

        foreach ($cart->get_cart() as $key => $item) {
            if (! empty($item[self::FLAG]) && isset($item['data']) && $item['data'] instanceof \WC_Product) {
                if ((int) ($item['quantity'] ?? 1) !== 1) {
                    $cart->set_quantity((string) $key, 1, false);
                }
                if (! $hasOtherLine) {
                    continue;
                }
                // WC_Product::set_price() is typed for a string; it stores the
                // value as given, so hand it the numeric string we validated.
                $item['data']->set_price($override);
            }
        }
    }

    private function findBumpCartKey(): ?string
    {
        if (! function_exists('WC') || null === WC()->cart) {
            return null;
        }

        foreach (WC()->cart->get_cart() as $key => $item) {
            if (! empty($item[self::FLAG])) {
                return (string) $key;
            }
        }

        return null;
    }

    private function cartHasProductWithoutFlag(int $productId): bool
    {
        if (! function_exists('WC') || null === WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item[self::FLAG]) && (int) ($item['product_id'] ?? 0) === $productId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function priceHtml(\WC_Product $product, array $settings): string
    {
        $override = (string) ($settings['bump_price'] ?? '');

        if ('' !== $override && is_numeric($override)) {
            return wc_price((float) $override);
        }

        return $product->get_price_html();
    }

    private function text(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return '' !== $value ? $value : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require BUMP_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): void
    {
        $file = BUMP_DIR . 'templates/' . $template . '.php';

        if (! is_readable($file)) {
            return;
        }

        extract($context, EXTR_SKIP);
        require $file;
    }
}
