<?php
/**
 * Plugin Name: Alchemy Cookie Consent
 * Plugin URI:  https://websitealchemy.com
 * Description: Lightweight cookie consent banner with WP Consent API + Google Consent Mode v2 integration, built for the Website Alchemy client portfolio.
 * Version:     1.7.1
 * Author:      Website Alchemy
 * Author URI:  https://websitealchemy.com
 * License:     GPL v2 or later
 * Text Domain: alchemy-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALCHEMY_CONSENT_VERSION', '1.7.1' );
define( 'ALCHEMY_CONSENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALCHEMY_CONSENT_URL', plugin_dir_url( __FILE__ ) );
define( 'ALCHEMY_CONSENT_BASENAME', plugin_basename( __FILE__ ) );

require_once ALCHEMY_CONSENT_PATH . 'includes/class-alchemy-consent-activator.php';
require_once ALCHEMY_CONSENT_PATH . 'includes/class-alchemy-consent-admin.php';
require_once ALCHEMY_CONSENT_PATH . 'includes/class-alchemy-consent-public.php';
require_once ALCHEMY_CONSENT_PATH . 'includes/class-alchemy-consent-shortcodes.php';

// Not on WordPress.org, so this is what gives client sites a real
// "Update available" notice + one-click Update Now instead of needing a
// manual zip re-upload every release (which is also what triggers the
// nested-folder bug in WP core's "Replace current with uploaded" flow).
require_once ALCHEMY_CONSENT_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$alchemy_consent_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/mrlennyman/alchemy-consent/',
	__FILE__,
	'alchemy-consent'
);
$alchemy_consent_update_checker->setBranch( 'main' );
// Releases are tagged (vX.Y.Z on main), not published via GitHub's separate
// "Releases" feature, so release assets are left off — PUC builds the
// update zip from the tagged source automatically.

register_activation_hook( __FILE__, array( 'Alchemy_Consent_Activator', 'activate' ) );

// Catches sites where the plugin files are overwritten directly (SFTP/zip
// re-upload) rather than deactivated and reactivated — otherwise an
// already-live site would never pick up new DB columns or setting keys
// added in a later version.
add_action( 'plugins_loaded', array( 'Alchemy_Consent_Activator', 'maybe_upgrade' ) );

/**
 * Boot the plugin once all plugins are loaded, so the WP Consent API
 * (if present) is available to register against.
 */
function alchemy_consent_init() {
	new Alchemy_Consent_Admin();
	new Alchemy_Consent_Public();
	new Alchemy_Consent_Shortcodes();
}
add_action( 'plugins_loaded', 'alchemy_consent_init' );
