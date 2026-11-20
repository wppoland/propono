<?php
// existing-checked: tests/bump-price-check.php - it stubs every option as one settings array, these cases need the tax display options and a configured product id.
// Run: php tests/bump-product-binding-check.php
// Repro for propono BumpService. 
namespace {
define('ABSPATH', '/');
define('BUMP_DIR', dirname(__DIR__) . '/');
$GLOBALS['opt'] = ['bump_settings' => ['enabled' => true, 'product_id' => 7, 'bump_price' => '1.00'], 'woocommerce_tax_display_cart' => 'incl'];
function get_option($k, $d = false) { return $GLOBALS['opt'][$k] ?? $d; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wc_price($p) { return number_format((float) $p, 2); }
// Mirrors wc_get_price_to_display() (wc-product-functions.php:1354): incl display adds 23% VAT to a net price.
function wc_get_price_to_display($product, $args = []) {
    $price = $args['price'] ?? $product->get_price();
    $opt = ('cart' === ($args['display_context'] ?? 'shop')) ? 'woocommerce_tax_display_cart' : 'woocommerce_tax_display_shop';
    return 'incl' === get_option($opt) ? (float) $price * 1.23 : (float) $price;
}
class WC_Product { public $price; public function __construct(public int $id, $p) { $this->price = $p; }
    function set_price($p) { $this->price = $p; } function get_price() { return $this->price; } function get_id() { return $this->id; } }
$GLOBALS['wc'] = new stdClass();
function WC() { return $GLOBALS['wc']; }
class WC_Cart { public array $items; function get_cart() { return $this->items; } function set_quantity($k, $q, $r) {} }
require BUMP_DIR . 'src/Contract/HasHooks.php';
require BUMP_DIR . 'src/Service/BumpService.php';
$fail = 0;
$svc = new Bump\Service\BumpService();
// A line flagged by the previous bump product (5, price 40) left in a cart after the merchant switched the bump to product 7.
$old = new WC_Product(5, '40.00');
$cart = new WC_Cart();
$cart->items = ['a' => ['product_id' => 9, 'data' => new WC_Product(9, '10'), 'quantity' => 1],
                'b' => ['product_id' => 5, '_plogins_bump' => true, 'data' => $old, 'quantity' => 1]];
$svc->applyBumpPrice($cart);
echo "old bump product charged at {$old->price}\n"; $fail += '40.00' === $old->price ? 0 : 1;
// The offer box must not show the new product as already added while the cart holds the old one.
$GLOBALS['wc']->cart = $cart;
$key = (new ReflectionMethod($svc, 'findBumpCartKey'))->invoke($svc);
echo 'old line taken as the current bump: ', var_export(null !== $key, true), "\n"; $fail += null === $key ? 0 : 1;
$new = new WC_Product(7, '20.00');
$cart->items['b'] = ['product_id' => 7, '_plogins_bump' => true, 'data' => $new, 'quantity' => 1];
$svc->applyBumpPrice($cart);
echo "current bump product charged at {$new->price}\n"; $fail += '1.00' === $new->price ? 0 : 1;
// Offer box vs the cart line, shop enters prices net and displays them gross.
$shown = (new ReflectionMethod($svc, 'priceHtml'))->invoke($svc, $new, $GLOBALS['opt']['bump_settings']);
$line  = wc_price(wc_get_price_to_display($new, ['display_context' => 'cart']));
echo "offer box shows $shown, cart line shows $line\n"; $fail += $shown === $line ? 0 : 1;
echo $fail ? "FAIL ($fail)\n" : "PASS\n";
exit($fail ? 1 : 0);
}
