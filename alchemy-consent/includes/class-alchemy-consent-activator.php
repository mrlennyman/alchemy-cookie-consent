<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Consent_Activator {

	public static function activate() {
		self::create_log_table();
		self::seed_default_settings();
		self::seed_default_cookie_list();
		update_option( 'alchemy_consent_db_version', ALCHEMY_CONSENT_VERSION );
	}

	/**
	 * Runs on every load and compares a stored version against the code's
	 * version — covers sites (like an already-live install) that get the
	 * plugin files overwritten directly rather than deactivated/reactivated,
	 * which would otherwise never pick up new DB columns or new setting
	 * keys added in a later version.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'alchemy_consent_db_version' ) === ALCHEMY_CONSENT_VERSION ) {
			return;
		}

		self::migrate_from_wa_consent(); // one-time: sites running this under its previous name/slug.
		self::create_log_table(); // dbDelta adds new columns without touching existing rows.
		self::merge_new_setting_defaults();

		update_option( 'alchemy_consent_db_version', ALCHEMY_CONSENT_VERSION );
	}

	/**
	 * One-time migration for sites that ran this plugin under its previous
	 * name/slug (WA Consent), before the rename forced by WordPress.org's
	 * "wa" trademark restriction. Renames the DB table and copies option
	 * data across so existing settings, cookie list, and consent history
	 * aren't lost. Idempotent — each step only acts if the old data is
	 * present and the new data isn't yet, so it's safe to leave running
	 * on every load-check indefinitely.
	 */
	private static function migrate_from_wa_consent() {
		global $wpdb;
		$old_table = $wpdb->prefix . 'wa_consent_log';
		$new_table = $wpdb->prefix . 'alchemy_consent_log';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time rename of this plugin's own table; identifiers built from $wpdb->prefix only, not user input.
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );

		if ( $old_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- already uses $wpdb->prepare() with the %i identifier placeholder (WP 6.2+), the correct mechanism for dynamic table names; both values are built from $wpdb->prefix plus a fixed literal, never from user input. The checker doesn't recognise %i as sufficient escaping yet.
			$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old_table, $new_table ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( false !== get_option( 'wa_consent_settings' ) && false === get_option( 'alchemy_consent_settings' ) ) {
			add_option( 'alchemy_consent_settings', get_option( 'wa_consent_settings' ) );
			delete_option( 'wa_consent_settings' );
		}

		if ( false !== get_option( 'wa_consent_cookie_list' ) && false === get_option( 'alchemy_consent_cookie_list' ) ) {
			add_option( 'alchemy_consent_cookie_list', get_option( 'wa_consent_cookie_list' ) );
			delete_option( 'wa_consent_cookie_list' );
		}

		delete_option( 'wa_consent_db_version' ); // old version marker, no longer relevant once migrated.
	}

	/**
	 * Fills in any settings keys introduced after this site's initial
	 * install, without touching values the site already has configured.
	 */
	private static function merge_new_setting_defaults() {
		$settings = get_option( 'alchemy_consent_settings', array() );
		$defaults = array(
			'geo_targeting_enabled' => false, // off by default — never silently changes existing behaviour on upgrade.
			'strict_countries'      => self::default_strict_countries(),
			'light_countries'       => self::default_light_countries(),
			'policy_page_id'        => 0,
			'revisit_bg_color'      => '#ffffff',
			'revisit_opacity'       => 55,
			'revisit_hover_opacity' => 100,
		);
		update_option( 'alchemy_consent_settings', array_merge( $defaults, $settings ) );
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
		$table           = $wpdb->prefix . 'alchemy_consent_log';
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
		if ( false !== get_option( 'alchemy_consent_settings' ) ) {
			return;
		}

		$defaults = array(
			'banner_message'        => "We use cookies to improve your experience and understand how visitors use this site. Choose which categories you're comfortable with.",
			'accept_label'          => 'Accept All',
			'reject_label'          => 'Reject All',
			'customize_label'       => 'Customize',
			'save_label'            => 'Save Preferences',
			'accent_color'          => '#1a73e8',
			'position'              => 'bottom',
			'policy_page_id'        => 0,
			'geo_targeting_enabled' => false,
			'strict_countries'      => self::default_strict_countries(),
			'light_countries'       => self::default_light_countries(),
			'revisit_bg_color'      => '#ffffff',
			'revisit_opacity'       => 55,
			'revisit_hover_opacity' => 100,
			'categories_enabled'    => array(
				'analytics' => true,
				'marketing' => false,
			),
		);

		add_option( 'alchemy_consent_settings', $defaults );
	}

	private static function seed_default_cookie_list() {
		if ( false !== get_option( 'alchemy_consent_cookie_list' ) ) {
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
				'name'     => 'alchemy_consent',
				'category' => 'necessary',
				'purpose'  => 'Stores your cookie preferences on this site.',
				'duration' => '6 months',
			),
		);

		add_option( 'alchemy_consent_cookie_list', $default_cookies );
	}
}
