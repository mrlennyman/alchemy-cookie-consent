(function () {
	'use strict';

	var COOKIE_NAME = 'alchemy_consent';
	var banner = document.getElementById( 'alchemy-consent-banner' );
	var revisitBtn = document.getElementById( 'alchemy-consent-revisit' );

	if ( ! banner ) {
		return;
	}

	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(^| )' + name + '=([^;]+)' ) );
		return match ? decodeURIComponent( match[ 2 ] ) : null;
	}

	function setCookie( name, value, days ) {
		var d = new Date();
		d.setTime( d.getTime() + days * 24 * 60 * 60 * 1000 );
		document.cookie = name + '=' + encodeURIComponent( value ) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
	}

	function saveConsent( categories, source, revealButton ) {
		setCookie( COOKIE_NAME, JSON.stringify( categories ), 180 );

		// Same event shape as the early <head> push in output_datalayer_bridge()
		// — a GTM trigger listening for either event sees a consistent shape
		// whether it's the pre-existing choice or a fresh one just made.
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( {
			event: 'alchemy_consent_update',
			alchemy_consent_necessary: true,
			alchemy_consent_analytics: categories.indexOf( 'analytics' ) !== -1,
			alchemy_consent_marketing: categories.indexOf( 'marketing' ) !== -1,
		} );

		var body = new URLSearchParams();
		body.append( 'action', 'alchemy_consent_save' );
		body.append( 'nonce', waConsentData.nonce );
		body.append( 'page_url', window.location.href );
		body.append( 'source', source || 'explicit' );
		categories.forEach( function ( c ) {
			body.append( 'categories[]', c );
		} );

		fetch( waConsentData.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		} );

		banner.classList.add( 'alchemy-consent-hidden' );
		// Exempt-tier auto-accept passes revealButton=false — no visible
		// affordance at all for regions with no consent requirement,
		// distinct from Light, which still surfaces the opt-out button.
		if ( revealButton !== false ) {
			revisitBtn.hidden = false;
		}
	}

	function openBanner() {
		banner.classList.remove( 'alchemy-consent-hidden' );
	}

	/**
	 * Reads the visitor's country from Cloudflare's public trace endpoint —
	 * deliberately Cloudflare's own domain, not the site's own, so this
	 * works identically whether or not this particular site is on
	 * Cloudflare. A short timeout plus a rejected/failed promise both
	 * resolve to null, and the caller treats null as "assume Strict" —
	 * detection failure must never accidentally relax the banner.
	 */
	function detectCountry() {
		var controller = new AbortController();
		var timeout = setTimeout( function () { controller.abort(); }, 2500 );

		return fetch( 'https://www.cloudflare.com/cdn-cgi/trace', { signal: controller.signal } )
			.then( function ( res ) { return res.text(); } )
			.then( function ( text ) {
				clearTimeout( timeout );
				var match = text.match( /loc=([A-Z]{2})/ );
				return match ? match[ 1 ] : null;
			} )
			.catch( function () {
				clearTimeout( timeout );
				return null;
			} );
	}

	function autoAccept( source, revealButton ) {
		var cats = [ 'necessary' ];
		if ( waConsentData.settings.categories_enabled.analytics ) {
			cats.push( 'analytics' );
		}
		if ( waConsentData.settings.categories_enabled.marketing ) {
			cats.push( 'marketing' );
		}
		saveConsent( cats, source, revealButton );
	}

	function init() {
		// An existing choice always wins, regardless of geo settings — we
		// never re-evaluate or override a visitor's own past decision.
		if ( getCookie( COOKIE_NAME ) ) {
			revisitBtn.hidden = false;
			return;
		}

		if ( ! waConsentData.settings.geo_targeting_enabled ) {
			openBanner();
			return;
		}

		detectCountry().then( function ( country ) {
			var strict = waConsentData.settings.strict_countries || [];
			var light  = waConsentData.settings.light_countries || [];

			// null (detection failed) or a recognised Strict-list country
			// both fall through to the normal blocking banner — the only
			// paths that skip it are confirmed Light or Exempt countries.
			if ( ! country || strict.indexOf( country ) !== -1 ) {
				openBanner(); // Strict — opt-in required.
			} else if ( light.indexOf( country ) !== -1 ) {
				autoAccept( 'geo-light', true ); // Light — auto-granted, opt-out button shown.
			} else {
				autoAccept( 'geo-exempt', false ); // Exempt — auto-granted, nothing shown at all.
			}
		} );
	}

	init();

	document.getElementById( 'alchemy-consent-accept' ).addEventListener( 'click', function () {
		saveConsent( [ 'necessary', 'analytics', 'marketing' ], 'explicit' );
	} );

	document.getElementById( 'alchemy-consent-reject' ).addEventListener( 'click', function () {
		saveConsent( [ 'necessary' ], 'explicit' );
	} );

	document.getElementById( 'alchemy-consent-customize' ).addEventListener( 'click', function () {
		banner.querySelector( '.alchemy-consent-categories' ).hidden = false;
		document.getElementById( 'alchemy-consent-customize' ).hidden = true;
		document.getElementById( 'alchemy-consent-reject' ).hidden = true;
		document.getElementById( 'alchemy-consent-accept' ).hidden = true;
		document.getElementById( 'alchemy-consent-save' ).hidden = false;
	} );

	document.getElementById( 'alchemy-consent-save' ).addEventListener( 'click', function () {
		var cats = [ 'necessary' ];
		var analytics = document.getElementById( 'alchemy-consent-analytics' );
		var marketing = document.getElementById( 'alchemy-consent-marketing' );
		if ( analytics && analytics.checked ) {
			cats.push( 'analytics' );
		}
		if ( marketing && marketing.checked ) {
			cats.push( 'marketing' );
		}
		saveConsent( cats, 'explicit' );
	} );

	revisitBtn.addEventListener( 'click', openBanner );

	// Lets the [alchemy_consent_settings_link] shortcode (or any custom link)
	// reopen the banner without duplicating this logic.
	document.addEventListener( 'alchemy-consent-reopen', openBanner );
} )();
