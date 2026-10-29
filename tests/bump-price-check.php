<?php

/**
 * The bump special price covers one unit added to an order, nothing more.
 *
 * Run: php tests/bump-price-check.php
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('BUMP_DIR', dirname(__DIR__) . '/');

    function is_admin(): bool { return false; }
    function wp_doing_ajax(): bool { return false; }
    function get_option(string $name, mixed $default = false): mixed { return ['bump_price' => '5']; }

    class WC_Product
    {
        public string $price = '50';
        public function set_price(string $price): void { $this->price = $price; }
    }

    class WC_Cart
    {
        /** @var array<string, array<string, mixed>> */
        public array $cart_contents = [];
        /** @return array<string, array<string, mixed>> */
        public function get_cart(): array { return $this->cart_contents; }
        public function set_quantity(string $key, int $qty, bool $refresh = true): bool
        {
            $this->cart_contents[$key]['quantity'] = $qty;
            return true;
        }
    }

    require dirname(__DIR__) . '/src/Contract/HasHooks.php';
    require dirname(__DIR__) . '/src/Service/BumpService.php';

    $fail = 0;
    $check = static function (bool $ok, string $what) use (&$fail): void {
        echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
        $fail += $ok ? 0 : 1;
    };

    $service = (new ReflectionClass(\Bump\Service\BumpService::class))->newInstanceWithoutConstructor();

    // Bump next to a normal line, quantity raised to 50.
    $cart = new WC_Cart();
    $bump = new WC_Product();
    $cart->cart_contents = [
        'a' => ['quantity' => 1, 'data' => new WC_Product()],
        'b' => ['quantity' => 50, 'data' => $bump, '_plogins_bump' => true],
    ];
    $service->applyBumpPrice($cart);
    $check($cart->cart_contents['b']['quantity'] === 1, 'raised bump quantity is reset to 1');
    $check($bump->price === '5', 'special price applies next to another line');

    // Bump left alone in the cart.
    $cart = new WC_Cart();
    $alone = new WC_Product();
    $cart->cart_contents = ['b' => ['quantity' => 1, 'data' => $alone, '_plogins_bump' => true]];
    $service->applyBumpPrice($cart);
    $check($alone->price === '50', 'no special price when the bump is the only line');

    exit($fail > 0 ? 1 : 0);
}
