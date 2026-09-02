<?php
/**
 * Plugin Name:       Variza for WooCommerce
 * Plugin URI:        https://github.com/the6fallenangel/variza-woocommerce-plugin
 * Description:       Card-to-card (bank transfer) payment gateway with fully automatic order verification. Once the customer transfers the exact amount, the order is automatically marked as paid — no manual receipt review required.
 * Version:           1.1.0
 * Author:            Variza
 * Author URI:        https://variza.ir
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       variza-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.5
 */

defined( 'ABSPATH' ) || exit;

define( 'VARIZA_PLUGIN_FILE', __FILE__ );
define( 'VARIZA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VARIZA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VARIZA_VERSION', '1.1.0' );
define( 'VARIZA_API_BASE_URL', 'https://variza.ir/api/v1' );

define( 'VARIZA_LOGO_URL', VARIZA_PLUGIN_URL . 'assets/img/variza-logo.webp' );
define( 'VARIZA_ICON_URL', VARIZA_PLUGIN_URL . 'assets/img/variza-icon.webp' );

add_action( 'before_woocommerce_init', 'variza_declare_woocommerce_compatibility' );
function variza_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

require_once VARIZA_PLUGIN_DIR . 'includes/class-variza-plugin.php';

register_activation_hook( __FILE__, array( 'Variza_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Variza_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', 'variza_boot' );
function variza_boot() {
	if ( ! variza_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'variza_missing_woocommerce_notice' );
		return;
	}

	Variza_Plugin::instance();
}

/**
 * WooCommerce loads its main class on 'plugins_loaded' at priority 0 (`before_woocommerce_init`
 * even earlier), so by the time this default-priority callback runs, `WooCommerce` will be
 * defined if the plugin is active — regardless of activation order.
 */
function variza_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

function variza_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Variza for WooCommerce requires WooCommerce to be installed and activated.', 'variza-for-woocommerce' )
		. '</p></div>';
}