<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Cookie_Consent_Activator {

	public static function activate() {
		self::create_log_table();
		self::seed_default_settings();
		self::seed_default_cookie_list();
		update_option( 'alchemy_cookie_consent_db_version', ALCHEMY_COOKIE_CONSENT_VERSION );
	}

	/**
	 * Runs on every load and compares a stored version against the code's
	 * version — covers sites (like an already-live install) that get the
	 * plugin files overwritten directly rather than deactivated/reactivated,
	 * which would otherwise never pick up new DB columns or new setting
	 * keys added in a later version.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'alchemy_cookie_consent_db_version' ) === ALCHEMY_COOKIE_CONSENT_VERSION ) {
			return;
		}

		self::create_log_table(); // dbDelta adds new columns without touching existing rows.
		self::merge_new_setting_defaults();

		update_option( 'alchemy_cookie_consent_db_version', ALCHEMY_COOKIE_CONSENT_VERSION );
	}

	/**
	 * Fills in any settings keys introduced after this site's initial
	 * install, without touching values the site already has configured.
	 */
	private static function merge_new_setting_defaults() {
		$settings = get_option( 'alchemy_cookie_consent_settings', array() );
		$defaults = array_merge(
			array(
				'geo_targeting_enabled' => false, // off by default — never silently changes existing behaviour on upgrade.
				'strict_countries'      => self::default_strict_countries(),
				'light_countries'       => self::default_light_countries(),
				'policy_page_id'        => 0,
			),
			self::style_defaults()
		);
		update_option( 'alchemy_cookie_consent_settings', array_merge( $defaults, $settings ) );
	}

	/**
	 * Every visual/style setting, in one place — shared between seeding
	 * (below), the Style tab's form (class-alchemy-cookie-consent-admin.php),
	 * and the banner template's CSS-variable resolver
	 * (templates/banner.php), so the three can't drift out of sync.
	 * Values match what banner.css already hardcodes, so a fresh install's
	 * appearance is unchanged until a client actually customises the Style
	 * tab.
	 */
	public static function style_defaults() {
		return array(
			'accent_color'            => '#1a73e8',
			'font_preset'             => 'default',
			'font_size'               => 14,
			'text_color'              => '#333333',
			'button_hover_color'      => '#155cba',
			'button_text_color'       => '#ffffff',
			'button_outline_color'    => '#cccccc',
			'button_outline_hover_bg' => '#f5f5f5',
			'button_radius'           => 4,
			'button_font_size'        => 14,
			'button_padding'          => 8,
			'container_bg_color'      => '#ffffff',
			'container_border_color'  => '#e2e2e2',
			'container_padding'       => 16,
			'shadow_enabled'          => true,
			'shadow_color'            => '#000000',
			'shadow_opacity'          => 8,
			'shadow_blur'             => 12,
			'revisit_bg_color'        => '#ffffff',
			'revisit_opacity'         => 55,
			'revisit_hover_opacity'   => 100,
		);
	}

	/**
	 * Font choices for the Style tab. Only "family" is required per entry —
	 * "google" is the stylesheet URL to enqueue when that preset is active,
	 * left null for stacks that don't need a web font. Kept in the same
	 * naming/style as the equivalent in the Alchemy Forms plugin so an admin
	 * managing several client sites sees a familiar, consistent choice.
	 */
	public static function font_presets() {
		return array(
			'default' => array(
				'label'  => 'Default (system font)',
				'family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
				'google' => null,
			),
			'inter'   => array(
				'label'  => 'Inter',
				'family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
				'google' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap',
			),
			'classic' => array(
				'label'  => 'Classic (Georgia)',
				'family' => "Georgia, 'Times New Roman', serif",
				'google' => null,
			),
			'modern'  => array(
				'label'  => 'Modern (Poppins)',
				'family' => "'Poppins', -apple-system, sans-serif",
				'google' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap',
			),
		);
	}

	/**
	 * EU-27 + EEA (Iceland, Liechtenstein, Norway) + UK + Canada.
	 * Canada is included whole rather than trying to isolate Quebec —
	 * country-level detection can't reliably distinguish provinces, and
	 * Law 25's opt-in standard is the safer one to apply nationwide.
	 * Switzerland isn't included by default (FADP is GDPR-similar but a
	 * separate law) — add "CH" here if a client's situation calls for it.
	 */
	public static function default_strict_countries() {
		return 'AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IS,IT,LV,LI,LT,LU,MT,NL,NO,PL,PT,RO,SK,SI,ES,SE,GB,CA';
	}

	/**
	 * US only, by design: 20 US states now have opt-out-style privacy laws
	 * (not just California), and country-level geolocation can't isolate
	 * California from the rest of the country anyway — so all US traffic
	 * gets the Light treatment (auto-granted, opt-out button shown) rather
	 * than trying to carve out one state. Simpler, and doesn't rely on the
	 * less-reliable region-level detection endpoint.
	 */
	public static function default_light_countries() {
		return 'US';
	}

	private static function create_log_table() {
		global $wpdb;
		$table           = $wpdb->prefix . 'alchemy_cookie_consent_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_time DATETIME NOT NULL,
			categories TEXT NOT NULL,
			ip_hash VARCHAR(64) DEFAULT '',
			page_url VARCHAR(500) DEFAULT '',
			source VARCHAR(20) DEFAULT 'explicit',
			PRIMARY KEY (id),
			KEY consent_time (consent_time)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function seed_default_settings() {
		if ( false !== get_option( 'alchemy_cookie_consent_settings' ) ) {
			return;
		}

		$defaults = array_merge(
			self::style_defaults(),
			array(
				'banner_message'        => "We use cookies to improve your experience and understand how visitors use this site. Choose which categories you're comfortable with.",
				'accept_label'          => 'Accept All',
				'reject_label'          => 'Reject All',
				'customize_label'       => 'Customize',
				'save_label'            => 'Save Preferences',
				'position'              => 'bottom',
				'policy_page_id'        => 0,
				'geo_targeting_enabled' => false,
				'strict_countries'      => self::default_strict_countries(),
				'light_countries'       => self::default_light_countries(),
				'categories_enabled'    => array(
					'analytics' => true,
					'marketing' => false,
				),
			)
		);

		add_option( 'alchemy_cookie_consent_settings', $defaults );
	}

	private static function seed_default_cookie_list() {
		if ( false !== get_option( 'alchemy_cookie_consent_cookie_list' ) ) {
			return;
		}

		// Seeded for the typical Website Alchemy stack: WordPress core,
		// WooCommerce, GiveWP, GA4 via Site Kit. Edit per-site from the
		// Cookie List tab if a client runs anything unusual.
		$default_cookies = array(
			array(
				'name'     => 'wordpress_logged_in_*',
				'category' => 'necessary',
				'purpose'  => 'Keeps you logged in to WordPress.',
				'duration' => 'Session',
			),
			array(
				'name'     => 'woocommerce_cart_hash',
				'category' => 'necessary',
				'purpose'  => 'Tracks changes to your cart contents.',
				'duration' => 'Session',
			),
			array(
				'name'     => 'woocommerce_items_in_cart',
				'category' => 'necessary',
				'purpose'  => 'Remembers items in your cart.',
				'duration' => 'Session',
			),
			array(
				'name'     => 'wp_woocommerce_session_*',
				'category' => 'necessary',
				'purpose'  => 'Maintains your shopping session while you browse.',
				'duration' => '2 days',
			),
			array(
				'name'     => '_ga',
				'category' => 'analytics',
				'purpose'  => 'Google Analytics — distinguishes unique visitors.',
				'duration' => '2 years',
			),
			array(
				'name'     => '_ga_*',
				'category' => 'analytics',
				'purpose'  => 'Google Analytics 4 — persists session state.',
				'duration' => '2 years',
			),
			array(
				'name'     => '_gid',
				'category' => 'analytics',
				'purpose'  => 'Google Analytics — distinguishes visitors for a shorter window.',
				'duration' => '24 hours',
			),
			array(
				'name'     => 'alchemy_cookie_consent',
				'category' => 'necessary',
				'purpose'  => 'Stores your cookie preferences on this site.',
				'duration' => '6 months',
			),
			array(
				'name'     => 'alchemy_cookie_consent_highrisk',
				'category' => 'necessary',
				'purpose'  => 'Stores your decision on session-recording/chat tools, where used on this site.',
				'duration' => '6 months',
			),
		);

		add_option( 'alchemy_cookie_consent_cookie_list', $default_cookies );
	}
}
