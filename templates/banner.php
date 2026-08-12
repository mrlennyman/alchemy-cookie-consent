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
<div id="alchemy-consent-banner" class="alchemy-consent-banner alchemy-consent-hidden" style="--alchemy-consent-accent: <?php echo esc_attr( $settings['accent_color'] ); ?>;">
	<div class="alchemy-consent-inner">
		<p class="alchemy-consent-message">
			<?php echo esc_html( $settings['banner_message'] ); ?>
			<?php if ( $alchemy_policy_url ) : ?>
				<a href="<?php echo esc_url( $alchemy_policy_url ); ?>" class="alchemy-consent-policy-link" target="_blank" rel="noopener">Learn more</a>
			<?php endif; ?>
		</p>

		<div class="alchemy-consent-categories" hidden>
			<label><input type="checkbox" checked disabled> Necessary</label>
			<?php if ( $alchemy_show_analytics ) : ?>
				<label><input type="checkbox" id="alchemy-consent-analytics"> Analytics</label>
			<?php endif; ?>
			<?php if ( $alchemy_show_marketing ) : ?>
				<label><input type="checkbox" id="alchemy-consent-marketing"> Marketing</label>
			<?php endif; ?>
		</div>

		<div class="alchemy-consent-actions">
			<button type="button" id="alchemy-consent-customize" class="alchemy-consent-btn alchemy-consent-btn-text"><?php echo esc_html( $settings['customize_label'] ); ?></button>
			<button type="button" id="alchemy-consent-reject" class="alchemy-consent-btn alchemy-consent-btn-outline"><?php echo esc_html( $settings['reject_label'] ); ?></button>
			<button type="button" id="alchemy-consent-accept" class="alchemy-consent-btn alchemy-consent-btn-solid"><?php echo esc_html( $settings['accept_label'] ); ?></button>
			<button type="button" id="alchemy-consent-save" class="alchemy-consent-btn alchemy-consent-btn-solid" hidden><?php echo esc_html( $settings['save_label'] ); ?></button>
		</div>
	</div>
</div>
<button type="button" id="alchemy-consent-revisit" class="alchemy-consent-revisit" aria-label="Cookie settings" hidden style="--alchemy-consent-revisit-bg: <?php echo esc_attr( $alchemy_revisit_bg ); ?>; --alchemy-consent-revisit-bg-hover: <?php echo esc_attr( $alchemy_revisit_bg_hover ); ?>;">&#127850;</button>
