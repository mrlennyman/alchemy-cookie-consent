<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Consent_Public {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_banner' ) );
		add_action( 'wp_ajax_alchemy_consent_save', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_alchemy_consent_save', array( $this, 'ajax_save_consent' ) );

		// Fires as early as possible in <head>, ahead of GTM's own script
		// (Site Kit typically prints GTM at default priority 10) — this is
		// what lets a GTM-managed tag like Bing UET or Facebook Pixel key
		// off the visitor's existing choice from the very first pageview,
		// not just after they interact with the banner.
		add_action( 'wp_head', array( $this, 'output_datalayer_bridge' ), 1 );

		// Tell the WP Consent API this plugin handles consent, so Site Kit
		// (and anything else reading the API) recognises us as a valid CMP.
		add_filter( 'wp_consent_api_registered_' . ALCHEMY_CONSENT_BASENAME, '__return_true' );
	}

	/**
	 * Prints a small inline script (not enqueued, so it can run before
	 * anything else) that reads the alchemy_consent cookie client-side and
	 * pushes the current state to window.dataLayer. This has to be pure
	 * client-side JS rather than PHP reading $_COOKIE — the output here is
	 * identical for every visitor and is safe under LiteSpeed's full-page
	 * cache, whereas a PHP-echoed value would bake one visitor's consent
	 * state into the cached HTML for everyone.
	 *
	 * Any GTM tag (Bing UET, Facebook Pixel, Hotjar, Clarity, etc.) can
	 * then use a Custom Event trigger on "alchemy_consent_default" /
	 * "alchemy_consent_update", gated on the matching alchemy_consent_* variable —
	 * the same mechanism Site Kit uses for Google's own tags, just made
	 * available to everything else routed through GTM.
	 */
	public function output_datalayer_bridge() {
		?>
<script>
(function(){
	window.dataLayer = window.dataLayer || [];
	var m = document.cookie.match(/(^| )alchemy_consent=([^;]+)/);
	var cats = m ? JSON.parse(decodeURIComponent(m[2])) : null;
	window.dataLayer.push({
		event: 'alchemy_consent_default',
		alchemy_consent_necessary: true,
		alchemy_consent_analytics: cats ? cats.indexOf('analytics') !== -1 : false,
		alchemy_consent_marketing: cats ? cats.indexOf('marketing') !== -1 : false
	});
})();
</script>
		<?php
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'alchemy-consent-banner', ALCHEMY_CONSENT_URL . 'assets/css/banner.css', array(), ALCHEMY_CONSENT_VERSION );
		wp_enqueue_script( 'alchemy-consent-banner', ALCHEMY_CONSENT_URL . 'assets/js/banner.js', array(), ALCHEMY_CONSENT_VERSION, true );

		$settings = get_option( 'alchemy_consent_settings' );

		wp_localize_script(
			'alchemy-consent-banner',
			'waConsentData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'alchemy_consent_nonce' ),
				'settings' => array(
					'categories_enabled'     => $settings['categories_enabled'],
					'geo_targeting_enabled'  => ! empty( $settings['geo_targeting_enabled'] ),
					'strict_countries'       => ! empty( $settings['strict_countries'] )
						? array_map( 'trim', explode( ',', strtoupper( $settings['strict_countries'] ) ) )
						: array(),
					'light_countries'        => ! empty( $settings['light_countries'] )
						? array_map( 'trim', explode( ',', strtoupper( $settings['light_countries'] ) ) )
						: array(),
				),
			)
		);
	}

	public function render_banner() {
		$settings = get_option( 'alchemy_consent_settings' );
		include ALCHEMY_CONSENT_PATH . 'templates/banner.php';
	}

	public function ajax_save_consent() {
		check_ajax_referer( 'alchemy_consent_nonce', 'nonce' );

		$categories = array();
		if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
			$categories = array_map( 'sanitize_text_field', wp_unslash( $_POST['categories'] ) );
		}
		// Allow-list rather than trusting arbitrary posted values — this is
		// a public, unauthenticated endpoint by necessity (visitors aren't
		// logged in), so anything beyond the three real categories gets
		// dropped rather than stored or acted on.
		$categories = array_intersect( $categories, array( 'necessary', 'analytics', 'marketing' ) );

		// Push the choice into the WP Consent API. Site Kit reads this and
		// handles the actual Google Consent Mode v2 signalling itself.
		if ( function_exists( 'wp_set_consent' ) ) {
			wp_set_consent( 'necessary', 'allow' );
			wp_set_consent( 'statistics', in_array( 'analytics', $categories, true ) ? 'allow' : 'deny' );
			wp_set_consent( 'marketing', in_array( 'marketing', $categories, true ) ? 'allow' : 'deny' );
		}

		$this->log_consent( $categories );

		wp_send_json_success();
	}

	private function log_consent( $categories ) {
		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_consent_log';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is
		// already verified by check_ajax_referer() in ajax_save_consent(), the
		// only caller of this private method; the sniff can't see across methods.
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'explicit';
		if ( ! in_array( $source, array( 'explicit', 'geo-light', 'geo-exempt' ), true ) ) {
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
