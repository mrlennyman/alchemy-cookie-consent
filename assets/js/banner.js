(function () {
	'use strict';

	var COOKIE_NAME = 'alchemy_cookie_consent';
	var HIGH_RISK_COOKIE_NAME = 'alchemy_cookie_consent_highrisk';
	var banner = document.getElementById( 'alchemy-cookie-consent-banner' );
	var revisitBtn = document.getElementById( 'alchemy-cookie-consent-revisit' );
	var highRiskBanner = document.getElementById( 'alchemy-cookie-consent-highrisk-banner' );

	if ( ! banner ) {
		return;
	}

	// Kept in sync with the cookie-read regex in output_datalayer_bridge()'s
	// inline <head> script — that snippet has to stay separate from this
	// enqueued file (it must run before this file loads), so the two can't
	// share this function, but must parse the cookie identically.
	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(^| )' + name + '=([^;]+)' ) );
		return match ? decodeURIComponent( match[ 2 ] ) : null;
	}

	function setCookie( name, value, days ) {
		var d = new Date();
		d.setTime( d.getTime() + days * 24 * 60 * 60 * 1000 );
		document.cookie = name + '=' + encodeURIComponent( value ) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
	}

	/**
	 * Reads back the stored consent as { categories, source }. The cookie
	 * used to hold a bare categories array (pre-1.6.2) — that shape is
	 * still accepted so visitors who already consented under the old
	 * version aren't treated as having no consent after an upgrade.
	 */
	function getConsentState() {
		var raw = getCookie( COOKIE_NAME );
		if ( ! raw ) {
			return null;
		}
		try {
			var parsed = JSON.parse( raw );
			return Array.isArray( parsed ) ? { categories: parsed, source: 'explicit' } : parsed;
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Global Privacy Control (navigator.globalPrivacyControl) is a browser/
	 * extension-level signal meaning "treat this as an opt-out of sale or
	 * sharing of my personal information" — CPPA guidance treats it as a
	 * valid CCPA/CPRA opt-out request that a business must honor without
	 * requiring a separate click. It's an opt-out signal, not opt-in
	 * consent, so it only ever suppresses marketing (the plugin's closest
	 * equivalent to "sale/sharing") — it never grants analytics or
	 * anything else on its own; Strict-tier visitors still see the normal
	 * blocking banner and must make an actual choice.
	 */
	function gpcOptOut() {
		return true === navigator.globalPrivacyControl;
	}

	function enabledCategories() {
		var cats = [ 'necessary' ];
		if ( alchemyCookieConsentData.settings.categories_enabled.analytics ) {
			cats.push( 'analytics' );
		}
		if ( alchemyCookieConsentData.settings.categories_enabled.marketing && ! gpcOptOut() ) {
			cats.push( 'marketing' );
		}
		return cats;
	}

	function saveConsent( categories, source, revealButton ) {
		setCookie( COOKIE_NAME, JSON.stringify( { categories: categories, source: source || 'explicit' } ), 180 );

		// Same event shape as the early <head> push in output_datalayer_bridge()
		// — a GTM trigger listening for either event sees a consistent shape
		// whether it's the pre-existing choice or a fresh one just made.
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( {
			event: 'alchemy_cookie_consent_update',
			alchemy_cookie_consent_necessary: true,
			alchemy_cookie_consent_analytics: categories.indexOf( 'analytics' ) !== -1,
			alchemy_cookie_consent_marketing: categories.indexOf( 'marketing' ) !== -1,
		} );

		var body = new URLSearchParams();
		body.append( 'action', 'alchemy_cookie_consent_save' );
		body.append( 'nonce', alchemyCookieConsentData.nonce );
		body.append( 'page_url', window.location.href );
		body.append( 'source', source || 'explicit' );
		body.append( 'scope', 'general' );
		categories.forEach( function ( c ) {
			body.append( 'categories[]', c );
		} );

		fetch( alchemyCookieConsentData.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		} );

		banner.classList.add( 'alchemy-cookie-consent-hidden' );
		// Exempt-tier auto-accept passes revealButton=false — no visible
		// affordance at all for regions with no consent requirement,
		// distinct from Light, which still surfaces the opt-out button.
		if ( revealButton !== false ) {
			revisitBtn.hidden = false;
		}

		// Checked only once the main banner is out of the way, so a visitor
		// is never shown two overlapping prompts at once — see
		// maybeShowHighRiskPrompt() for why this is a separate decision
		// from the categories above.
		maybeShowHighRiskPrompt();
	}

	/**
	 * Session-recording and chat-type tools get their own always-ask
	 * prompt, independent of Strict/Light/Exempt tier — unlike general
	 * Analytics/Marketing, this isn't about where the visitor is, it's
	 * about whether the tool captured anything before they said yes. It
	 * only appears if the Cookie List has at least one row flagged
	 * High-risk, and only once (governed by its own cookie, separate from
	 * the general consent cookie).
	 */
	function maybeShowHighRiskPrompt() {
		if ( ! highRiskBanner ) {
			return;
		}
		if ( ! alchemyCookieConsentData.settings.has_high_risk ) {
			return;
		}
		if ( getCookie( HIGH_RISK_COOKIE_NAME ) ) {
			return;
		}
		var notices = alchemyCookieConsentData.settings.high_risk_notices || [];
		if ( ! notices.length ) {
			return;
		}
		var msg = document.getElementById( 'alchemy-cookie-consent-highrisk-standalone-message' );
		if ( msg ) {
			msg.textContent = notices.join( ' ' );
		}
		highRiskBanner.classList.remove( 'alchemy-cookie-consent-hidden' );
	}

	function saveHighRiskConsent( granted ) {
		setCookie( HIGH_RISK_COOKIE_NAME, granted ? 'granted' : 'declined', 180 );

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( {
			event: 'alchemy_cookie_consent_highrisk_update',
			alchemy_cookie_consent_highrisk: granted,
		} );

		var body = new URLSearchParams();
		body.append( 'action', 'alchemy_cookie_consent_save' );
		body.append( 'nonce', alchemyCookieConsentData.nonce );
		body.append( 'page_url', window.location.href );
		body.append( 'source', 'explicit' );
		body.append( 'scope', 'highrisk' );
		if ( granted ) {
			body.append( 'categories[]', 'high_risk' );
		}

		fetch( alchemyCookieConsentData.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		} );

		highRiskBanner.classList.add( 'alchemy-cookie-consent-hidden' );
	}

	function openBanner() {
		banner.classList.remove( 'alchemy-cookie-consent-hidden' );
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
		saveConsent( enabledCategories(), source, revealButton );
	}

	function init() {
		// An existing choice always wins, regardless of geo settings — we
		// never re-evaluate or override a visitor's own past decision.
		var state = getConsentState();
		if ( state ) {
			// Exempt-tier consent shows no UI at all, including the revisit
			// button — that has to hold on every later pageview too, not
			// just the pageview where auto-accept first ran.
			revisitBtn.hidden = ( 'geo-exempt' === state.source );
			maybeShowHighRiskPrompt();
			return;
		}

		if ( ! alchemyCookieConsentData.settings.geo_targeting_enabled ) {
			openBanner();
			return;
		}

		detectCountry().then( function ( country ) {
			var strict = alchemyCookieConsentData.settings.strict_countries || [];
			var light  = alchemyCookieConsentData.settings.light_countries || [];

			// null (detection failed) or a recognised Strict-list country
			// both fall through to the normal blocking banner — the only
			// paths that skip it are confirmed Light or Exempt countries.
			if ( ! country || strict.indexOf( country ) !== -1 ) {
				openBanner(); // Strict — opt-in required regardless of GPC; an opt-out signal can't substitute for affirmative consent.
			} else if ( light.indexOf( country ) !== -1 ) {
				autoAccept( 'geo-light', true ); // Light — auto-granted (minus marketing if GPC is set), opt-out button shown.
			} else if ( gpcOptOut() ) {
				// Exempt tier normally shows nothing at all, but a visitor
				// who's actively sent an opt-out signal has expressed a
				// preference — honor it (enabledCategories() already
				// excludes marketing) and surface the button so they can
				// see/change it, rather than silently auto-granting with no
				// visible trace that a signal was even received.
				autoAccept( 'gpc', true );
			} else {
				autoAccept( 'geo-exempt', false ); // Exempt — auto-granted, nothing shown at all.
			}
		} );
	}

	init();

	document.getElementById( 'alchemy-cookie-consent-accept' ).addEventListener( 'click', function () {
		saveConsent( enabledCategories(), 'explicit' );
	} );

	document.getElementById( 'alchemy-cookie-consent-reject' ).addEventListener( 'click', function () {
		saveConsent( [ 'necessary' ], 'explicit' );
	} );

	// "Don't show again" — functionally identical to Reject (necessary-only,
	// nothing granted), since dismissing without an actual choice can never
	// safely be treated as "assume they're fine with tracking." Logged with
	// its own "dismissed" source for audit-trail clarity.
	document.getElementById( 'alchemy-cookie-consent-dismiss' ).addEventListener( 'click', function () {
		saveConsent( [ 'necessary' ], 'dismissed' );
	} );

	document.getElementById( 'alchemy-cookie-consent-customize' ).addEventListener( 'click', function () {
		banner.querySelector( '.alchemy-cookie-consent-categories' ).hidden = false;
		document.getElementById( 'alchemy-cookie-consent-customize' ).hidden = true;
		document.getElementById( 'alchemy-cookie-consent-reject' ).hidden = true;
		document.getElementById( 'alchemy-cookie-consent-accept' ).hidden = true;
		document.getElementById( 'alchemy-cookie-consent-save' ).hidden = false;
	} );

	document.getElementById( 'alchemy-cookie-consent-save' ).addEventListener( 'click', function () {
		var cats = [ 'necessary' ];
		var analytics = document.getElementById( 'alchemy-cookie-consent-analytics' );
		var marketing = document.getElementById( 'alchemy-cookie-consent-marketing' );
		if ( analytics && analytics.checked ) {
			cats.push( 'analytics' );
		}
		if ( marketing && marketing.checked ) {
			cats.push( 'marketing' );
		}
		saveConsent( cats, 'explicit' );
	} );

	revisitBtn.addEventListener( 'click', openBanner );

	// Lets the [alchemy_cookie_consent_settings_link] shortcode (or any custom link)
	// reopen the banner without duplicating this logic.
	document.addEventListener( 'alchemy-cookie-consent-reopen', openBanner );

	if ( highRiskBanner ) {
		document.getElementById( 'alchemy-cookie-consent-highrisk-accept' ).addEventListener( 'click', function () {
			saveHighRiskConsent( true );
		} );
		document.getElementById( 'alchemy-cookie-consent-highrisk-decline' ).addEventListener( 'click', function () {
			saveHighRiskConsent( false );
		} );
	}

	// [alchemy_privacy_choices] shortcode dispatches this — a one-click
	// "Do Not Sell or Share My Personal Information" opt-out rather than
	// reopening the full banner and asking the visitor to find the right
	// checkbox. Keeps whatever categories are already granted (including
	// an unrelated Customize choice made earlier) and only strips
	// marketing, since that's the plugin's closest equivalent to CPRA's
	// "sale/sharing" concept.
	document.addEventListener( 'alchemy-cookie-consent-optout', function () {
		var state = getConsentState();
		var current = state ? state.categories.slice() : enabledCategories();
		var idx = current.indexOf( 'marketing' );
		if ( idx !== -1 ) {
			current.splice( idx, 1 );
		}
		if ( current.indexOf( 'necessary' ) === -1 ) {
			current.unshift( 'necessary' );
		}
		saveConsent( current, 'explicit' );
	} );
} )();
