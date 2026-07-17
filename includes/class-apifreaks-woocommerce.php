<?php
/**
 * WooCommerce integration: show approximate prices in the visitor's
 * local currency, detected from their IP via APIFreaks.
 *
 * Display only. Orders are still charged in the store's base currency,
 * so this never touches the cart, checkout, or order totals.
 *
 * @package APIFreaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APIFreaks_WooCommerce {

	/**
	 * Settings snapshot.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Resolved visitor currency code (uppercase) or empty string.
	 *
	 * @var string|null
	 */
	protected $visitor_currency = null;

	/**
	 * Resolved visitor currency symbol.
	 *
	 * @var string
	 */
	protected $visitor_symbol = '';

	public function __construct() {
		$this->settings = wp_parse_args(
			(array) get_option( APIFREAKS_OPTION, array() ),
			array(
				'woo_enabled' => 0,
				'woo_mode'    => 'append',
				'woo_base'    => 'USD',
			)
		);

		if ( empty( $this->settings['woo_enabled'] ) ) {
			return;
		}

		// Only wire up when WooCommerce is present.
		add_action( 'woocommerce_loaded', array( $this, 'init' ) );
		// Fallback if WooCommerce loaded before us.
		if ( class_exists( 'WooCommerce' ) ) {
			$this->init();
		}
	}

	public function init() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 20, 2 );
	}

	/**
	 * Base (store) currency.
	 *
	 * @return string
	 */
	protected function base_currency() {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return get_woocommerce_currency();
		}
		return strtoupper( (string) $this->settings['woo_base'] );
	}

	/**
	 * Resolve the visitor's currency once per request.
	 *
	 * @return string Currency code, or '' if unavailable.
	 */
	protected function get_visitor_currency() {
		if ( null !== $this->visitor_currency ) {
			return $this->visitor_currency;
		}

		$this->visitor_currency = '';

		$geo = apifreaks_client()->visitor_geolocation();
		if ( is_wp_error( $geo ) ) {
			return '';
		}

		if ( isset( $geo['currency']['code'] ) ) {
			$this->visitor_currency = strtoupper( (string) $geo['currency']['code'] );
		}
		if ( isset( $geo['currency']['symbol'] ) ) {
			$this->visitor_symbol = (string) $geo['currency']['symbol'];
		}

		return $this->visitor_currency;
	}

	/**
	 * Get (and cache) the conversion rate from base to target currency.
	 *
	 * @param string $base   Base currency.
	 * @param string $target Target currency.
	 * @return float|null
	 */
	protected function get_rate( $base, $target ) {
		$result = apifreaks_client()->convert_currency( $base, $target, 1 );
		if ( is_wp_error( $result ) || ! isset( $result['rate'] ) ) {
			return null;
		}
		$rate = (float) $result['rate'];
		return $rate > 0 ? $rate : null;
	}

	/**
	 * Format a numeric amount with the visitor's currency.
	 *
	 * @param float  $amount   Converted amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	protected function format_amount( $amount, $currency ) {
		$symbol = '' !== $this->visitor_symbol ? $this->visitor_symbol : '';

		// Prefer WooCommerce's own symbol table when available.
		if ( '' === $symbol && function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$symbol = get_woocommerce_currency_symbol( $currency );
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$number   = number_format_i18n( $amount, $decimals );

		if ( '' !== $symbol && $symbol !== $currency ) {
			return $symbol . $number;
		}
		return $number . ' ' . $currency;
	}

	/**
	 * Append or replace the WooCommerce price HTML with a localized figure.
	 *
	 * @param string     $price_html Existing price HTML.
	 * @param WC_Product $product    Product object.
	 * @return string
	 */
	public function filter_price_html( $price_html, $product ) {
		// Never alter prices inside wp-admin.
		if ( is_admin() ) {
			return $price_html;
		}

		$base   = $this->base_currency();
		$target = $this->get_visitor_currency();

		if ( '' === $target || $target === $base ) {
			return $price_html;
		}

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return $price_html;
		}

		$price = (float) $product->get_price();
		if ( $price <= 0 ) {
			return $price_html;
		}

		$rate = $this->get_rate( $base, $target );
		if ( null === $rate ) {
			return $price_html;
		}

		$converted = $this->format_amount( $price * $rate, $target );
		$mode      = ( 'replace' === $this->settings['woo_mode'] ) ? 'replace' : 'append';

		if ( 'replace' === $mode ) {
			return '<span class="apifreaks-woo-price">' . esc_html( $converted ) . '</span>'
				. ' <small class="apifreaks-woo-note" style="opacity:.7;">'
				. esc_html__( '(approx.)', 'apifreaks' ) . '</small>';
		}

		// Append mode: keep the real price, add the approximate local one.
		$approx = sprintf(
			/* translators: %s: converted price string. */
			esc_html__( '(approx. %s)', 'apifreaks' ),
			esc_html( $converted )
		);

		return $price_html
			. ' <span class="apifreaks-woo-approx" style="opacity:.75;font-size:.9em;">'
			. $approx
			. '</span>';
	}
}
