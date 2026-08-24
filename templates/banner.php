<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $settings */
$alchemy_show_analytics = ! empty( $settings['categories_enabled']['analytics'] );
$alchemy_show_marketing = ! empty( $settings['categories_enabled']['marketing'] );
$alchemy_policy_page_id = isset( $settings['policy_page_id'] ) ? absint( $settings['policy_page_id'] ) : 0;
$alchemy_policy_url     = $alchemy_policy_page_id ? get_permalink( $alchemy_policy_page_id ) : '';

/**
 * Converts a hex color + opacity percentage into an rgba() string, so the
 * revisit button's background can fade without also fading the icon/text
 * inside it (plain CSS `opacity` would fade the whole element).
 */
$alchemy_hex_to_rgba = function ( $hex, $opacity_percent ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		$hex = 'ffffff';
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$a = max( 0, min( 100, (float) $opacity_percent ) ) / 100;
	return "rgba($r, $g, $b, $a)";
};

$alchemy_revisit_color    = isset( $settings['revisit_bg_color'] ) ? $settings['revisit_bg_color'] : '#ffffff';
$alchemy_revisit_bg       = $alchemy_hex_to_rgba( $alchemy_revisit_color, isset( $settings['revisit_opacity'] ) ? $settings['revisit_opacity'] : 55 );
$alchemy_revisit_bg_hover = $alchemy_hex_to_rgba( $alchemy_revisit_color, isset( $settings['revisit_hover_opacity'] ) ? $settings['revisit_hover_opacity'] : 100 );
?>
<div id="alchemy-cookie-consent-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-hidden" style="--alchemy-cookie-consent-accent: <?php echo esc_attr( $settings['accent_color'] ); ?>;">
	<div class="alchemy-cookie-consent-inner">
		<p class="alchemy-cookie-consent-message">
			<?php echo esc_html( $settings['banner_message'] ); ?>
			<?php if ( $alchemy_policy_url ) : ?>
				<a href="<?php echo esc_url( $alchemy_policy_url ); ?>" class="alchemy-cookie-consent-policy-link" target="_blank" rel="noopener">Learn more</a>
			<?php endif; ?>
		</p>

		<div class="alchemy-cookie-consent-categories" hidden>
			<label><input type="checkbox" checked disabled> Necessary</label>
			<?php if ( $alchemy_show_analytics ) : ?>
				<label><input type="checkbox" id="alchemy-cookie-consent-analytics"> Analytics</label>
			<?php endif; ?>
			<?php if ( $alchemy_show_marketing ) : ?>
				<label><input type="checkbox" id="alchemy-cookie-consent-marketing"> Marketing</label>
			<?php endif; ?>
		</div>

		<div class="alchemy-cookie-consent-actions">
			<button type="button" id="alchemy-cookie-consent-customize" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-text"><?php echo esc_html( $settings['customize_label'] ); ?></button>
			<button type="button" id="alchemy-cookie-consent-reject" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-outline"><?php echo esc_html( $settings['reject_label'] ); ?></button>
			<button type="button" id="alchemy-cookie-consent-accept" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-solid"><?php echo esc_html( $settings['accept_label'] ); ?></button>
			<button type="button" id="alchemy-cookie-consent-save" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-solid" hidden><?php echo esc_html( $settings['save_label'] ); ?></button>
		</div>
	</div>
</div>
<button type="button" id="alchemy-cookie-consent-revisit" class="alchemy-cookie-consent-revisit" aria-label="Cookie settings" hidden style="--alchemy-cookie-consent-revisit-bg: <?php echo esc_attr( $alchemy_revisit_bg ); ?>; --alchemy-cookie-consent-revisit-bg-hover: <?php echo esc_attr( $alchemy_revisit_bg_hover ); ?>;">&#127850;</button>

<!--
Standalone compact prompt for session-recording/chat-type tools. Shown
independently of the main banner above — it applies to every visitor
regardless of Strict/Light/Exempt tier (see class-alchemy-cookie-consent-public.php
for why this sits outside the geo-tier system), and only appears at all
if the Cookie List has at least one row flagged High-risk.
-->
<div id="alchemy-cookie-consent-highrisk-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-highrisk-banner alchemy-cookie-consent-hidden" style="--alchemy-cookie-consent-accent: <?php echo esc_attr( $settings['accent_color'] ); ?>;">
	<div class="alchemy-cookie-consent-inner">
		<p class="alchemy-cookie-consent-message" id="alchemy-cookie-consent-highrisk-standalone-message"></p>
		<div class="alchemy-cookie-consent-actions">
			<button type="button" id="alchemy-cookie-consent-highrisk-decline" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-outline">No Thanks</button>
			<button type="button" id="alchemy-cookie-consent-highrisk-accept" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-solid">I Agree</button>
		</div>
	</div>
</div>
