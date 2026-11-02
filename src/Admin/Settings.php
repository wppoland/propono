<?php

declare(strict_types=1);

namespace Bump\Admin;

use Bump\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Admin settings page under WooCommerce > Order Bump.
 *
 * Stores everything in the `bump_settings` option (array). All output escaped,
 * all input sanitised on save.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'bump_settings';
    private const PAGE   = 'propono';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('plugin_action_links_' . plugin_basename(\Bump\PLUGIN_FILE), [$this, 'actionLinks']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Propono: order bump', 'propono'),
            __('Order Bump', 'propono'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    /**
     * @param array<int|string, string> $links
     * @return array<int|string, string>
     */
    public function actionLinks(array $links): array
    {
        $link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::PAGE)),
            esc_html__('Settings', 'propono'),
        );
        array_unshift($links, $link);

        return $links;
    }

    public function registerSettings(): void
    {
        register_setting(self::PAGE, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);

        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $s = $this->settings();

        // The checkout quietly drops a bump product that is not purchasable or is
        // out of stock: bumpProduct() returns null, nothing renders, no styles load.
        // The merchant saw their product still selected on this screen while the
        // shopper saw no offer at all. So the list now only offers what the checkout
        // will accept, and a saved choice that has since fallen out of that set is
        // kept but flagged instead of looking fine.
        $choices  = $this->productChoices();
        $selected = (int) $s['product_id'];
        $stale    = $selected > 0 && ! isset($choices[$selected]);

        if ($stale) {
            $choices[$selected] = $this->staleChoiceLabel($selected);
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Show a one-click order-bump offer above the payment methods on the checkout. Ticking it adds the product to the order and recalculates the total before payment.', 'propono'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable order bump', 'propono'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked(! empty($s['enabled'])); ?> />
                                    <?php esc_html_e('Show the order-bump offer on the checkout.', 'propono'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_product"><?php esc_html_e('Bump product', 'propono'); ?></label></th>
                            <td>
                                <select id="bump_product" name="<?php echo esc_attr(self::OPTION); ?>[product_id]">
                                    <option value="0"><?php esc_html_e('- Select a product -', 'propono'); ?></option>
                                    <?php foreach ($choices as $id => $label) : ?>
                                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($selected, $id); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('A simple, purchasable product offered as the bump. Pick something cheap and complementary. Products with no price, and products out of stock, are not listed because the checkout will not show them.', 'propono'); ?></p>
                                <?php if ($stale) : ?>
                                    <p class="notice notice-warning inline"><?php esc_html_e('The saved product has no price or is out of stock, so the offer stays hidden on the checkout. Give it a price and stock, or pick another product.', 'propono'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_heading"><?php esc_html_e('Heading', 'propono'); ?></label></th>
                            <td><input type="text" id="bump_heading" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[heading]" value="<?php echo esc_attr((string) $s['heading']); ?>" placeholder="<?php esc_attr_e('One-time offer', 'propono'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_checkbox_label"><?php esc_html_e('Checkbox label', 'propono'); ?></label></th>
                            <td><input type="text" id="bump_checkbox_label" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[checkbox_label]" value="<?php echo esc_attr((string) $s['checkbox_label']); ?>" placeholder="<?php esc_attr_e('Yes, add this to my order!', 'propono'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_description"><?php esc_html_e('Description', 'propono'); ?></label></th>
                            <td><textarea id="bump_description" class="large-text" rows="2" name="<?php echo esc_attr(self::OPTION); ?>[description]"><?php echo esc_textarea((string) $s['description']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_price"><?php esc_html_e('Override price', 'propono'); ?></label></th>
                            <td>
                                <input type="text" id="bump_price" class="small-text" name="<?php echo esc_attr(self::OPTION); ?>[bump_price]" value="<?php echo esc_attr((string) $s['bump_price']); ?>" inputmode="decimal" />
                                <p class="description"><?php esc_html_e('Optional special price for the bump line only (e.g. 19.00). Leave empty to use the product\'s normal price.', 'propono'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bump_accent"><?php esc_html_e('Accent colour', 'propono'); ?></label></th>
                            <td><input type="text" id="bump_accent" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[accent_color]" value="<?php echo esc_attr((string) $s['accent_color']); ?>" placeholder="#d97706" pattern="#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})" /></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php $this->renderCompanionCard(); ?>
        </div>
        <?php
    }

    /**
     * Purchasable simple products, id => "Name (price)".
     *
     * @return array<int, string>
     */
    private function productChoices(): array
    {
        $choices  = [];
        $products = wc_get_products([
            'status'  => 'publish',
            'type'    => 'simple',
            'limit'   => 200,
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);

        foreach ($products as $product) {
            if (! $product instanceof \WC_Product) {
                continue;
            }
            // Same test BumpService::bumpProduct() applies at checkout.
            if (! $product->is_purchasable() || ! $product->is_in_stock()) {
                continue;
            }
            $choices[$product->get_id()] = sprintf('%s (%s)', $product->get_name(), wp_strip_all_tags($product->get_price_html()));
        }

        return $choices;
    }

    /**
     * Label for a saved product the checkout will no longer offer. Kept in the
     * select so saving the page does not silently reset the merchant's choice.
     */
    private function staleChoiceLabel(int $productId): string
    {
        $product = wc_get_product($productId);

        $name = $product instanceof \WC_Product
            ? $product->get_name()
            /* translators: %d: product id. */
            : sprintf(__('Product #%d', 'propono'), $productId);

        /* translators: %s: product name. */
        return sprintf(__('%s (not available on the checkout)', 'propono'), $name);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->settings();

        $price = isset($raw['bump_price']) ? trim((string) $raw['bump_price']) : '';
        $price = ('' !== $price && is_numeric(str_replace(',', '.', $price)))
            ? number_format((float) str_replace(',', '.', $price), wc_get_price_decimals(), '.', '')
            : '';

        // The field's pattern makes the leading hash optional, so the browser
        // happily submits "d97706". sanitize_hex_color() insists on the hash and
        // returned null for it, the accent saved empty, and the merchant got a
        // blank field back while the shopper kept seeing the packaged orange.
        // Put the hash back before sanitising.
        $accent = isset($raw['accent_color']) ? trim((string) $raw['accent_color']) : '';

        if ('' !== $accent && ! str_starts_with($accent, '#')) {
            $accent = '#' . $accent;
        }

        $accent = sanitize_hex_color($accent);

        return array_merge($defaults, [
            'enabled'        => ! empty($raw['enabled']),
            'product_id'     => isset($raw['product_id']) ? absint($raw['product_id']) : 0,
            'heading'        => isset($raw['heading']) ? sanitize_text_field((string) $raw['heading']) : '',
            'description'    => isset($raw['description']) ? sanitize_textarea_field((string) $raw['description']) : '',
            'checkbox_label' => isset($raw['checkbox_label']) ? sanitize_text_field((string) $raw['checkbox_label']) : '',
            'bump_price'     => $price,
            'accent_color'   => is_string($accent) ? $accent : '',
        ]);
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
     * Cross-recommendation card for sister plugins (Swift Buy Now).
     * Strictly scoped to the Propono settings screen per WordPress.org Guideline 11.
     */
    private function renderCompanionCard(): void
    {
        $isSwiftActive = defined('Swift\\VERSION') || (function_exists('is_plugin_active') && (is_plugin_active('swift/plogins-swift.php') || is_plugin_active('plogins-swift/plogins-swift.php')));

        ?>
        <div class="card" style="max-width: 800px; margin-top: 24px; padding: 18px 22px;">
            <h2 class="title" style="margin-top: 0; font-size: 15px;"><?php esc_html_e('Complementary integration: Swift - Buy Now Button for WooCommerce', 'propono'); ?></h2>
            <p style="color: #50575e; line-height: 1.5;">
                <?php esc_html_e('Swift sends shoppers straight from product pages or loops to checkout, skipping the cart. Combine it with Propono order bumps to convert instant buyers into higher-value orders.', 'propono'); ?>
            </p>
            <?php if ($isSwiftActive) : ?>
                <p style="color: #007017; font-weight: 600; margin-bottom: 0;">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Swift - Buy Now Button is active.', 'propono'); ?>
                </p>
            <?php else : ?>
                <p style="margin-bottom: 0;">
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=' . rawurlencode('Swift - Buy Now Button for WooCommerce') . '&tab=search&type=term')); ?>" class="button button-secondary">
                        <?php esc_html_e('Explore Swift Buy Now', 'propono'); ?> &rarr;
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
