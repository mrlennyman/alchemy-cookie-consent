<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Cookie_Consent_Public {

	/** @var array|null Memoized per-request; avoids fetching the same option twice on one pageview (wp_enqueue_scripts + wp_footer both need it). */
	private $settings;

	/** @var array|null Memoized per-request; enqueue_assets() and ajax_save_consent() (for the highrisk scope) both need it. */
	private $high_risk_notices;

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_banner' ) );
		add_action( 'wp_ajax_alchemy_cookie_consent_save', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_alchemy_cookie_consent_save', array( $this, 'ajax_save_consent' ) );

		// Fires as early as possible in <head>, ahead of GTM's own script
		// (Site Kit typically prints GTM at default priority 10) — this is
		// what lets a GTM-managed tag like Bing UET or Facebook Pixel key
		// off the visitor's existing choice from the very first pageview,
		// not just after they interact with the banner.
		add_action( 'wp_head', array( $this, 'output_datalayer_bridge' ), 1 );

		// Tell the WP Consent API this plugin handles consent, so Site Kit
		// (and anything else reading the API) recognises us as a valid CMP.
		add_filter( 'wp_consent_api_registered_' . ALCHEMY_COOKIE_CONSENT_BASENAME, '__return_true' );
	}

	private function get_settings() {
		if ( null === $this->settings ) {
			$this->settings = get_option( 'alchemy_cookie_consent_settings' );
		}
		return $this->settings;
	}

	/**
	 * Prints a small inline script (not enqueued, so it can run before
	 * anything else) that reads the alchemy_cookie_consent cookie client-side and
	 * pushes the current state to window.dataLayer. This has to be pure
	 * client-side JS rather than PHP reading $_COOKIE — the output here is
	 * identical for every visitor and is safe under LiteSpeed's full-page
	 * cache, whereas a PHP-echoed value would bake one visitor's consent
	 * state into the cached HTML for everyone.
	 *
	 * Any GTM tag (Bing UET, Facebook Pixel, Hotjar, Clarity, etc.) can
	 * then use a Custom Event trigger on "alchemy_cookie_consent_default" /
	 * "alchemy_cookie_consent_update", gated on the matching alchemy_cookie_consent_* variable —
	 * the same mechanism Site Kit uses for Google's own tags, just made
	 * available to everything else routed through GTM.
	 *
	 * alchemy_cookie_consent_highrisk is a separate signal from the
	 * necessary/analytics/marketing ones — it tracks the always-ask
	 * session-recording/chat prompt, which applies regardless of geo tier
	 * rather than following Strict/Light/Exempt logic. A GTM trigger for
	 * Hotjar/Clarity/a chat widget should key off this variable, not the
	 * general alchemy_cookie_consent_analytics one, even though those tools may
	 * also be categorised as Analytics for the cookie policy table.
	 */
	public function output_datalayer_bridge() {
		?>
<script>
(function(){
	window.dataLayer = window.dataLayer || [];
	// Cookie-read regex kept in sync with getCookie() in banner.js — this
	// snippet has to stay a separate inline script (see class doc above),
	// so the two can't share one function, but they must parse the cookie
	// identically, including the fail-safe try/catch (a malformed cookie —
	// DevTools edit, a colliding third-party script, a truncating proxy —
	// must not throw and silently skip the dataLayer.push below).
	var m = document.cookie.match(/(^| )alchemy_cookie_consent=([^;]+)/);
	var parsed = null;
	if ( m ) {
		try {
			parsed = JSON.parse(decodeURIComponent(m[2]));
		} catch (e) {}
	}
	// Cookie value is { categories: [...], source: '...' } as of 1.6.2 (was
	// a bare categories array before) — accept both shapes so visitors who
	// consented under the old version aren't treated as having no consent.
	var cats = parsed ? ( Array.isArray(parsed) ? parsed : parsed.categories ) : null;
	var hr = document.cookie.match(/(^| )alchemy_cookie_consent_highrisk=([^;]+)/);
	var highRisk = hr ? decodeURIComponent(hr[2]) === 'granted' : false;
	window.dataLayer.push({
		event: 'alchemy_cookie_consent_default',
		alchemy_cookie_consent_necessary: true,
		alchemy_cookie_consent_analytics: cats ? cats.indexOf('analytics') !== -1 : false,
		alchemy_cookie_consent_marketing: cats ? cats.indexOf('marketing') !== -1 : false,
		alchemy_cookie_consent_highrisk: highRisk
	});
})();
</script>
		<?php
	}

	/**
	 * Notices for every cookie-list row flagged High-risk, deduplicated —
	 * drives both the localized settings (has the site got any at all?)
	 * and the standalone prompt's message text. Computed from the cookie
	 * list itself rather than a separate toggle, so it can't drift out of
	 * sync with what the Cookie List tab actually has flagged.
	 */
	private function get_high_risk_notices() {
		if ( null !== $this->high_risk_notices ) {
			return $this->high_risk_notices;
		}
		$cookie_list = get_option( 'alchemy_cookie_consent_cookie_list', array() );
		$notices     = array();
		foreach ( $cookie_list as $c ) {
			if ( empty( $c['high_risk'] ) ) {
				continue;
			}
			$text = ! empty( $c['notice'] ) ? $c['notice'] : ( ! empty( $c['purpose'] ) ? $c['purpose'] : $c['name'] );
			if ( $text && ! in_array( $text, $notices, true ) ) {
				$notices[] = $text;
			}
		}
		$this->high_risk_notices = $notices;
		return $notices;
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'alchemy-cookie-consent-banner', ALCHEMY_COOKIE_CONSENT_URL . 'assets/css/banner.css', array(), ALCHEMY_COOKIE_CONSENT_VERSION );
		wp_enqueue_script( 'alchemy-cookie-consent-banner', ALCHEMY_COOKIE_CONSENT_URL . 'assets/js/banner.js', array(), ALCHEMY_COOKIE_CONSENT_VERSION, true );

		$settings = $this->get_settings();

		wp_localize_script(
			'alchemy-cookie-consent-banner',
			'alchemyCookieConsentData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'alchemy_cookie_consent_nonce' ),
				'settings' => array(
					'categories_enabled'     => isset( $settings['categories_enabled'] ) ? $settings['categories_enabled'] : array(
						'analytics' => false,
						'marketing' => false,
					),
					'geo_targeting_enabled'  => ! empty( $settings['geo_targeting_enabled'] ),
					'strict_countries'       => ! empty( $settings['strict_countries'] )
						? array_map( 'trim', explode( ',', strtoupper( $settings['strict_countries'] ) ) )
						: array(),
					'light_countries'        => ! empty( $settings['light_countries'] )
						? array_map( 'trim', explode( ',', strtoupper( $settings['light_countries'] ) ) )
						: array(),
					'has_high_risk'          => ! empty( $this->get_high_risk_notices() ),
					'high_risk_notices'      => $this->get_high_risk_notices(),
				),
			)
		);
	}

	public function render_banner() {
		$settings = $this->get_settings();
		include ALCHEMY_COOKIE_CONSENT_PATH . 'templates/banner.php';
	}

	public function ajax_save_consent() {
		check_ajax_referer( 'alchemy_cookie_consent_nonce', 'nonce' );

		// "general" = the Necessary/Analytics/Marketing decision (drives WP
		// Consent API). "highrisk" = the separate always-ask session-
		// recording/chat decision — it must never touch wp_set_consent(),
		// or a highrisk-only submission would incorrectly overwrite
		// categories the visitor already granted earlier.
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'general';
		if ( ! in_array( $scope, array( 'general', 'highrisk' ), true ) ) {
			$scope = 'general';
		}

		$categories = array();
		if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
			$categories = array_map( 'sanitize_text_field', wp_unslash( $_POST['categories'] ) );
		}
		// Allow-list rather than trusting arbitrary posted values — this is
		// a public, unauthenticated endpoint by necessity (visitors aren't
		// logged in), so anything beyond the real categories gets dropped
		// rather than stored or acted on.
		$categories = array_intersect( $categories, array( 'necessary', 'analytics', 'marketing', 'high_risk' ) );

		if ( 'general' === $scope ) {
			// Enforce this site's categories_enabled setting server-side —
			// the banner UI only offers a checkbox for enabled categories,
			// but that's a client-side restriction only; a category
			// disabled for this site must not be recordable as granted
			// regardless of what a client sends (a stale cache, a scripted
			// POST, or a client-side bug).
			$settings_for_gate  = $this->get_settings();
			$enabled_categories = isset( $settings_for_gate['categories_enabled'] ) ? $settings_for_gate['categories_enabled'] : array();
			$categories         = array_values(
				array_filter(
					$categories,
					function ( $category ) use ( $enabled_categories ) {
						return 'necessary' === $category || ! empty( $enabled_categories[ $category ] );
					}
				)
			);

			// Push the choice into the WP Consent API. Site Kit reads this
			// and handles the actual Google Consent Mode v2 signalling.
			if ( function_exists( 'wp_set_consent' ) ) {
				wp_set_consent( 'necessary', 'allow' );
				wp_set_consent( 'statistics', in_array( 'analytics', $categories, true ) ? 'allow' : 'deny' );
				wp_set_consent( 'marketing', in_array( 'marketing', $categories, true ) ? 'allow' : 'deny' );
			}
		} else {
			// highrisk scope: only 'high_risk' is meaningful here, and only
			// if the site actually has a high-risk tool configured — mirrors
			// the client-side has_high_risk gate server-side so a scripted
			// request can't record a grant for a signal this site never
			// asked about. wp_set_consent() is deliberately never touched
			// in this branch.
			$categories = ( in_array( 'high_risk', $categories, true ) && ! empty( $this->get_high_risk_notices() ) )
				? array( 'high_risk' )
				: array();
		}

		$this->log_consent( $categories );

		wp_send_json_success();
	}

	private function log_consent( $categories ) {
		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_cookie_consent_log';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is
		// already verified by check_ajax_referer() in ajax_save_consent(), the
		// only caller of this private method; the sniff can't see across methods.
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'explicit';
		if ( ! in_array( $source, array( 'explicit', 'geo-light', 'geo-exempt', 'gpc' ), true ) ) {
			$source = 'explicit';
		}

		// $wpdb->insert() into this plugin's own consent-log table; no core WP API for that.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'consent_time' => current_time( 'mysql' ),
				'categories'   => wp_json_encode( $categories ),
				'ip_hash'      => hash( 'sha256', $this->get_ip() . wp_salt() ),
				'page_url'     => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
				'source'       => $source,
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * We only ever store a salted hash of the IP, never the IP itself —
	 * enough to dedupe/audit without keeping personal data around.
	 */
	private function get_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
