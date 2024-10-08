<?php
/**
    * The plugin bootstrap file
    *
    * This file is read by WordPress to generate the plugin information in the
    * plugin admin area. This file also includes all of the dependencies used by
    * the plugin, registers the activation and deactivation functions, and defines
    * a function that starts the plugin.
    *
    * @link https://www.linkedin.com/in/shakir-uz-zaman/
    * @since 1.0.0
    * @package Wishlist_Quote_Price_and_Notifier
    *
    * @wordpress-plugin
    * Plugin Name: Wishlist Quote Price and Notifier
    * Plugin URI: www.google.com
    * Description: A plugin for wishlist, quote, price, and notifier functionalities.
    * Version: 1.0.0
    * Author: zamanshakir
    * Author URI: https://www.linkedin.com/in/shakir-uz-zaman/
    * License: GPL-2.0+
    * License URI: https://www.gnu.org/licenses/gpl-2.0.html
    * Text Domain: wc-wishlist-quote-and-price-notifier
    * Domain Path: /languages
    *
    * WC requires at least: 5.0.0
    * WC tested up to: 7.1.0
*/
// Ensure WordPress is loaded.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Include Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

/*
 * Main plugin class
*/

final class WC_Wishlist_Quote_Price_and_Notifier
{
    /*
     * Plugin version
     *
     * $var string
     */
    public const version = '1.0.0';

    /*
     * Plugin constructor
     */
    private function __construct()
    {

        $this->define_constants();
        $this->check_older_version();

        register_activation_hook(__FILE__, [$this, 'activate']);

        add_action('plugins_loaded', [$this, 'init_plugin']);
    }

    /**
     * Initializes a singleton instance
     *
     * @return \WC_Wishlist_Quote_Price_and_Notifier
    */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }
        return $instance;
    }
    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_VERSION', self::version);
        define('WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_FILE', __FILE__);
        define('WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_PATH', __DIR__);
        define('WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_URL', plugins_url('', WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_FILE));
        define('WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_ASSETS', WC_WISHLIST_QUOTE_PRICE_AND_NOTIFIER_URL . '/assets');
    }
    /**
     * Check older version & update DB accordingly
    */
    public function check_older_version()
    {
        if (!get_option('wc_wishlist_quote_and_price_notifier_installed')) {
            $installer = new Shakir\WishlistQuotePriceAndNotifier\Installer\Installer();
            $installer->run();

        } else {
            if (get_option('wc_wishlist_quote_and_price_notifier_version') < self::version) {
                update_option('wc_wishlist_quote_and_price_notifier_version', self::version);
            }
        }
    }

    /**
     * Initialize the plugin
     *
     * @return void
    */
    public function init_plugin()
    {
        //var_dump("plugin loadded");
        new Shakir\WishlistQuotePriceAndNotifier\Assets\Assets();
        Shakir\WishlistQuotePriceAndNotifier\Frontend\Frontend::get_instance();
        if(is_admin()) {
            // Initialize the Admin class
            Shakir\WishlistQuotePriceAndNotifier\Admin\Admin::get_instance();
        }
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
    */
    public function activate()
    {
        // check if woocommerce is already activated or not
        $checkWC   = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

        if (!$checkWC) {
            // if woocommerce is not installed, display an admin notice
            $admin_notice = new Shakir\WishlistQuotePriceAndNotifier\Admin\Admin_Notice();
            add_action('admin_notices', [$admin_notice, 'check_require_plugin_notice']);

        } else {
            $installer = new Shakir\WishlistQuotePriceAndNotifier\Installer\Installer();
            $installer->run();
        }
    }

}
/**
 * Initializes the main plugin
 *
 * @return \WC_Wishlist_Quote_Price_and_Notifier
 */
function wc_wishlist_quote_price_and_notifier()
{
    return WC_Wishlist_Quote_Price_and_Notifier::init();
}

// kick-off the plugin
wc_wishlist_quote_price_and_notifier();
