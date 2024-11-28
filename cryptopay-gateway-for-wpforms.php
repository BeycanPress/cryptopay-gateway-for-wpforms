<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength

/**
 * Plugin Name: CryptoPay Gateway for WPForms
 * Version:     1.0.1
 * Plugin URI:  https://beycanpress.com/cryptopay/
 * Description: Adds Cryptocurrency payment gateway (CryptoPay) for WPForms.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wpforms-cryptopay
 * Tags: Bitcoin, Ethereum, Crypto, Payment, WPForms
 * Requires at least: 5.0
 * Tested up to: 6.7.1
 * Requires PHP: 8.1
*/

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

define('WPFORMS_CRYPTOPAY_FILE', __FILE__);
define('WPFORMS_CRYPTOPAY_VERSION', '1.0.1');
define('WPFORMS_CRYPTOPAY_KEY', basename(__DIR__));
define('WPFORMS_CRYPTOPAY_URL', plugin_dir_url(__FILE__));
define('WPFORMS_CRYPTOPAY_DIR', plugin_dir_path(__FILE__));
define('WPFORMS_CRYPTOPAY_SLUG', plugin_basename(__FILE__));
define('WPFORMS_CRYPTOPAY_SVG_ICON', file_get_contents(WPFORMS_CRYPTOPAY_DIR . 'assets/images/icon.svg'));

use BeycanPress\CryptoPay\Integrator\Helpers;

/**
 * @return void
 */
function wpformsCryptoPayRegisterModels(): void
{
    Helpers::registerModel(BeycanPress\CryptoPay\WPForms\Models\TransactionsPro::class);
    Helpers::registerLiteModel(BeycanPress\CryptoPay\WPForms\Models\TransactionsLite::class);
}

wpformsCryptoPayRegisterModels();

add_action('init', function (): void {
    load_plugin_textdomain('wpforms-cryptopay', false, basename(__DIR__) . '/languages');
});

add_action('plugins_loaded', function (): void {
    wpformsCryptoPayRegisterModels();

    if (!defined('WPFORMS_VERSION')) {
        Helpers::requirePluginMessage('WPForms', admin_url('plugin-install.php?s=wpforms&tab=search&type=term'));
    } elseif (Helpers::bothExists()) {
        new BeycanPress\CryptoPay\WPForms\Loader();
        add_filter('wpforms_integrations_available', function (array $integrations): array {
            return array_merge($integrations, [
                'CryptoPay',
            ]);
        });
    } else {
        Helpers::requireCryptoPayMessage('WPForms');
    }
});
