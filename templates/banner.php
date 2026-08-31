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

// Every Style-tab field, defaulted the same way the tab's form and the
// save handler resolve them — so a site that's never touched the Style
// tab renders identically to what banner.css hardcodes on its own.
$alchemy_style_defaults = Alchemy_Cookie_Consent_Activator::style_defaults();
$alchemy_style          = array_merge( $alchemy_style_defaults, array_intersect_key( $settings, $alchemy_style_defaults ) );

$alchemy_revisit_color    = $alchemy_style['revisit_bg_color'];
$alchemy_revisit_bg       = $alchemy_hex_to_rgba( $alchemy_revisit_color, $alchemy_style['revisit_opacity'] );
$alchemy_revisit_bg_hover = $alchemy_hex_to_rgba( $alchemy_revisit_color, $alchemy_style['revisit_hover_opacity'] );

$alchemy_font_presets = Alchemy_Cookie_Consent_Activator::font_presets();
$alchemy_font_family  = isset( $alchemy_font_presets[ $alchemy_style['font_preset'] ] )
	? $alchemy_font_presets[ $alchemy_style['font_preset'] ]['family']
	: $alchemy_font_presets['default']['family'];

$alchemy_shadow = ! empty( $alchemy_style['shadow_enabled'] )
	? '0 -2px ' . absint( $alchemy_style['shadow_blur'] ) . 'px ' . $alchemy_hex_to_rgba( $alchemy_style['shadow_color'], $alchemy_style['shadow_opacity'] )
	: 'none';

// One shared set of custom properties applied to every top-level element
// below (main banner, standalone High-Risk prompt, revisit button) — CSS
// variables don't cascade between siblings, and all three need at least
// some of these (buttons, container chrome, the revisit-specific pair).
$alchemy_style_vars = array(
	'--alchemy-cookie-consent-accent'                => $alchemy_style['accent_color'],
	'--alchemy-cookie-consent-font'                  => $alchemy_font_family,
	'--alchemy-cookie-consent-font-size'             => absint( $alchemy_style['font_size'] ) . 'px',
	'--alchemy-cookie-consent-text-color'            => $alchemy_style['text_color'],
	'--alchemy-cookie-consent-button-hover'          => $alchemy_style['button_hover_color'],
	'--alchemy-cookie-consent-button-text'           => $alchemy_style['button_text_color'],
	'--alchemy-cookie-consent-button-outline-color'  => $alchemy_style['button_outline_color'],
	'--alchemy-cookie-consent-button-outline-hover-bg' => $alchemy_style['button_outline_hover_bg'],
	'--alchemy-cookie-consent-button-radius'         => absint( $alchemy_style['button_radius'] ) . 'px',
	'--alchemy-cookie-consent-button-font-size'      => absint( $alchemy_style['button_font_size'] ) . 'px',
	'--alchemy-cookie-consent-button-padding'        => absint( $alchemy_style['button_padding'] ) . 'px',
	'--alchemy-cookie-consent-container-bg'          => $alchemy_style['container_bg_color'],
	'--alchemy-cookie-consent-container-border'      => $alchemy_style['container_border_color'],
	'--alchemy-cookie-consent-container-padding'     => absint( $alchemy_style['container_padding'] ) . 'px',
	'--alchemy-cookie-consent-shadow'                => $alchemy_shadow,
	'--alchemy-cookie-consent-revisit-bg'             => $alchemy_revisit_bg,
	'--alchemy-cookie-consent-revisit-bg-hover'       => $alchemy_revisit_bg_hover,
);
$alchemy_style_attr = '';
foreach ( $alchemy_style_vars as $alchemy_prop => $alchemy_value ) {
	$alchemy_style_attr .= $alchemy_prop . ': ' . $alchemy_value . '; ';
}
$alchemy_style_attr = esc_attr( trim( $alchemy_style_attr ) );
?>
<div id="alchemy-cookie-consent-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-hidden" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above (line ~77); re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">
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
<button type="button" id="alchemy-cookie-consent-revisit" class="alchemy-cookie-consent-revisit" aria-label="Cookie settings" hidden style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above (line ~77); re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">&#127850;</button>

<!--
Standalone compact prompt for session-recording/chat-type tools. Shown
independently of the main banner above — it applies to every visitor
regardless of Strict/Light/Exempt tier (see class-alchemy-cookie-consent-public.php
for why this sits outside the geo-tier system), and only appears at all
if the Cookie List has at least one row flagged High-risk.
-->
<div id="alchemy-cookie-consent-highrisk-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-highrisk-banner alchemy-cookie-consent-hidden" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above (line ~77); re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">
	<div class="alchemy-cookie-consent-inner">
		<p class="alchemy-cookie-consent-message" id="alchemy-cookie-consent-highrisk-standalone-message"></p>
		<div class="alchemy-cookie-consent-actions">
			<button type="button" id="alchemy-cookie-consent-highrisk-decline" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-outline">No Thanks</button>
			<button type="button" id="alchemy-cookie-consent-highrisk-accept" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-solid">I Agree</button>
		</div>
	</div>
</div>
