<?php
/**
 * Plugin Name:       Propono - Checkout Order Bump for WooCommerce
 * Plugin URI:        https://plogins.com/propono/
 * Description:        Show a one-click order-bump offer on the checkout so shoppers can add a complementary product before they pay.
 * Version:           1.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       propono
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to: 11.0
 *
 * @package Bump
 */

declare(strict_types=1);

namespace Bump;

defined('ABSPATH') || exit;

const VERSION     = '1.1.1';
const PLUGIN_FILE = __FILE__;

define('BUMP_DIR', plugin_dir_path(__FILE__));
define('BUMP_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Propono requires WooCommerce to be active.', 'propono');
            echo '</p></div>';
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);
