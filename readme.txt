=== APIFreaks ===
Contributors: afraz33
Tags: geolocation, geotargeting, timezone, geocoding, woocommerce
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

APIFreaks IP geolocation, timezone, astronomy, weather, geocoding, ZIP and GeoDB data via shortcodes, conditional content, and WooCommerce prices.

== Description ==

This plugin lets non-developers use the [APIFreaks](https://apifreaks.com/) API hub through a settings page and shortcodes — no code required.

Features:

* **Visitor IP intelligence** — show a visitor's country, city, region, currency, timezone, ISP, and (on supported plans) proxy / Tor / threat signals.
* **Conditional content** — show or hide blocks based on the visitor's location or security flags.
* **Timezone** — current time and timezone details for any place, coordinates, IP, or airport code, plus timezone-to-timezone conversion.
* **Astronomy** — sunrise, sunset, moon phase, and celestial positions for any location and date.
* **Weather** — current conditions, daily forecast (up to 16 days), historical weather, marine conditions, air quality, and flood/river-discharge forecasts.
* **User-Agent parsing** — browser, OS, and device details from a User-Agent string.
* **Forward & reverse geocoding** — addresses to coordinates and back.
* **ZIP / postal codes** — look up a code, or search codes by city, region, or radius.
* **GeoDB** — countries, regions, subregions, administrative units, and cities.
* **Currency** — live and historical conversion, latest/historical rates, fluctuation over a period, and currency names.
* **WooCommerce currency display** — show approximate prices in each visitor's local currency (display only; orders are still charged in your store currency).

All responses are cached in transients to keep API usage low. Cloudflare's CF-Connecting-IP header is supported.

== Installation ==

1. Upload the `apifreaks` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin.
3. Go to **APIFreaks** in the admin menu and paste your API key. Get a free key at https://apifreaks.com/signup.
4. Click **Test connection** to confirm the key works.
5. Add shortcodes to any post, page, or widget.

== Shortcodes ==

`[apifreaks_ip field="country"]`
`[apifreaks_if country_code="US,CA" logic="OR"]...[/apifreaks_if]`
`[apifreaks_if_not country="Germany"]...[/apifreaks_if_not]`
`[apifreaks_timezone location="Tokyo, JP" field="date_time"]`
`[apifreaks_timezone_convert time="2026-01-01 09:00" tz_from="America/New_York" tz_to="Asia/Karachi"]`
`[apifreaks_astronomy location="Paris, FR" field="sunrise"]`
`[apifreaks_weather location="Berlin, DE" field="temperature"]`
`[apifreaks_weather_forecast location="Berlin, DE" date="2026-01-05" field="temperature_2m_max"]`
`[apifreaks_weather_historical location="Berlin, DE" date="2025-12-01" field="temperature_2m_max"]`
`[apifreaks_air_quality location="Delhi, IN" field="us_aqi"]`
`[apifreaks_weather_marine lat="43.3" long="5.4" field="wave_height"]`
`[apifreaks_weather_flood location="Lahore, PK" field="river_discharge"]`
`[apifreaks_user_agent field="name"]`
`[apifreaks_geocode query="Wembley Stadium, London" field="lat"]`
`[apifreaks_reverse_geocode lat="40.748" lon="-73.985" field="full_address"]`
`[apifreaks_zipcode code="10001" country="US" field="city"]`
`[apifreaks_zipcode_search mode="city" city="Lahore" country="PK"]`
`[apifreaks_geodb resource="countries" field="country_name"]`
`[apifreaks_currency_convert from="USD" to="EUR" amount="49.99" decimals="2"]`
`[apifreaks_currency_rate base="USD" symbols="EUR,GBP" field="EUR"]`
`[apifreaks_currency_fluctuation base="USD" symbols="EUR" start="2025-12-01" end="2025-12-31" code="EUR"]`
`[apifreaks_currency_name code="EUR"]`

Every shortcode accepts `default="..."` for fallback text. The full field list is shown on the settings screen.

== External services ==

This plugin is an interface to the **APIFreaks API**, a third-party service, and requires an APIFreaks API key to work. Without contacting this service the plugin cannot determine visitor location, time, weather, or the other data it displays.

What it connects to:

* Service: APIFreaks API — https://api.apifreaks.com
* Provider: APIFreaks — https://apifreaks.com

When requests are made:

* When a page, post, or widget renders one of the plugin's shortcodes.
* When the WooCommerce currency-display option is enabled and a product price is shown.
* When you click "Test connection" on the settings screen.

What data is sent:

* Your APIFreaks API key (sent as the `X-apiKey` request header on every call).
* For visitor-based features (IP intelligence, conditional content, WooCommerce prices, weather/timezone that default to the visitor), the visitor's IP address.
* Any values you pass to a shortcode, for example an address given to `[apifreaks_geocode]`, coordinates given to `[apifreaks_reverse_geocode]`, a location given to `[apifreaks_weather]`, or a User-Agent string.

Responses are cached in WordPress transients for the lifetime you set on the settings page (default 24 hours) to minimise the number of requests.

Please review the APIFreaks Terms of Service and Privacy Policy before use:

* Terms of Service: https://apifreaks.com/terms
* Privacy Policy: https://apifreaks.com/privacy

== Privacy ==

When visitor-based features are used, this plugin transmits the visitor's IP address to APIFreaks in order to return location, timezone, and weather data. IP addresses can be considered personal data under regulations such as the GDPR. If you operate in a region with such regulations, disclose this processing in your own site's privacy policy and obtain consent where required. The plugin itself stores only cached API responses in transients and your settings in the options table; it does not create user profiles or share data with any party other than APIFreaks.

== Frequently Asked Questions ==

= Does it work behind Cloudflare? =
Yes. Enable the Cloudflare option in settings to trust the CF-Connecting-IP header.

= Will WooCommerce charge customers in their local currency? =
No. The WooCommerce feature is display only. Orders are charged in your configured store currency; the localized figure is an approximate reference.

= Does it cache API responses? =
Yes, in WordPress transients. You can set the lifetime in hours on the settings page.

= What happens without a valid API key? =
Shortcodes fall back to their `default` text for visitors, and administrators see a short diagnostic note.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
