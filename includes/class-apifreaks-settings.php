<?php
/**
 * Admin settings screen for APIFreaks.
 *
 * @package APIFreaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APIFreaks_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_apifreaks_test', array( $this, 'ajax_test' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'APIFreaks', 'apifreaks' ),
			__( 'APIFreaks', 'apifreaks' ),
			'manage_options',
			'apifreaks',
			array( $this, 'render' ),
			'dashicons-admin-site-alt3',
			80
		);
	}

	public function register() {
		register_setting(
			'apifreaks_group',
			APIFREAKS_OPTION,
			array( $this, 'sanitize' )
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_apifreaks' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'apifreaks-admin', APIFREAKS_URL . 'assets/admin.css', array(), APIFREAKS_VERSION );
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = array();

		$out['api_key']          = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$out['cache_hours']      = isset( $input['cache_hours'] ) ? max( 1, absint( $input['cache_hours'] ) ) : 24;
		$out['trust_cloudflare'] = ! empty( $input['trust_cloudflare'] ) ? 1 : 0;
		$out['woo_enabled']      = ! empty( $input['woo_enabled'] ) ? 1 : 0;
		$out['woo_mode']         = ( isset( $input['woo_mode'] ) && 'replace' === $input['woo_mode'] ) ? 'replace' : 'append';
		$out['woo_base']         = isset( $input['woo_base'] ) ? strtoupper( sanitize_text_field( $input['woo_base'] ) ) : 'USD';

		return $out;
	}

	/**
	 * AJAX: quick "does my key work" test.
	 */
	public function ajax_test() {
		check_ajax_referer( 'apifreaks_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apifreaks' ) ) );
		}

		$data = apifreaks_client()->geolocation( '8.8.8.8', 'location,currency' );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		$country  = isset( $data['location']['country_name'] ) ? $data['location']['country_name'] : '?';
		$currency = isset( $data['currency']['code'] ) ? $data['currency']['code'] : '?';

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: country, 2: currency code. */
					__( 'Connected. Test lookup for 8.8.8.8 resolved to %1$s (%2$s).', 'apifreaks' ),
					$country,
					$currency
				),
			)
		);
	}

	public function render() {
		$opts = wp_parse_args(
			(array) get_option( APIFREAKS_OPTION, array() ),
			array(
				'api_key'          => '',
				'cache_hours'      => 24,
				'trust_cloudflare' => 1,
				'woo_enabled'      => 0,
				'woo_mode'         => 'append',
				'woo_base'         => 'USD',
			)
		);
		?>
		<div class="wrap apifreaks-wrap">
			<h1 class="apifreaks-title">
				<img src="<?php echo esc_url( APIFREAKS_URL . 'assets/logo.png' ); ?>" alt="" class="apifreaks-logo" width="32" height="32" />
				<?php esc_html_e( 'APIFreaks – IP, Geo &amp; Location Toolkit', 'apifreaks' ); ?>
			</h1>
			<p class="description">
				<?php
				printf(
					/* translators: %s: signup URL. */
					wp_kses_post( __( 'Need a key? <a href="%s" target="_blank" rel="noopener">Get a free API key from APIFreaks</a>, then paste it below.', 'apifreaks' ) ),
					esc_url( 'https://apifreaks.com/signup' )
				);
				?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'apifreaks_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'API connection', 'apifreaks' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="apifreaks_api_key"><?php esc_html_e( 'API key', 'apifreaks' ); ?></label></th>
						<td>
							<input type="password" id="apifreaks_api_key" class="regular-text"
								name="<?php echo esc_attr( APIFREAKS_OPTION ); ?>[api_key]"
								value="<?php echo esc_attr( $opts['api_key'] ); ?>" autocomplete="off" />
							<button type="button" class="button" id="apifreaks-toggle-key"><?php esc_html_e( 'Show', 'apifreaks' ); ?></button>
							<button type="button" class="button button-secondary" id="apifreaks-test"><?php esc_html_e( 'Test connection', 'apifreaks' ); ?></button>
							<span id="apifreaks-test-result" class="apifreaks-test-result"></span>
							<p class="description"><?php esc_html_e( 'Sent as the X-apiKey header on every request.', 'apifreaks' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="apifreaks_cache_hours"><?php esc_html_e( 'Cache lifetime (hours)', 'apifreaks' ); ?></label></th>
						<td>
							<input type="number" min="1" max="720" id="apifreaks_cache_hours"
								name="<?php echo esc_attr( APIFREAKS_OPTION ); ?>[cache_hours]"
								value="<?php echo esc_attr( $opts['cache_hours'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'How long each API response is stored in a transient. Higher values mean fewer API calls.', 'apifreaks' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloudflare', 'apifreaks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( APIFREAKS_OPTION ); ?>[trust_cloudflare]"
									<?php checked( $opts['trust_cloudflare'], 1 ); ?> />
								<?php esc_html_e( 'Trust the CF-Connecting-IP header when detecting visitor IPs', 'apifreaks' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Enable this only if your site sits behind Cloudflare.', 'apifreaks' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'WooCommerce currency display', 'apifreaks' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Localized prices', 'apifreaks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( APIFREAKS_OPTION ); ?>[woo_enabled]"
									<?php checked( $opts['woo_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Show approximate prices in each visitor\'s local currency', 'apifreaks' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Detects the visitor currency from their IP and converts using live rates. Display only — orders are still charged in your store currency.', 'apifreaks' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="apifreaks_woo_mode"><?php esc_html_e( 'Display mode', 'apifreaks' ); ?></label></th>
						<td>
							<select id="apifreaks_woo_mode" name="<?php echo esc_attr( APIFREAKS_OPTION ); ?>[woo_mode]">
								<option value="append" <?php selected( $opts['woo_mode'], 'append' ); ?>><?php esc_html_e( 'Append next to the store price', 'apifreaks' ); ?></option>
								<option value="replace" <?php selected( $opts['woo_mode'], 'replace' ); ?>><?php esc_html_e( 'Replace the displayed price', 'apifreaks' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php $this->render_reference(); ?>
		</div>

		<script>
		( function () {
			var testBtn = document.getElementById( 'apifreaks-test' );
			var result  = document.getElementById( 'apifreaks-test-result' );
			var toggle  = document.getElementById( 'apifreaks-toggle-key' );
			var keyEl   = document.getElementById( 'apifreaks_api_key' );

			if ( toggle && keyEl ) {
				toggle.addEventListener( 'click', function () {
					var show = keyEl.type === 'password';
					keyEl.type = show ? 'text' : 'password';
					toggle.textContent = show ? '<?php echo esc_js( __( 'Hide', 'apifreaks' ) ); ?>' : '<?php echo esc_js( __( 'Show', 'apifreaks' ) ); ?>';
				} );
			}

			if ( testBtn ) {
				testBtn.addEventListener( 'click', function () {
					result.textContent = '<?php echo esc_js( __( 'Testing…', 'apifreaks' ) ); ?>';
					result.className = 'apifreaks-test-result';
					var body = new FormData();
					body.append( 'action', 'apifreaks_test' );
					body.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'apifreaks_test' ) ); ?>' );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( json ) {
							if ( json.success ) {
								result.classList.add( 'ok' );
								result.textContent = json.data.message;
							} else {
								result.classList.add( 'fail' );
								result.textContent = json.data && json.data.message ? json.data.message : 'Error';
							}
						} )
						.catch( function () {
							result.classList.add( 'fail' );
							result.textContent = '<?php echo esc_js( __( 'Request failed.', 'apifreaks' ) ); ?>';
						} );
				} );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Shortcode cheat-sheet.
	 */
	protected function render_reference() {
		$rows = array(
			array( '[apifreaks_ip field="country"]', __( 'Visitor country name. Try field = city, state, country_code, currency, currency_symbol, timezone, calling_code, latitude, longitude, is_proxy, is_tor, threat_score, flag, emoji.', 'apifreaks' ) ),
			array( '[apifreaks_if country_code="US,CA" logic="OR"]…[/apifreaks_if]', __( 'Show the wrapped content only to matching visitors. Attributes: country, country_code, state, city, continent, is_proxy, is_tor, is_anonymous, is_bot, is_cloud_provider. logic = AND (default) or OR.', 'apifreaks' ) ),
			array( '[apifreaks_if_not country="Germany"]…[/apifreaks_if_not]', __( 'Hide the wrapped content from matching visitors.', 'apifreaks' ) ),
			array( '[apifreaks_timezone location="Tokyo, JP" field="date_time"]', __( 'Timezone / current time. Selectors: tz, location, lat + long, ip, iata. field defaults to date_time; try name, offset, date, is_dst.', 'apifreaks' ) ),
			array( '[apifreaks_astronomy location="Paris, FR" field="sunrise"]', __( 'Sun & moon data. field: sunrise, sunset, solar_noon, day_length, moon_phase, moonrise, moonset, moon_illumination_percentage.', 'apifreaks' ) ),
			array( '[apifreaks_user_agent field="name"]', __( 'Parse the visitor\'s browser/device. field: name, version, type, device.name, device.brand, operating_system.name.', 'apifreaks' ) ),
			array( '[apifreaks_geocode query="Wembley Stadium, London" field="lat"]', __( 'Forward geocoding. field: lat, lon, city, state, country, postcode, full_address.', 'apifreaks' ) ),
			array( '[apifreaks_reverse_geocode lat="40.748" lon="-73.985" field="full_address"]', __( 'Reverse geocoding. field: city, state, postcode, country, full_address, name.', 'apifreaks' ) ),
			array( '[apifreaks_zipcode code="10001" country="US" field="city"]', __( 'ZIP / postal lookup. field: city, region, locality, latitude, longitude.', 'apifreaks' ) ),
			array( '[apifreaks_zipcode_search mode="city" city="Lahore" country="PK"]', __( 'Search ZIP / postal codes. mode: city (city + country), region (region + country), or radius (code or lat + long, radius, unit). Returns a list of codes.', 'apifreaks' ) ),
			array( '[apifreaks_geodb resource="countries" field="country_name"]', __( 'GeoDB. resource: countries, country, regions, subregions, admin-levels, admin-units, cities. Pass country / region / admin_unit as needed. Omit field for a count.', 'apifreaks' ) ),
			array( '[apifreaks_weather location="Berlin, DE" field="temperature"]', __( 'Current weather. field: temperature, feels_like, humidity, wind_speed, precipitation, cloud_cover, pressure, description. Defaults to the visitor when no location is given.', 'apifreaks' ) ),
			array( '[apifreaks_weather_forecast location="Berlin, DE" date="2026-01-05" field="temperature_2m_max"]', __( 'Daily forecast (up to 16 days). field: temperature_2m_max, temperature_2m_min, weather_code, apparent_temperature_max. Omit date for the first day.', 'apifreaks' ) ),
			array( '[apifreaks_weather_historical location="Berlin, DE" date="2025-12-01" field="temperature_2m_max"]', __( 'Past weather for a date. Same daily fields as the forecast shortcode.', 'apifreaks' ) ),
			array( '[apifreaks_air_quality location="Delhi, IN" field="us_aqi"]', __( 'Air quality. field: us_aqi, european_aqi, pm10, pm2_5, ozone, nitrogen_dioxide, uv_index. Defaults to the visitor.', 'apifreaks' ) ),
			array( '[apifreaks_weather_marine lat="43.3" long="5.4" field="wave_height"]', __( 'Marine conditions. field: wave_height, wave_period, sea_surface_temperature, ocean_current_velocity.', 'apifreaks' ) ),
			array( '[apifreaks_weather_flood location="Lahore, PK" field="river_discharge"]', __( 'Flood / river-discharge forecast. Optional start and end dates (default today).', 'apifreaks' ) ),
			array( '[apifreaks_currency_convert from="USD" to="EUR" amount="49.99" decimals="2"]', __( 'Convert an amount between currencies (live rates). Add date="YYYY-MM-DD" for a historical rate. field: convertedAmount (default), rate.', 'apifreaks' ) ),
			array( '[apifreaks_currency_rate base="USD" symbols="EUR,GBP" field="EUR" decimals="4"]', __( 'Latest exchange rate(s). Give field="EUR" for one rate, or omit it to list all requested rates. Add date for a historical rate.', 'apifreaks' ) ),
			array( '[apifreaks_currency_fluctuation base="USD" symbols="EUR" start="2025-12-01" end="2025-12-31" code="EUR" field="percentChange"]', __( 'Rate change over a period. field: percentChange, change, startRate, endRate.', 'apifreaks' ) ),
			array( '[apifreaks_currency_name code="EUR"]', __( 'Full name of a currency code (e.g. Euro).', 'apifreaks' ) ),
			array( '[apifreaks_timezone_convert time="2026-01-01 09:00" tz_from="America/New_York" tz_to="Asia/Karachi"]', __( 'Convert a time between two timezones or locations. field: converted_time (default), original_time, diff_hour.', 'apifreaks' ) ),
		);
		?>
		<h2 class="title"><?php esc_html_e( 'Shortcode reference', 'apifreaks' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Every shortcode accepts default="…" for fallback text when data is unavailable. Add separator="…" to list-producing shortcodes.', 'apifreaks' ); ?></p>
		<table class="widefat striped apifreaks-reference">
			<thead>
				<tr>
					<th style="width:42%;"><?php esc_html_e( 'Shortcode', 'apifreaks' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'apifreaks' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row[0] ); ?></code></td>
						<td><?php echo esc_html( $row[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
