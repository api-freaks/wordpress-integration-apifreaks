<?php
/**
 * Shortcode handlers for the APIFreaks plugin.
 *
 * @package APIFreaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APIFreaks_Shortcodes {

	public function __construct() {
		add_shortcode( 'apifreaks_ip', array( $this, 'sc_ip' ) );
		add_shortcode( 'apifreaks_if', array( $this, 'sc_if' ) );
		add_shortcode( 'apifreaks_if_not', array( $this, 'sc_if_not' ) );
		add_shortcode( 'apifreaks_timezone', array( $this, 'sc_timezone' ) );
		add_shortcode( 'apifreaks_astronomy', array( $this, 'sc_astronomy' ) );
		add_shortcode( 'apifreaks_user_agent', array( $this, 'sc_user_agent' ) );
		add_shortcode( 'apifreaks_geocode', array( $this, 'sc_geocode' ) );
		add_shortcode( 'apifreaks_reverse_geocode', array( $this, 'sc_reverse_geocode' ) );
		add_shortcode( 'apifreaks_zipcode', array( $this, 'sc_zipcode' ) );
		add_shortcode( 'apifreaks_zipcode_search', array( $this, 'sc_zipcode_search' ) );
		add_shortcode( 'apifreaks_geodb', array( $this, 'sc_geodb' ) );
		add_shortcode( 'apifreaks_weather', array( $this, 'sc_weather' ) );
		add_shortcode( 'apifreaks_weather_forecast', array( $this, 'sc_weather_forecast' ) );
		add_shortcode( 'apifreaks_weather_historical', array( $this, 'sc_weather_historical' ) );
		add_shortcode( 'apifreaks_weather_marine', array( $this, 'sc_weather_marine' ) );
		add_shortcode( 'apifreaks_air_quality', array( $this, 'sc_air_quality' ) );
		add_shortcode( 'apifreaks_weather_flood', array( $this, 'sc_weather_flood' ) );
		add_shortcode( 'apifreaks_currency_convert', array( $this, 'sc_currency_convert' ) );
		add_shortcode( 'apifreaks_currency_rate', array( $this, 'sc_currency_rate' ) );
		add_shortcode( 'apifreaks_currency_fluctuation', array( $this, 'sc_currency_fluctuation' ) );
		add_shortcode( 'apifreaks_currency_name', array( $this, 'sc_currency_name' ) );
		add_shortcode( 'apifreaks_timezone_convert', array( $this, 'sc_timezone_convert' ) );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Read a value out of a nested array using dot notation.
	 *
	 * @param array  $data Source array.
	 * @param string $path e.g. "location.city".
	 * @return mixed|null
	 */
	protected function dig( $data, $path ) {
		if ( ! is_array( $data ) || '' === $path ) {
			return null;
		}
		$node = $data;
		foreach ( explode( '.', $path ) as $key ) {
			if ( is_array( $node ) && array_key_exists( $key, $node ) ) {
				$node = $node[ $key ];
			} else {
				return null;
			}
		}
		return $node;
	}

	/**
	 * Friendly field aliases for the visitor geolocation shortcode.
	 *
	 * @return array
	 */
	protected function ip_field_map() {
		return array(
			'ip'              => 'ip',
			'hostname'        => 'hostname',
			'country'         => 'location.country_name',
			'country_official'=> 'location.country_name_official',
			'country_code'    => 'location.country_code2',
			'country_code3'   => 'location.country_code3',
			'capital'         => 'location.country_capital',
			'city'            => 'location.city',
			'state'           => 'location.state_prov',
			'region'          => 'location.state_prov',
			'state_code'      => 'location.state_code',
			'district'        => 'location.district',
			'zipcode'         => 'location.zipcode',
			'postal'          => 'location.zipcode',
			'continent'       => 'location.continent_name',
			'continent_code'  => 'location.continent_code',
			'latitude'        => 'location.latitude',
			'longitude'       => 'location.longitude',
			'flag'            => 'location.country_flag',
			'emoji'           => 'location.country_emoji',
			'currency'        => 'currency.code',
			'currency_name'   => 'currency.name',
			'currency_symbol' => 'currency.symbol',
			'calling_code'    => 'country_metadata.calling_code',
			'tld'             => 'country_metadata.tld',
			'languages'       => 'country_metadata.languages',
			'timezone'        => 'time_zone.name',
			'time'            => 'time_zone.current_time',
			'org'             => 'network.company.name',
			'isp'             => 'network.company.name',
			'company'         => 'network.company.name',
			'asn'             => 'network.asn.number',
			'is_proxy'        => 'security.is_proxy',
			'is_tor'          => 'security.is_tor',
			'is_anonymous'    => 'security.is_anonymous',
			'is_bot'          => 'security.is_bot',
			'is_spam'         => 'security.is_spam',
			'is_cloud_provider' => 'security.is_cloud_provider',
			'threat_score'    => 'security.threat_score',
		);
	}

	/**
	 * Turn a raw value into display-safe text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function stringify( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'stringify' ), $value ) );
		}
		return (string) $value;
	}

	/**
	 * Render a short, non-alarming notice for the current user only.
	 * Front-end visitors never see raw error strings.
	 *
	 * @param WP_Error $error   Error object.
	 * @param string   $default Fallback text shown to visitors.
	 * @return string
	 */
	protected function soft_error( $error, $default = '' ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<span class="apifreaks-error" style="color:#b32d2e;">[APIFreaks: ' . esc_html( $error->get_error_message() ) . ']</span>';
		}
		return esc_html( $default );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_ip field="country"]
	 * ------------------------------------------------------------------- */

	public function sc_ip( $atts ) {
		$atts = shortcode_atts(
			array(
				'field'   => 'country',
				'ip'      => '',
				'default' => '',
			),
			$atts,
			'apifreaks_ip'
		);

		$client = apifreaks_client();
		$data   = '' !== $atts['ip'] ? $client->geolocation( $atts['ip'] ) : $client->visitor_geolocation();

		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$map   = $this->ip_field_map();
		$field = strtolower( trim( $atts['field'] ) );
		$path  = isset( $map[ $field ] ) ? $map[ $field ] : $field; // allow raw dot paths too.
		$value = $this->dig( $data, $path );

		if ( null === $value || '' === $value ) {
			return esc_html( $atts['default'] );
		}

		// Country flag renders as an image.
		if ( 'flag' === $field ) {
			return '<img class="apifreaks-flag" src="' . esc_url( $value ) . '" alt="" style="height:1em;vertical-align:middle;" />';
		}

		return esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * Conditional content
	 * ------------------------------------------------------------------- */

	/**
	 * Evaluate the visitor against a set of location conditions.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return bool|WP_Error
	 */
	protected function evaluate_conditions( $atts ) {
		$client = apifreaks_client();
		$data   = $client->visitor_geolocation();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$logic = strtoupper( isset( $atts['logic'] ) ? $atts['logic'] : 'AND' );
		$logic = ( 'OR' === $logic ) ? 'OR' : 'AND';

		$checks = array(
			'country'           => 'location.country_name',
			'country_code'      => 'location.country_code2',
			'state'             => 'location.state_prov',
			'city'              => 'location.city',
			'continent'         => 'location.continent_name',
			'is_proxy'          => 'security.is_proxy',
			'is_tor'            => 'security.is_tor',
			'is_anonymous'      => 'security.is_anonymous',
			'is_bot'            => 'security.is_bot',
			'is_cloud_provider' => 'security.is_cloud_provider',
		);

		$results = array();

		foreach ( $checks as $attr => $path ) {
			if ( empty( $atts[ $attr ] ) ) {
				continue;
			}
			$actual = $this->dig( $data, $path );

			// Boolean-style attributes.
			if ( 0 === strpos( $attr, 'is_' ) ) {
				$want          = in_array( strtolower( (string) $atts[ $attr ] ), array( 'yes', 'true', '1' ), true );
				$results[]     = ( (bool) $actual === $want );
				continue;
			}

			// Value-list attributes (comma separated, case-insensitive).
			$allowed = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $atts[ $attr ] ) ) ) );
			$results[] = in_array( strtolower( (string) $actual ), $allowed, true );
		}

		if ( empty( $results ) ) {
			return false;
		}

		return ( 'OR' === $logic ) ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	public function sc_if( $atts, $content = '' ) {
		$match = $this->evaluate_conditions( (array) $atts );
		if ( is_wp_error( $match ) ) {
			// On failure, hide gated content for visitors; warn admins.
			return current_user_can( 'manage_options' ) ? $this->soft_error( $match ) : '';
		}
		return $match ? wp_kses_post( do_shortcode( $content ) ) : '';
	}

	public function sc_if_not( $atts, $content = '' ) {
		$match = $this->evaluate_conditions( (array) $atts );
		if ( is_wp_error( $match ) ) {
			return current_user_can( 'manage_options' ) ? $this->soft_error( $match ) : wp_kses_post( do_shortcode( $content ) );
		}
		return $match ? '' : wp_kses_post( do_shortcode( $content ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_timezone]
	 * ------------------------------------------------------------------- */

	public function sc_timezone( $atts ) {
		$atts = shortcode_atts(
			array(
				'tz'       => '',
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'iata'     => '',
				'field'    => 'date_time',
				'default'  => '',
			),
			$atts,
			'apifreaks_timezone'
		);

		$args = array(
			'tz'        => $atts['tz'],
			'location'  => $atts['location'],
			'lat'       => $atts['lat'],
			'long'      => $atts['long'],
			'ip'        => $atts['ip'],
			'iata_code' => $atts['iata'],
		);

		// If nothing was specified, resolve for the visitor's IP.
		if ( '' === implode( '', array_map( 'strval', $args ) ) ) {
			$args['ip'] = apifreaks_client()->visitor_ip();
		}

		$data = apifreaks_client()->timezone( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = trim( $atts['field'] );
		// Timezone fields live under time_zone.*; allow bare field names.
		$value = $this->dig( $data, $field );
		if ( null === $value ) {
			$value = $this->dig( $data, 'time_zone.' . $field );
		}

		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_astronomy]
	 * ------------------------------------------------------------------- */

	public function sc_astronomy( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'date'     => '',
				'field'    => 'sunrise',
				'default'  => '',
			),
			$atts,
			'apifreaks_astronomy'
		);

		$args = array(
			'location' => $atts['location'],
			'lat'      => $atts['lat'],
			'long'     => $atts['long'],
			'ip'       => $atts['ip'],
			'date'     => $atts['date'],
		);

		if ( '' === $atts['location'] && '' === $atts['lat'] && '' === $atts['ip'] ) {
			$args['ip'] = apifreaks_client()->visitor_ip();
		}

		$data = apifreaks_client()->astronomy( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = trim( $atts['field'] );
		$value = $this->dig( $data, $field );
		if ( null === $value ) {
			$value = $this->dig( $data, 'astronomy.' . $field );
		}

		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_user_agent]
	 * ------------------------------------------------------------------- */

	public function sc_user_agent( $atts ) {
		$atts = shortcode_atts(
			array(
				'ua'      => '',
				'field'   => 'name',
				'default' => '',
			),
			$atts,
			'apifreaks_user_agent'
		);

		$ua = '' !== $atts['ua'] ? $atts['ua'] : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		if ( '' === $ua ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->user_agent( $ua );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$value = $this->dig( $data, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_geocode] forward
	 * ------------------------------------------------------------------- */

	public function sc_geocode( $atts ) {
		$atts = shortcode_atts(
			array(
				'query'   => '',
				'field'   => 'full_address',
				'lang'    => '',
				'default' => '',
			),
			$atts,
			'apifreaks_geocode'
		);

		if ( '' === trim( $atts['query'] ) ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->geocode( $atts['query'], 1, $atts['lang'] );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		// Forward geocoder returns an array of matches.
		$first = ( isset( $data[0] ) && is_array( $data[0] ) ) ? $data[0] : $data;
		$value = $this->dig( $first, trim( $atts['field'] ) );

		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_reverse_geocode] reverse
	 * ------------------------------------------------------------------- */

	public function sc_reverse_geocode( $atts ) {
		$atts = shortcode_atts(
			array(
				'lat'     => '',
				'lon'     => '',
				'field'   => 'full_address',
				'lang'    => '',
				'default' => '',
			),
			$atts,
			'apifreaks_reverse_geocode'
		);

		if ( '' === $atts['lat'] || '' === $atts['lon'] ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->reverse_geocode( $atts['lat'], $atts['lon'], $atts['lang'] );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$value = $this->dig( $data, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_zipcode]
	 * ------------------------------------------------------------------- */

	public function sc_zipcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'code'      => '',
				'country'   => '',
				'field'     => 'city',
				'separator' => ', ',
				'default'   => '',
			),
			$atts,
			'apifreaks_zipcode'
		);

		if ( '' === trim( $atts['code'] ) ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->zipcode_lookup( $atts['code'], $atts['country'] );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$results = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
		if ( empty( $results ) ) {
			return esc_html( $atts['default'] );
		}

		$field  = trim( $atts['field'] );
		$values = array();
		foreach ( $results as $row ) {
			$v = $this->dig( $row, $field );
			if ( null !== $v && '' !== $v ) {
				$values[] = $this->stringify( $v );
			}
		}
		$values = array_values( array_unique( $values ) );

		return empty( $values )
			? esc_html( $atts['default'] )
			: esc_html( implode( $atts['separator'], $values ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_geodb]
	 * ------------------------------------------------------------------- */

	public function sc_geodb( $atts ) {
		$atts = shortcode_atts(
			array(
				'resource'    => 'countries', // countries|country|regions|subregions|admin-levels|admin-units|cities
				'country'     => '',
				'region'      => '',
				'subregion'   => '',
				'admin_unit'  => '',
				'adminlevels' => '',
				'field'       => '',
				'separator'   => ', ',
				'default'     => '',
			),
			$atts,
			'apifreaks_geodb'
		);

		$resource = strtolower( trim( $atts['resource'] ) );

		$path_map = array(
			'countries'    => 'countries',
			'country'      => 'country/details',
			'regions'      => 'regions',
			'subregions'   => 'subregions',
			'admin-levels' => 'admin-levels',
			'admin-units'  => 'admin-units',
			'admin-unit'   => 'admin-unit/details',
			'cities'       => 'cities',
		);

		if ( ! isset( $path_map[ $resource ] ) ) {
			return $this->soft_error(
				new WP_Error( 'apifreaks_geodb_resource', __( 'Unknown GeoDB resource.', 'apifreaks' ) ),
				$atts['default']
			);
		}

		$args = array(
			'country'     => $atts['country'],
			'region'      => $atts['region'],
			'subregion'   => $atts['subregion'],
			'admin_unit'  => $atts['admin_unit'],
			'adminLevels' => $atts['adminlevels'],
		);

		$data = apifreaks_client()->geodb( $path_map[ $resource ], $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = trim( $atts['field'] );

		// Scalar/object detail response: return one field.
		if ( '' !== $field && ! $this->is_list( $data ) ) {
			$value = $this->dig( $data, $field );
			return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
		}

		// List response: locate the array of items.
		$items = $this->first_list( $data );

		if ( empty( $items ) ) {
			// Nothing list-like; if a field was requested try to read it directly.
			if ( '' !== $field ) {
				$value = $this->dig( $data, $field );
				return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
			}
			return esc_html( $atts['default'] );
		}

		// No field -> return the count.
		if ( '' === $field ) {
			return esc_html( (string) count( $items ) );
		}

		// Field -> map across items and join.
		$values = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$v = $this->dig( $item, $field );
				if ( null !== $v && '' !== $v ) {
					$values[] = $this->stringify( $v );
				}
			} elseif ( is_scalar( $item ) ) {
				$values[] = (string) $item;
			}
		}

		return empty( $values )
			? esc_html( $atts['default'] )
			: esc_html( implode( $atts['separator'], $values ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_weather]
	 * ------------------------------------------------------------------- */

	/**
	 * Map a WMO weather code to a short human description.
	 *
	 * @param int $code WMO code.
	 * @return string
	 */
	protected function wmo_description( $code ) {
		$map = array(
			0  => 'Clear sky',
			1  => 'Mainly clear',
			2  => 'Partly cloudy',
			3  => 'Overcast',
			45 => 'Fog',
			48 => 'Depositing rime fog',
			51 => 'Light drizzle',
			53 => 'Moderate drizzle',
			55 => 'Dense drizzle',
			56 => 'Light freezing drizzle',
			57 => 'Dense freezing drizzle',
			61 => 'Slight rain',
			63 => 'Moderate rain',
			65 => 'Heavy rain',
			66 => 'Light freezing rain',
			67 => 'Heavy freezing rain',
			71 => 'Slight snowfall',
			73 => 'Moderate snowfall',
			75 => 'Heavy snowfall',
			77 => 'Snow grains',
			80 => 'Slight rain showers',
			81 => 'Moderate rain showers',
			82 => 'Violent rain showers',
			85 => 'Slight snow showers',
			86 => 'Heavy snow showers',
			95 => 'Thunderstorm',
			96 => 'Thunderstorm with slight hail',
			99 => 'Thunderstorm with heavy hail',
		);
		$code = (int) $code;
		return isset( $map[ $code ] ) ? $map[ $code ] : '';
	}

	public function sc_weather( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'timezone' => '',
				'field'    => 'temperature',
				'default'  => '',
			),
			$atts,
			'apifreaks_weather'
		);

		$args = array(
			'location' => $atts['location'],
			'lat'      => $atts['lat'],
			'long'     => $atts['long'],
			'ip'       => $atts['ip'],
			'timezone' => $atts['timezone'],
		);

		if ( '' === $atts['location'] && '' === $atts['lat'] && '' === $atts['ip'] ) {
			$args['ip'] = apifreaks_client()->visitor_ip();
		}

		$data = apifreaks_client()->weather_current( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = strtolower( trim( $atts['field'] ) );

		// Friendly field -> dot path map.
		$map = array(
			'temperature'    => 'current.temperature_2m',
			'feels_like'     => 'current.apparent_temperature',
			'humidity'       => 'current.relative_humidity_2m',
			'precipitation'  => 'current.precipitation',
			'rain'           => 'current.rain',
			'snowfall'       => 'current.snowfall',
			'cloud_cover'    => 'current.cloud_cover',
			'pressure'       => 'current.pressure_msl',
			'wind_speed'     => 'current.wind_speed_10m',
			'wind_direction' => 'current.wind_direction_10m',
			'wind_gusts'     => 'current.wind_gusts_10m',
			'weather_code'   => 'current.weather_code',
			'timestamp'      => 'current.timestamp',
		);

		// Special: a readable condition string derived from the WMO code.
		if ( 'description' === $field || 'condition' === $field ) {
			$code = $this->dig( $data, 'current.weather_code' );
			$desc = null === $code ? '' : $this->wmo_description( $code );
			return '' === $desc ? esc_html( $atts['default'] ) : esc_html( $desc );
		}

		$path  = isset( $map[ $field ] ) ? $map[ $field ] : $field;
		$value = $this->dig( $data, $path );

		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_currency_convert]
	 * ------------------------------------------------------------------- */

	public function sc_currency_convert( $atts ) {
		$atts = shortcode_atts(
			array(
				'from'    => '',
				'to'      => '',
				'amount'  => '1',
				'date'    => '',
				'field'   => 'convertedAmount',
				'decimals' => '',
				'default' => '',
			),
			$atts,
			'apifreaks_currency_convert'
		);

		if ( '' === trim( $atts['from'] ) || '' === trim( $atts['to'] ) ) {
			return esc_html( $atts['default'] );
		}

		if ( '' !== trim( $atts['date'] ) ) {
			$data = apifreaks_client()->convert_currency_historical( $atts['from'], $atts['to'], $atts['amount'], $atts['date'] );
		} else {
			$data = apifreaks_client()->convert_currency( $atts['from'], $atts['to'], $atts['amount'] );
		}
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$value = $this->dig( $data, trim( $atts['field'] ) );
		if ( null === $value ) {
			return esc_html( $atts['default'] );
		}

		// Optional rounding for the amount fields.
		if ( '' !== $atts['decimals'] && is_numeric( $value ) ) {
			$value = number_format_i18n( (float) $value, absint( $atts['decimals'] ) );
		}

		return esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_timezone_convert]
	 * ------------------------------------------------------------------- */

	public function sc_timezone_convert( $atts ) {
		$atts = shortcode_atts(
			array(
				'time'          => '',
				'tz_from'       => '',
				'tz_to'         => '',
				'location_from' => '',
				'location_to'   => '',
				'field'         => 'converted_time',
				'default'       => '',
			),
			$atts,
			'apifreaks_timezone_convert'
		);

		$args = array(
			'time'          => $atts['time'],
			'tz_from'       => $atts['tz_from'],
			'tz_to'         => $atts['tz_to'],
			'location_from' => $atts['location_from'],
			'location_to'   => $atts['location_to'],
		);

		$data = apifreaks_client()->timezone_convert( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$value = $this->dig( $data, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_zipcode_search]
	 * ------------------------------------------------------------------- */

	public function sc_zipcode_search( $atts ) {
		$atts = shortcode_atts(
			array(
				'mode'       => 'city', // city | region | radius
				'city'       => '',
				'region'     => '',
				'state_name' => '',
				'country'    => '',
				'code'       => '',
				'lat'        => '',
				'long'       => '',
				'radius'     => '',
				'unit'       => 'km',
				'field'      => '',
				'separator'  => ', ',
				'default'    => '',
			),
			$atts,
			'apifreaks_zipcode_search'
		);

		$mode = strtolower( trim( $atts['mode'] ) );

		switch ( $mode ) {
			case 'region':
				$data = apifreaks_client()->zipcode_search_region(
					array(
						'country' => $atts['country'],
						'region'  => $atts['region'],
					)
				);
				break;

			case 'radius':
				$data = apifreaks_client()->zipcode_search_radius(
					array(
						'code'    => $atts['code'],
						'lat'     => $atts['lat'],
						'long'    => $atts['long'],
						'country' => $atts['country'],
						'radius'  => $atts['radius'],
						'unit'    => $atts['unit'],
					)
				);
				break;

			case 'city':
			default:
				$data = apifreaks_client()->zipcode_search_city(
					array(
						'city'       => $atts['city'],
						'country'    => $atts['country'],
						'state_name' => $atts['state_name'],
					)
				);
				break;
		}

		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		// city/region return { codes: [...] }; radius returns { results: [ {code,...} ] }.
		if ( isset( $data['codes'] ) && is_array( $data['codes'] ) ) {
			return empty( $data['codes'] )
				? esc_html( $atts['default'] )
				: esc_html( implode( $atts['separator'], $data['codes'] ) );
		}

		if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
			$field  = '' !== trim( $atts['field'] ) ? trim( $atts['field'] ) : 'code';
			$values = array();
			foreach ( $data['results'] as $row ) {
				$v = $this->dig( $row, $field );
				if ( null !== $v && '' !== $v ) {
					$values[] = $this->stringify( $v );
				}
			}
			return empty( $values )
				? esc_html( $atts['default'] )
				: esc_html( implode( $atts['separator'], $values ) );
		}

		return esc_html( $atts['default'] );
	}

	/* ---------------------------------------------------------------------
	 * [apifreaks_currency_rate] / _fluctuation / _name
	 * ------------------------------------------------------------------- */

	public function sc_currency_rate( $atts ) {
		$atts = shortcode_atts(
			array(
				'base'      => '',
				'symbols'   => '',
				'date'      => '',
				'field'     => '',
				'decimals'  => '',
				'separator' => ', ',
				'default'   => '',
			),
			$atts,
			'apifreaks_currency_rate'
		);

		if ( '' !== trim( $atts['date'] ) ) {
			$data = apifreaks_client()->currency_rates_historical( $atts['base'], $atts['symbols'], $atts['date'] );
		} else {
			$data = apifreaks_client()->currency_rates_latest( $atts['base'], $atts['symbols'] );
		}
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$rates = isset( $data['rates'] ) && is_array( $data['rates'] ) ? $data['rates'] : array();
		if ( empty( $rates ) ) {
			return esc_html( $atts['default'] );
		}

		$field = strtoupper( trim( $atts['field'] ) );

		// Single currency requested.
		if ( '' !== $field ) {
			if ( ! isset( $rates[ $field ] ) ) {
				return esc_html( $atts['default'] );
			}
			$value = $rates[ $field ];
			if ( '' !== $atts['decimals'] && is_numeric( $value ) ) {
				$value = number_format_i18n( (float) $value, absint( $atts['decimals'] ) );
			}
			return esc_html( $this->stringify( $value ) );
		}

		// No field: list "CODE: rate" pairs.
		$parts = array();
		foreach ( $rates as $code => $rate ) {
			$parts[] = $code . ': ' . $this->stringify( $rate );
		}
		return esc_html( implode( $atts['separator'], $parts ) );
	}

	public function sc_currency_fluctuation( $atts ) {
		$atts = shortcode_atts(
			array(
				'base'    => '',
				'symbols' => '',
				'start'   => '',
				'end'     => '',
				'code'    => '',
				'field'   => 'percentChange',
				'default' => '',
			),
			$atts,
			'apifreaks_currency_fluctuation'
		);

		if ( '' === trim( $atts['start'] ) || '' === trim( $atts['end'] ) ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->currency_fluctuation( $atts['start'], $atts['end'], $atts['base'], $atts['symbols'] );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$flux = isset( $data['rateFluctuations'] ) && is_array( $data['rateFluctuations'] ) ? $data['rateFluctuations'] : array();
		if ( empty( $flux ) ) {
			return esc_html( $atts['default'] );
		}

		$code = strtoupper( trim( $atts['code'] ) );
		if ( '' === $code ) {
			// Default to the first (or only) requested code.
			$keys = array_keys( $flux );
			$code = reset( $keys );
		}

		if ( ! isset( $flux[ $code ] ) ) {
			return esc_html( $atts['default'] );
		}

		$value = $this->dig( $flux[ $code ], trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	public function sc_currency_name( $atts ) {
		$atts = shortcode_atts(
			array(
				'code'    => '',
				'default' => '',
			),
			$atts,
			'apifreaks_currency_name'
		);

		$code = strtoupper( trim( $atts['code'] ) );
		if ( '' === $code ) {
			return esc_html( $atts['default'] );
		}

		$data = apifreaks_client()->currency_symbols();
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$map = isset( $data['currencySymbols'] ) && is_array( $data['currencySymbols'] ) ? $data['currencySymbols'] : array();
		return isset( $map[ $code ] ) ? esc_html( $this->stringify( $map[ $code ] ) ) : esc_html( $atts['default'] );
	}

	/* ---------------------------------------------------------------------
	 * Weather (forecast / historical / marine / air-quality / flood)
	 * ------------------------------------------------------------------- */

	/**
	 * Build location args, defaulting to the visitor when nothing is set.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	protected function location_args( $atts ) {
		$args = array(
			'location' => isset( $atts['location'] ) ? $atts['location'] : '',
			'lat'      => isset( $atts['lat'] ) ? $atts['lat'] : '',
			'long'     => isset( $atts['long'] ) ? $atts['long'] : '',
			'ip'       => isset( $atts['ip'] ) ? $atts['ip'] : '',
		);
		if ( '' === $args['location'] && '' === $args['lat'] && '' === $args['ip'] ) {
			$args['ip'] = apifreaks_client()->visitor_ip();
		}
		return $args;
	}

	/**
	 * Pick a date-keyed entry from a container, or the first available.
	 *
	 * @param mixed  $container Date-keyed map.
	 * @param string $date      Requested date or ''.
	 * @return array|null
	 */
	protected function pick_dated( $container, $date ) {
		if ( ! is_array( $container ) ) {
			return null;
		}
		if ( '' !== $date && isset( $container[ $date ] ) && is_array( $container[ $date ] ) ) {
			return $container[ $date ];
		}
		foreach ( $container as $entry ) {
			if ( is_array( $entry ) ) {
				return $entry;
			}
		}
		return null;
	}

	public function sc_weather_forecast( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'date'     => '',
				'field'    => 'temperature_2m_max',
				'default'  => '',
			),
			$atts,
			'apifreaks_weather_forecast'
		);

		$args = $this->location_args( $atts );
		if ( '' !== $atts['date'] ) {
			$args['startDate'] = $atts['date'];
			$args['endDate']   = $atts['date'];
		}

		$data = apifreaks_client()->weather_forecast( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$day   = $this->pick_dated( isset( $data['forecast'] ) ? $data['forecast'] : null, $atts['date'] );
		$value = null === $day ? null : $this->dig( $day, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	public function sc_weather_historical( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'date'     => '',
				'field'    => 'temperature_2m_max',
				'default'  => '',
			),
			$atts,
			'apifreaks_weather_historical'
		);

		if ( '' === trim( $atts['date'] ) ) {
			return esc_html( $atts['default'] );
		}

		$args         = $this->location_args( $atts );
		$args['date'] = $atts['date'];

		$data = apifreaks_client()->weather_historical( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$day   = $this->pick_dated( isset( $data['historical'] ) ? $data['historical'] : null, $atts['date'] );
		$value = null === $day ? null : $this->dig( $day, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	public function sc_weather_marine( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'field'    => 'wave_height',
				'default'  => '',
			),
			$atts,
			'apifreaks_weather_marine'
		);

		$data = apifreaks_client()->weather_marine( $this->location_args( $atts ) );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = trim( $atts['field'] );
		$value = $this->dig( $data, $field );
		if ( null === $value ) {
			$value = $this->dig( $data, 'current.' . $field );
		}
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	public function sc_air_quality( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'field'    => 'us_aqi',
				'default'  => '',
			),
			$atts,
			'apifreaks_air_quality'
		);

		$data = apifreaks_client()->weather_air_quality( $this->location_args( $atts ) );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$field = trim( $atts['field'] );
		$value = $this->dig( $data, $field );
		if ( null === $value ) {
			$value = $this->dig( $data, 'current.' . $field );
		}
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	public function sc_weather_flood( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'lat'      => '',
				'long'     => '',
				'ip'       => '',
				'start'    => '',
				'end'      => '',
				'field'    => 'river_discharge',
				'default'  => '',
			),
			$atts,
			'apifreaks_weather_flood'
		);

		$args           = $this->location_args( $atts );
		$args['startDate'] = '' !== $atts['start'] ? $atts['start'] : gmdate( 'Y-m-d' );
		$args['endDate']   = '' !== $atts['end'] ? $atts['end'] : $args['startDate'];

		$data = apifreaks_client()->weather_flood( $args );
		if ( is_wp_error( $data ) ) {
			return $this->soft_error( $data, $atts['default'] );
		}

		$day   = $this->pick_dated( isset( $data['forecast'] ) ? $data['forecast'] : null, $atts['start'] );
		$value = null === $day ? null : $this->dig( $day, trim( $atts['field'] ) );
		return null === $value ? esc_html( $atts['default'] ) : esc_html( $this->stringify( $value ) );
	}

	/**
	 * Is $data a plain sequential list?
	 *
	 * @param mixed $data Value.
	 * @return bool
	 */
	protected function is_list( $data ) {
		return is_array( $data ) && ( array() === $data || array_keys( $data ) === range( 0, count( $data ) - 1 ) );
	}

	/**
	 * Find the first list of items inside a response.
	 *
	 * @param mixed $data Decoded response.
	 * @return array
	 */
	protected function first_list( $data ) {
		if ( $this->is_list( $data ) ) {
			return $data;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( $this->is_list( $value ) && ! empty( $value ) ) {
					return $value;
				}
			}
		}
		return array();
	}
}
