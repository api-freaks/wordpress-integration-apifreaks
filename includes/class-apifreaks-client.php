<?php
/**
 * APIFreaks HTTP client.
 *
 * Wraps every request to https://api.apifreaks.com, injects the X-apiKey
 * header, caches responses in transients, and exposes one helper per
 * endpoint family the plugin uses.
 *
 * @package APIFreaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APIFreaks_Client {

	const BASE = 'https://api.apifreaks.com';

	/**
	 * Cached settings array.
	 *
	 * @var array
	 */
	protected $settings;

	public function __construct() {
		$this->settings = wp_parse_args(
			(array) get_option( APIFREAKS_OPTION, array() ),
			array(
				'api_key'          => '',
				'cache_hours'      => 24,
				'trust_cloudflare' => 1,
			)
		);
	}

	/**
	 * Whether an API key has been configured.
	 *
	 * @return bool
	 */
	public function has_key() {
		return '' !== trim( (string) $this->settings['api_key'] );
	}

	/**
	 * Cache lifetime in seconds.
	 *
	 * @return int
	 */
	protected function ttl() {
		$hours = absint( $this->settings['cache_hours'] );
		if ( $hours < 1 ) {
			$hours = 1;
		}
		return $hours * HOUR_IN_SECONDS;
	}

	/**
	 * Perform a cached GET request against the APIFreaks API.
	 *
	 * @param string $path         Endpoint path beginning with a slash.
	 * @param array  $query        Query-string parameters.
	 * @param array  $headers      Extra request headers (e.g. User-Agent).
	 * @param bool   $use_cache    Whether to read/write the transient cache.
	 * @return array|WP_Error      Decoded body on success, WP_Error on failure.
	 */
	public function get( $path, $query = array(), $headers = array(), $use_cache = true ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'apifreaks_no_key', __( 'No APIFreaks API key configured.', 'apifreaks' ) );
		}

		$query = array_filter(
			$query,
			static function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);

		$url = self::BASE . $path;
		if ( ! empty( $query ) ) {
			// add_query_arg() URL-encodes values for us.
			$url = add_query_arg( $query, $url );
		}

		$cache_key = 'apifreaks_' . md5( $url . wp_json_encode( $headers ) );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$args = array(
			'timeout' => 15,
			'headers' => array_merge(
				array(
					'X-apiKey' => trim( (string) $this->settings['api_key'] ),
					'Accept'   => 'application/json',
				),
				$headers
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && isset( $body['message'] )
				? $body['message']
				: sprintf(
					/* translators: %d: HTTP status code. */
					__( 'APIFreaks request failed (HTTP %d).', 'apifreaks' ),
					$code
				);
			return new WP_Error( 'apifreaks_http_' . $code, $message, $body );
		}

		if ( null === $body ) {
			return new WP_Error( 'apifreaks_bad_json', __( 'APIFreaks returned an unreadable response.', 'apifreaks' ) );
		}

		if ( $use_cache ) {
			set_transient( $cache_key, $body, $this->ttl() );
		}

		return $body;
	}

	/* ---------------------------------------------------------------------
	 * Visitor IP handling
	 * ------------------------------------------------------------------- */

	/**
	 * Best-effort detection of the visitor's public IP address.
	 *
	 * @return string
	 */
	public function visitor_ip() {
		$candidates = array();

		if ( ! empty( $this->settings['trust_cloudflare'] ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded    = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( $forwarded[0] );
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		foreach ( $candidates as $ip ) {
			$ip = trim( (string) $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * Endpoint helpers
	 * ------------------------------------------------------------------- */

	/**
	 * IP geolocation lookup. When $ip is empty the caller's IP is used.
	 *
	 * @param string $ip      IP address or hostname.
	 * @param string $include Comma-separated extra sections.
	 * @param string $lang    Response language.
	 * @return array|WP_Error
	 */
	public function geolocation( $ip = '', $include = 'location,currency,time_zone,security,country_metadata,network', $lang = 'en' ) {
		return $this->get(
			'/v1.0/geolocation/lookup',
			array(
				'ip'      => $ip,
				'include' => $include,
				'lang'    => $lang,
			)
		);
	}

	/**
	 * Cached geolocation for the current visitor.
	 *
	 * @return array|WP_Error
	 */
	public function visitor_geolocation() {
		return $this->geolocation( $this->visitor_ip() );
	}

	/**
	 * Dedicated IP security lookup.
	 *
	 * @param string $ip IP address.
	 * @return array|WP_Error
	 */
	public function ip_security( $ip = '' ) {
		return $this->get( '/v1.0/ip/security', array( 'ip' => $ip ) );
	}

	/**
	 * Timezone lookup. Pass any supported selector via $args.
	 *
	 * @param array $args Query args (tz, location, lat, long, ip, iata_code...).
	 * @return array|WP_Error
	 */
	public function timezone( $args = array() ) {
		return $this->get( '/v1.0/geolocation/timezone', $args );
	}

	/**
	 * Timezone conversion.
	 *
	 * @param array $args Query args (time, tz_from, tz_to, ...).
	 * @return array|WP_Error
	 */
	public function timezone_convert( $args = array() ) {
		return $this->get( '/v1.0/timezone/converter', $args );
	}

	/**
	 * Astronomy (sun/moon) data.
	 *
	 * @param array $args Query args (location, lat, long, ip, date, ...).
	 * @return array|WP_Error
	 */
	public function astronomy( $args = array() ) {
		return $this->get( '/v1.0/geolocation/astronomy', $args );
	}

	/**
	 * Parse a User-Agent string.
	 *
	 * @param string $ua User-Agent string.
	 * @return array|WP_Error
	 */
	public function user_agent( $ua ) {
		return $this->get( '/v1.0/user-agent/lookup', array(), array( 'User-Agent' => $ua ) );
	}

	/**
	 * Forward geocoding (address -> coordinates).
	 *
	 * @param string $query Free-form address.
	 * @param int    $limit Max results.
	 * @param string $lang  Accept-Language value.
	 * @return array|WP_Error
	 */
	public function geocode( $query, $limit = 1, $lang = '' ) {
		$headers = array();
		if ( '' !== $lang ) {
			$headers['Accept-Language'] = $lang;
		}
		return $this->get(
			'/v1.0/geocoder/search',
			array(
				'query' => $query,
				'limit' => $limit,
			),
			$headers
		);
	}

	/**
	 * Reverse geocoding (coordinates -> address).
	 *
	 * @param float  $lat  Latitude.
	 * @param float  $lon  Longitude.
	 * @param string $lang Accept-Language value.
	 * @return array|WP_Error
	 */
	public function reverse_geocode( $lat, $lon, $lang = '' ) {
		$headers = array();
		if ( '' !== $lang ) {
			$headers['Accept-Language'] = $lang;
		}
		return $this->get(
			'/v1.0/geocoder/reverse',
			array(
				'lat' => $lat,
				'lon' => $lon,
			),
			$headers
		);
	}

	/**
	 * ZIP / postal code lookup.
	 *
	 * @param string $code    Comma-separated codes.
	 * @param string $country ISO country code.
	 * @return array|WP_Error
	 */
	public function zipcode_lookup( $code, $country = '' ) {
		return $this->get(
			'/v1.0/zipcode/lookup',
			array(
				'code'    => $code,
				'country' => $country,
			)
		);
	}

	/**
	 * GeoDB request (generic passthrough for /v1.0/geo/*).
	 *
	 * @param string $path Path segment after /v1.0/geo, e.g. "countries".
	 * @param array  $args Query args.
	 * @return array|WP_Error
	 */
	public function geodb( $path, $args = array() ) {
		return $this->get( '/v1.0/geo/' . ltrim( $path, '/' ), $args );
	}

	/**
	 * Currency conversion using latest rates.
	 *
	 * @param string $from   Source currency.
	 * @param string $to     Target currency.
	 * @param float  $amount Amount to convert.
	 * @return array|WP_Error
	 */
	public function convert_currency( $from, $to, $amount = 1 ) {
		return $this->get(
			'/v1.0/currency/converter/latest/prices',
			array(
				'from'   => $from,
				'to'     => $to,
				'amount' => $amount,
			)
		);
	}

	/**
	 * Current weather conditions.
	 *
	 * @param array $args Query args (location, lat, long, ip, timezone).
	 * @return array|WP_Error
	 */
	public function weather_current( $args = array() ) {
		return $this->get( '/v1.0/weather/current', $args );
	}

	/**
	 * ZIP / postal codes for a city.
	 *
	 * @param array $args Query args (city, country, state_name, page).
	 * @return array|WP_Error
	 */
	public function zipcode_search_city( $args = array() ) {
		return $this->get( '/v1.0/zipcode/search/city', $args );
	}

	/**
	 * ZIP / postal codes for a region.
	 *
	 * @param array $args Query args (country, region, page).
	 * @return array|WP_Error
	 */
	public function zipcode_search_region( $args = array() ) {
		return $this->get( '/v1.0/zipcode/search/region', $args );
	}

	/**
	 * ZIP / postal codes within a radius.
	 *
	 * @param array $args Query args (code|lat+long, country, radius, unit, page).
	 * @return array|WP_Error
	 */
	public function zipcode_search_radius( $args = array() ) {
		return $this->get( '/v1.0/zipcode/search/radius', $args );
	}

	/* -------------------- Currency (extended) -------------------- */

	/**
	 * Latest exchange rates for a base currency.
	 *
	 * @param string $base    Base currency.
	 * @param string $symbols Comma-separated target codes.
	 * @return array|WP_Error
	 */
	public function currency_rates_latest( $base = '', $symbols = '' ) {
		return $this->get(
			'/v1.0/currency/rates/latest',
			array(
				'base'    => $base,
				'symbols' => $symbols,
			)
		);
	}

	/**
	 * Historical exchange rates on a given date.
	 *
	 * @param string $base    Base currency.
	 * @param string $symbols Comma-separated target codes.
	 * @param string $date    Date (YYYY-MM-DD).
	 * @return array|WP_Error
	 */
	public function currency_rates_historical( $base, $symbols, $date ) {
		return $this->get(
			'/v1.0/currency/rates/historical',
			array(
				'base'    => $base,
				'symbols' => $symbols,
				'date'    => $date,
			)
		);
	}

	/**
	 * Convert currency using a historical rate.
	 *
	 * @param string $from   Source currency.
	 * @param string $to     Target currency.
	 * @param float  $amount Amount.
	 * @param string $date   Date (YYYY-MM-DD).
	 * @return array|WP_Error
	 */
	public function convert_currency_historical( $from, $to, $amount, $date ) {
		return $this->get(
			'/v1.0/currency/converter/historical/prices',
			array(
				'from'   => $from,
				'to'     => $to,
				'amount' => $amount,
				'date'   => $date,
			)
		);
	}

	/**
	 * Exchange-rate time series over a date range.
	 *
	 * @param string $start   Start date (YYYY-MM-DD).
	 * @param string $end     End date (YYYY-MM-DD).
	 * @param string $base    Base currency.
	 * @param string $symbols Comma-separated target codes.
	 * @return array|WP_Error
	 */
	public function currency_time_series( $start, $end, $base = '', $symbols = '' ) {
		return $this->get(
			'/v1.0/currency/time-series',
			array(
				'startDate' => $start,
				'endDate'   => $end,
				'base'      => $base,
				'symbols'   => $symbols,
			)
		);
	}

	/**
	 * Exchange-rate fluctuation over a date range.
	 *
	 * @param string $start   Start date (YYYY-MM-DD).
	 * @param string $end     End date (YYYY-MM-DD).
	 * @param string $base    Base currency.
	 * @param string $symbols Comma-separated target codes.
	 * @return array|WP_Error
	 */
	public function currency_fluctuation( $start, $end, $base = '', $symbols = '' ) {
		return $this->get(
			'/v1.0/currency/fluctuation',
			array(
				'startDate' => $start,
				'endDate'   => $end,
				'base'      => $base,
				'symbols'   => $symbols,
			)
		);
	}

	/**
	 * Convert an amount into the visitor's local currency (by IP).
	 *
	 * @param string $from   Source currency.
	 * @param string $ip     IP address (empty = caller IP).
	 * @param float  $amount Amount.
	 * @return array|WP_Error
	 */
	public function convert_currency_by_ip( $from, $ip = '', $amount = 1 ) {
		return $this->get(
			'/v1.0/currency/converter/ip-to-currency',
			array(
				'from'   => $from,
				'ip'     => $ip,
				'amount' => $amount,
			)
		);
	}

	/**
	 * List of supported currencies.
	 *
	 * @return array|WP_Error
	 */
	public function currency_supported() {
		return $this->get( '/v1.0/currency/supported' );
	}

	/**
	 * Map of currency codes to names.
	 *
	 * @return array|WP_Error
	 */
	public function currency_symbols() {
		return $this->get( '/v1.0/currency/symbols' );
	}

	/* -------------------- Weather (extended) -------------------- */

	/**
	 * Weather forecast (1-16 days).
	 *
	 * @param array $args Query args.
	 * @return array|WP_Error
	 */
	public function weather_forecast( $args = array() ) {
		return $this->get( '/v1.0/weather/forecast', $args );
	}

	/**
	 * Historical weather for a specific date.
	 *
	 * @param array $args Query args (date required).
	 * @return array|WP_Error
	 */
	public function weather_historical( $args = array() ) {
		return $this->get( '/v1.0/weather/historical', $args );
	}

	/**
	 * Historical weather across a date range.
	 *
	 * @param array $args Query args (startDate, endDate required).
	 * @return array|WP_Error
	 */
	public function weather_time_series( $args = array() ) {
		return $this->get( '/v1.0/weather/time-series', $args );
	}

	/**
	 * Marine (ocean) weather forecast.
	 *
	 * @param array $args Query args.
	 * @return array|WP_Error
	 */
	public function weather_marine( $args = array() ) {
		return $this->get( '/v1.0/weather/marine', $args );
	}

	/**
	 * Air-quality data and forecast.
	 *
	 * @param array $args Query args.
	 * @return array|WP_Error
	 */
	public function weather_air_quality( $args = array() ) {
		return $this->get( '/v1.0/weather/air-quality', $args );
	}

	/**
	 * Flood / river-discharge forecast.
	 *
	 * @param array $args Query args (startDate, endDate required).
	 * @return array|WP_Error
	 */
	public function weather_flood( $args = array() ) {
		return $this->get( '/v1.0/weather/flood', $args );
	}
}
