/**
 * APIFreaks admin settings behaviour.
 *
 * Localized data is provided via wp_localize_script as `apifreaksAdmin`:
 *   - ajaxUrl : admin-ajax.php endpoint
 *   - nonce   : verification nonce for the "apifreaks_test" action
 *   - i18n    : translated UI strings
 */
( function () {
	'use strict';

	var cfg = window.apifreaksAdmin || {};
	var i18n = cfg.i18n || {};

	function ready() {
		var testBtn = document.getElementById( 'apifreaks-test' );
		var result  = document.getElementById( 'apifreaks-test-result' );
		var toggle  = document.getElementById( 'apifreaks-toggle-key' );
		var keyEl   = document.getElementById( 'apifreaks_api_key' );

		if ( toggle && keyEl ) {
			toggle.addEventListener( 'click', function () {
				var show = keyEl.type === 'password';
				keyEl.type = show ? 'text' : 'password';
				toggle.textContent = show ? ( i18n.hide || 'Hide' ) : ( i18n.show || 'Show' );
			} );
		}

		if ( testBtn && result ) {
			testBtn.addEventListener( 'click', function () {
				result.textContent = i18n.testing || 'Testing…';
				result.className = 'apifreaks-test-result';

				var body = new FormData();
				body.append( 'action', 'apifreaks_test' );
				body.append( 'nonce', cfg.nonce || '' );

				fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( json ) {
						if ( json.success ) {
							result.classList.add( 'ok' );
							result.textContent = json.data.message;
						} else {
							result.classList.add( 'fail' );
							result.textContent = ( json.data && json.data.message ) ? json.data.message : ( i18n.failed || 'Error' );
						}
					} )
					.catch( function () {
						result.classList.add( 'fail' );
						result.textContent = i18n.failed || 'Request failed.';
					} );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
