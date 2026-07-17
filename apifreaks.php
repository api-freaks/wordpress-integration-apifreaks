<?php
/**
 * Plugin Name:       APIFreaks
 * Plugin URI:        https://apifreaks.com/api
 * Description:       Bring APIFreaks IP Geolocation, IP Security, Timezone, Astronomy, User-Agent, Geocoding, ZIP Code and GeoDB data into WordPress with shortcodes, conditional content, and WooCommerce currency display.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            APIFreaks
 * Author URI:        https://apifreaks.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apifreaks
 *
 * @package APIFreaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'APIFREAKS_VERSION', '1.0.0' );
define( 'APIFREAKS_FILE', __FILE__ );
define( 'APIFREAKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'APIFREAKS_URL', plugin_dir_url( __FILE__ ) );
define( 'APIFREAKS_OPTION', 'apifreaks_settings' );

require_once APIFREAKS_DIR . 'includes/class-apifreaks-client.php';
require_once APIFREAKS_DIR . 'includes/class-apifreaks-settings.php';
require_once APIFREAKS_DIR . 'includes/class-apifreaks-shortcodes.php';
require_once APIFREAKS_DIR . 'includes/class-apifreaks-woocommerce.php';

/**
 * Boot the plugin once all plugins are loaded.
 */
function apifreaks_bootstrap() {
	// Admin settings screen.
	if ( is_admin() ) {
		new APIFreaks_Settings();
	}

	// Shortcodes are needed on both front-end and in the block editor preview.
	new APIFreaks_Shortcodes();

	// WooCommerce integration (self-guards if WooCommerce is inactive).
	new APIFreaks_WooCommerce();
}
add_action( 'plugins_loaded', 'apifreaks_bootstrap' );

/**
 * Default options on activation.
 */
function apifreaks_activate() {
	$existing = get_option( APIFREAKS_OPTION );
	if ( false === $existing ) {
		add_option(
			APIFREAKS_OPTION,
			array(
				'api_key'          => '',
				'cache_hours'      => 24,
				'trust_cloudflare' => 1,
				'woo_enabled'      => 0,
				'woo_mode'         => 'append',
				'woo_base'         => get_option( 'woocommerce_currency', 'USD' ),
			)
		);
	}
}
register_activation_hook( __FILE__, 'apifreaks_activate' );

/**
 * Convenience accessor used across the plugin.
 *
 * @return APIFreaks_Client
 */
function apifreaks_client() {
	static $client = null;
	if ( null === $client ) {
		$client = new APIFreaks_Client();
	}
	return $client;
}

/**
 * Settings link on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function apifreaks_action_links( $links ) {
	$url          = admin_url( 'admin.php?page=apifreaks' );
	$settings     = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'apifreaks' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'apifreaks_action_links' );
