<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $settings */
$alchemy_show_analytics = ! empty( $settings['categories_enabled']['analytics'] );
$alchemy_show_marketing = ! empty( $settings['categories_enabled']['marketing'] );
$alchemy_policy_page_id = isset( $settings['policy_page_id'] ) ? absint( $settings['policy_page_id'] ) : 0;
$alchemy_policy_url     = $alchemy_policy_page_id ? get_permalink( $alchemy_policy_page_id ) : '';
$alchemy_heading_text   = isset( $settings['heading_text'] ) ? $settings['heading_text'] : 'We use cookies';

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
$alchemy_google_fonts   = Alchemy_Cookie_Consent_Activator::google_fonts();

/**
 * Resolves a role's font key ("Fraunces", "__system_sans__", ...) to the
 * actual CSS font-family stack, falling back to the system sans stack for
 * a key that's somehow gone missing from the curated list (e.g. a font
 * removed from google_fonts() after a site already selected it).
 */
$alchemy_font_family = function ( $role ) use ( $alchemy_style, $alchemy_google_fonts ) {
	$key = $alchemy_style[ $role . '_font' ];
	return isset( $alchemy_google_fonts[ $key ] ) ? $alchemy_google_fonts[ $key ]['family'] : $alchemy_google_fonts['__system_sans__']['family'];
};

$alchemy_revisit_color    = $alchemy_style['revisit_bg_color'];
$alchemy_revisit_bg       = $alchemy_hex_to_rgba( $alchemy_revisit_color, $alchemy_style['revisit_opacity'] );
$alchemy_revisit_bg_hover = $alchemy_hex_to_rgba( $alchemy_revisit_color, $alchemy_style['revisit_hover_opacity'] );

$alchemy_layout   = ( 'card' === $alchemy_style['layout_preset'] ) ? 'card' : 'bar';
$alchemy_position = ( 'right' === $alchemy_style['card_position'] ) ? 'right' : 'left';

// Bar sits flush against the viewport edges, so its shadow points straight
// up (negative Y offset) into the page content above it; Card floats away
// from every edge, so a normal downward-and-out shadow reads correctly.
$alchemy_shadow_offset = ( 'card' === $alchemy_layout ) ? '0 8px' : '0 -2px';
$alchemy_shadow        = ! empty( $alchemy_style['shadow_enabled'] )
	? $alchemy_shadow_offset . ' ' . absint( $alchemy_style['shadow_blur'] ) . 'px ' . $alchemy_hex_to_rgba( $alchemy_style['shadow_color'], $alchemy_style['shadow_opacity'] )
	: 'none';

// One shared set of custom properties applied to every top-level element
// below (main banner, standalone High-Risk prompt, revisit button) — CSS
// variables don't cascade between siblings, and all three need at least
// some of these (buttons, container chrome, the revisit-specific pair).
$alchemy_style_vars = array(
	'--alchemy-cookie-consent-accent'                  => $alchemy_style['accent_color'],
	'--alchemy-cookie-consent-heading-font'            => $alchemy_font_family( 'heading' ),
	'--alchemy-cookie-consent-heading-weight'          => absint( $alchemy_style['heading_weight'] ),
	'--alchemy-cookie-consent-heading-font-size'       => absint( $alchemy_style['heading_font_size'] ) . 'px',
	'--alchemy-cookie-consent-heading-color'           => $alchemy_style['heading_color'],
	'--alchemy-cookie-consent-font'                    => $alchemy_font_family( 'body' ),
	'--alchemy-cookie-consent-font-weight'             => absint( $alchemy_style['body_weight'] ),
	'--alchemy-cookie-consent-font-size'                => absint( $alchemy_style['body_font_size'] ) . 'px',
	'--alchemy-cookie-consent-text-color'              => $alchemy_style['body_color'],
	'--alchemy-cookie-consent-button-font'             => $alchemy_font_family( 'button' ),
	'--alchemy-cookie-consent-button-weight'           => absint( $alchemy_style['button_weight'] ),
	'--alchemy-cookie-consent-button-hover'            => $alchemy_style['button_hover_color'],
	'--alchemy-cookie-consent-button-text'             => $alchemy_style['button_text_color'],
	'--alchemy-cookie-consent-button-outline-color'    => $alchemy_style['button_outline_color'],
	'--alchemy-cookie-consent-button-outline-hover-bg' => $alchemy_style['button_outline_hover_bg'],
	'--alchemy-cookie-consent-button-radius'           => absint( $alchemy_style['button_radius'] ) . 'px',
	'--alchemy-cookie-consent-button-font-size'        => absint( $alchemy_style['button_font_size'] ) . 'px',
	'--alchemy-cookie-consent-button-padding'          => absint( $alchemy_style['button_padding'] ) . 'px',
	'--alchemy-cookie-consent-container-bg'            => $alchemy_style['container_bg_color'],
	'--alchemy-cookie-consent-container-border'        => $alchemy_style['container_border_color'],
	'--alchemy-cookie-consent-container-padding'       => absint( $alchemy_style['container_padding'] ) . 'px',
	'--alchemy-cookie-consent-shadow'                  => $alchemy_shadow,
	'--alchemy-cookie-consent-revisit-bg'              => $alchemy_revisit_bg,
	'--alchemy-cookie-consent-revisit-bg-hover'        => $alchemy_revisit_bg_hover,
);
$alchemy_style_attr = '';
foreach ( $alchemy_style_vars as $alchemy_prop => $alchemy_value ) {
	$alchemy_style_attr .= $alchemy_prop . ': ' . $alchemy_value . '; ';
}
$alchemy_style_attr = esc_attr( trim( $alchemy_style_attr ) );
?>
<div id="alchemy-cookie-consent-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-hidden" data-alchemy-layout="<?php echo esc_attr( $alchemy_layout ); ?>" data-alchemy-position="<?php echo esc_attr( $alchemy_position ); ?>" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above; re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">
	<!--
	"Don't show again" — a quick dismiss, functionally identical to
	clicking Reject (necessary-only, nothing granted) rather than a
	distinct third option, since dismissing without an actual choice
	can never safely mean "assume they're fine with tracking." Logged
	with its own "dismissed" source so it's distinguishable in the
	consent log from an explicit Reject All click.
	-->
	<button type="button" id="alchemy-cookie-consent-dismiss" class="alchemy-cookie-consent-dismiss" aria-label="Dismiss — don't show again">&times;</button>
	<div class="alchemy-cookie-consent-inner">
		<?php if ( 'card' === $alchemy_layout ) : ?>
			<div class="alchemy-cookie-consent-heading-row">
				<span class="alchemy-cookie-consent-icon" aria-hidden="true">&#127850;</span>
				<p class="alchemy-cookie-consent-heading"><?php echo esc_html( $alchemy_heading_text ); ?></p>
			</div>
		<?php endif; ?>
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
<button type="button" id="alchemy-cookie-consent-revisit" class="alchemy-cookie-consent-revisit" aria-label="Cookie settings" hidden data-alchemy-position="<?php echo esc_attr( $alchemy_position ); ?>" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above; re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">&#127850;</button>

<!--
[alchemy_privacy_choices] (or GPC) can update the consent cookie at any
point, including when the banner is already hidden and the revisit
button is already showing — i.e. no other element on the page visibly
changes. Without this, clicking it looks like nothing happened even
though the preference genuinely saved.
-->
<div id="alchemy-cookie-consent-toast" class="alchemy-cookie-consent-toast alchemy-cookie-consent-hidden" role="status" aria-live="polite" data-alchemy-position="<?php echo esc_attr( $alchemy_position ); ?>" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above; re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>"></div>

<!--
Standalone compact prompt for session-recording/chat-type tools. Shown
independently of the main banner above — it applies to every visitor
regardless of Strict/Light/Exempt tier (see class-alchemy-cookie-consent-public.php
for why this sits outside the geo-tier system), and only appears at all
if the Cookie List has at least one row flagged High-risk. Picks up the
same layout/position as the main banner (no heading of its own, just the
card chrome) for a visually consistent pair.
-->
<div id="alchemy-cookie-consent-highrisk-banner" class="alchemy-cookie-consent-banner alchemy-cookie-consent-highrisk-banner alchemy-cookie-consent-hidden" data-alchemy-layout="<?php echo esc_attr( $alchemy_layout ); ?>" data-alchemy-position="<?php echo esc_attr( $alchemy_position ); ?>" style="<?php echo $alchemy_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_attr() when built above; re-escaping here would double-encode the quotes in a font-family value like "Segoe UI". ?>">
	<div class="alchemy-cookie-consent-inner">
		<p class="alchemy-cookie-consent-message" id="alchemy-cookie-consent-highrisk-standalone-message"></p>
		<div class="alchemy-cookie-consent-actions">
			<button type="button" id="alchemy-cookie-consent-highrisk-decline" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-outline">No Thanks</button>
			<button type="button" id="alchemy-cookie-consent-highrisk-accept" class="alchemy-cookie-consent-btn alchemy-cookie-consent-btn-solid">I Agree</button>
		</div>
	</div>
</div>
