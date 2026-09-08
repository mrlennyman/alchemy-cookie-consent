<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Cookie_Consent_Admin {

	/** @var string Hook suffix returned by add_menu_page(), used to only load color-picker assets on this plugin's own page. */
	private $page_hook;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_general', array( $this, 'save_general' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_style', array( $this, 'save_style' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_categories', array( $this, 'save_categories' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_cookies', array( $this, 'save_cookies' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_policy', array( $this, 'save_policy' ) );
		add_action( 'admin_post_alchemy_cookie_consent_export_log', array( $this, 'export_log' ) );
		add_action( 'admin_post_alchemy_cookie_consent_save_geo', array( $this, 'save_geo' ) );
	}

	public function add_menu() {
		$this->page_hook = add_menu_page( 'Alchemy Cookie Consent', 'Cookie Consent', 'manage_options', 'alchemy-cookie-consent', array( $this, 'render_page' ), 'dashicons-shield', 58 );
	}

	/**
	 * WP's own color picker (used by the Style tab) rather than plain hex
	 * text fields — only loaded on this plugin's own settings page, not
	 * every admin screen.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Shared nonce + capability guard for every admin_post handler below —
	 * a single point of truth instead of five copies that could drift.
	 */
	private function verify_admin_request( $action ) {
		check_admin_referer( $action );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-cookie-consent' ) );
		}
	}

	/**
	 * Shared post-save redirect back to a tab on this settings page.
	 */
	private function redirect_to_tab( $tab ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'alchemy-cookie-consent', 'tab' => $tab, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * sanitize_hex_color() returns null/empty for anything that isn't a
	 * valid #rgb or #rrggbb value, so a malformed submission falls back to
	 * the given default instead of storing something the banner's inline
	 * style can't use.
	 */
	private function sanitize_hex_color_or_default( $posted, $default ) {
		$color = sanitize_hex_color( wp_unslash( $posted ) );
		return $color ? $color : $default;
	}

	/**
	 * Clamped integer, falling back to the given default when missing or
	 * non-numeric (e.g. a field left blank, or a value saved under a
	 * scheme that no longer applies).
	 */
	private function sanitize_px( $posted, $default, $min = 0, $max = 999 ) {
		if ( ! isset( $posted ) || '' === $posted || ! is_numeric( $posted ) ) {
			return $default;
		}
		return max( $min, min( $max, (int) $posted ) );
	}

	/**
	 * Neutralizes leading formula-trigger characters before a value is
	 * written into an exported CSV cell — Excel/Sheets treat a cell
	 * starting with =, +, -, or @ as a formula, and page_url in particular
	 * originates from the public, unauthenticated consent-save endpoint.
	 */
	private function csv_safe( $value ) {
		$value = (string) $value;
		if ( isset( $value[0] ) && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function render_page() {
		// Read-only tab selector for display only, doesn't change state, no nonce needed.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap"><h1>Alchemy Cookie Consent</h1>';
		echo '<h2 class="nav-tab-wrapper">';

		$tabs = array(
			'general'    => 'General',
			'style'      => 'Style',
			'categories' => 'Categories',
			'cookies'    => 'Cookie List',
			'policy'     => 'Policy Page',
			'log'        => 'Consent Log',
			'geo'        => 'Geo Targeting',
		);

		foreach ( $tabs as $key => $label ) {
			$class = ( $tab === $key ) ? ' nav-tab-active' : '';
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'alchemy-cookie-consent', 'tab' => $key ), admin_url( 'admin.php' ) ) ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'style':
				$this->render_style_tab();
				break;
			case 'categories':
				$this->render_categories_tab();
				break;
			case 'cookies':
				$this->render_cookies_tab();
				break;
			case 'policy':
				$this->render_policy_tab();
				break;
			case 'log':
				$this->render_log_tab();
				break;
			case 'geo':
				$this->render_geo_tab();
				break;
			default:
				$this->render_general_tab();
		}

		echo '</div>';
	}

	private function render_general_tab() {
		$settings = get_option( 'alchemy_cookie_consent_settings' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_general">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_general' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="heading_text">Heading</label></th>
					<td>
						<input type="text" name="heading_text" id="heading_text" value="<?php echo esc_attr( isset( $settings['heading_text'] ) ? $settings['heading_text'] : 'We use cookies' ); ?>" class="regular-text">
						<p class="description">Only shown when the Style tab's Layout is set to "Card".</p>
					</td>
				</tr>
				<tr>
					<th><label for="banner_message">Banner message</label></th>
					<td><textarea name="banner_message" id="banner_message" rows="3" class="large-text"><?php echo esc_textarea( $settings['banner_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="accept_label">Accept button label</label></th>
					<td><input type="text" name="accept_label" id="accept_label" value="<?php echo esc_attr( $settings['accept_label'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="reject_label">Reject button label</label></th>
					<td><input type="text" name="reject_label" id="reject_label" value="<?php echo esc_attr( $settings['reject_label'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="customize_label">Customize button label</label></th>
					<td><input type="text" name="customize_label" id="customize_label" value="<?php echo esc_attr( $settings['customize_label'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="policy_page_id">Cookie/Privacy Policy page</label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'policy_page_id',
								'id'                => 'policy_page_id',
								'show_option_none'  => '— None selected —',
								'option_none_value' => '0',
								'selected'          => isset( $settings['policy_page_id'] ) ? absint( $settings['policy_page_id'] ) : 0,
							)
						);
						?>
						<p class="description">Picked by page, not URL — so the link resolves correctly per site rather than needing a hardcoded address per client. Shown as a link inside the banner; leave unset to hide it.</p>
					</td>
				</tr>
			</table>
			<p class="description">Colors, fonts, and other appearance settings have moved to the <strong>Style</strong> tab.</p>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}

	/**
	 * One Font + Weight picker pair, shared by the Heading and Body sections
	 * of the Style tab below so the two don't drift in markup. $role is the
	 * settings-key prefix ("heading" or "body"); the font-size field is
	 * rendered separately per caller since its min/max differ by role.
	 */
	private function render_font_weight_rows( $role, $label, $s ) {
		$google_fonts = Alchemy_Cookie_Consent_Activator::google_fonts();
		$weights      = Alchemy_Cookie_Consent_Activator::font_weight_options();
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $role ); ?>_font"><?php echo esc_html( $label ); ?> font</label></th>
			<td>
				<select name="<?php echo esc_attr( $role ); ?>_font" id="<?php echo esc_attr( $role ); ?>_font">
					<?php foreach ( $google_fonts as $key => $font ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s[ $role . '_font' ], $key ); ?>><?php echo esc_html( $font['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( 'button' !== $role ) : ?>
					<p class="description">Non-system choices load a Google Font.</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="<?php echo esc_attr( $role ); ?>_weight"><?php echo esc_html( $label ); ?> weight</label></th>
			<td>
				<select name="<?php echo esc_attr( $role ); ?>_weight" id="<?php echo esc_attr( $role ); ?>_weight">
					<?php foreach ( $weights as $value => $weight_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (int) $s[ $role . '_weight' ], $value ); ?>><?php echo esc_html( $weight_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Every field here writes into the same flat $settings array as the
	 * General tab (no separate nested 'style' option) — grouped onto its
	 * own tab purely for a cleaner admin UI, not a different storage
	 * scheme. Defaults come from Alchemy_Cookie_Consent_Activator::style_defaults()
	 * so this form, the save handler below, and the banner template's CSS
	 * variables can't drift out of sync.
	 */
	private function render_style_tab() {
		$settings = get_option( 'alchemy_cookie_consent_settings' );
		$d        = Alchemy_Cookie_Consent_Activator::style_defaults();
		$s        = array_merge( $d, array_intersect_key( $settings, $d ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_style">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_style' ); ?>

			<h2 class="title">Layout</h2>
			<table class="form-table">
				<tr>
					<th><label for="layout_preset">Preset</label></th>
					<td>
						<select name="layout_preset" id="layout_preset">
							<?php foreach ( Alchemy_Cookie_Consent_Activator::layout_presets() as $key => $layout ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['layout_preset'], $key ); ?>><?php echo esc_html( $layout['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php foreach ( Alchemy_Cookie_Consent_Activator::layout_presets() as $key => $layout ) : ?>
							<p class="description alchemy-cookie-consent-layout-desc" data-layout="<?php echo esc_attr( $key ); ?>" <?php echo ( $s['layout_preset'] !== $key ) ? 'hidden' : ''; ?>><?php echo esc_html( $layout['description'] ); ?></p>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr class="alchemy-cookie-consent-card-only" <?php echo ( 'card' !== $s['layout_preset'] ) ? 'style="display:none;"' : ''; ?>>
					<th><label for="card_position">Card position</label></th>
					<td>
						<select name="card_position" id="card_position">
							<option value="left" <?php selected( $s['card_position'], 'left' ); ?>>Bottom left</option>
							<option value="right" <?php selected( $s['card_position'], 'right' ); ?>>Bottom right</option>
						</select>
					</td>
				</tr>
			</table>

			<h2 class="title">Heading</h2>
			<p class="description">Only shown in the Card layout. Text is set on the General tab.</p>
			<table class="form-table">
				<?php $this->render_font_weight_rows( 'heading', 'Heading', $s ); ?>
				<tr>
					<th><label for="heading_font_size">Font size (px)</label></th>
					<td><input type="number" name="heading_font_size" id="heading_font_size" value="<?php echo esc_attr( $s['heading_font_size'] ); ?>" min="14" max="32" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="heading_color">Color</label></th>
					<td><input type="text" name="heading_color" id="heading_color" value="<?php echo esc_attr( $s['heading_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
			</table>

			<h2 class="title">Body text</h2>
			<p class="description">The banner message and category labels, in both layouts.</p>
			<table class="form-table">
				<?php $this->render_font_weight_rows( 'body', 'Body', $s ); ?>
				<tr>
					<th><label for="body_font_size">Font size (px)</label></th>
					<td><input type="number" name="body_font_size" id="body_font_size" value="<?php echo esc_attr( $s['body_font_size'] ); ?>" min="10" max="24" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="body_color">Color</label></th>
					<td><input type="text" name="body_color" id="body_color" value="<?php echo esc_attr( $s['body_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
			</table>

			<h2 class="title">Buttons</h2>
			<table class="form-table">
				<?php $this->render_font_weight_rows( 'button', 'Button', $s ); ?>
				<tr>
					<th><label for="accent_color">Accent / button color</label></th>
					<td>
						<input type="text" name="accent_color" id="accent_color" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="alchemy-cookie-consent-color-field">
						<p class="description">The solid Accept button, links, and the standalone prompt's "I Agree" button.</p>
					</td>
				</tr>
				<tr>
					<th><label for="button_hover_color">Button hover color</label></th>
					<td><input type="text" name="button_hover_color" id="button_hover_color" value="<?php echo esc_attr( $s['button_hover_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="button_text_color">Button text color</label></th>
					<td><input type="text" name="button_text_color" id="button_text_color" value="<?php echo esc_attr( $s['button_text_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="button_outline_color">Outline button border/text</label></th>
					<td>
						<input type="text" name="button_outline_color" id="button_outline_color" value="<?php echo esc_attr( $s['button_outline_color'] ); ?>" class="alchemy-cookie-consent-color-field">
						<p class="description">The Reject button, and the standalone prompt's "No Thanks" button.</p>
					</td>
				</tr>
				<tr>
					<th><label for="button_outline_hover_bg">Outline button hover background</label></th>
					<td><input type="text" name="button_outline_hover_bg" id="button_outline_hover_bg" value="<?php echo esc_attr( $s['button_outline_hover_bg'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="button_radius">Corner radius (px)</label></th>
					<td><input type="number" name="button_radius" id="button_radius" value="<?php echo esc_attr( $s['button_radius'] ); ?>" min="0" max="40" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="button_font_size">Font size (px)</label></th>
					<td><input type="number" name="button_font_size" id="button_font_size" value="<?php echo esc_attr( $s['button_font_size'] ); ?>" min="10" max="24" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="button_padding">Padding (px)</label></th>
					<td>
						<input type="number" name="button_padding" id="button_padding" value="<?php echo esc_attr( $s['button_padding'] ); ?>" min="4" max="40" step="1" class="small-text">
						<p class="description">Vertical padding — horizontal is always double this.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Container</h2>
			<table class="form-table">
				<tr>
					<th><label for="container_bg_color">Background color</label></th>
					<td><input type="text" name="container_bg_color" id="container_bg_color" value="<?php echo esc_attr( $s['container_bg_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="container_border_color">Top border color</label></th>
					<td><input type="text" name="container_border_color" id="container_border_color" value="<?php echo esc_attr( $s['container_border_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="container_padding">Padding (px)</label></th>
					<td><input type="number" name="container_padding" id="container_padding" value="<?php echo esc_attr( $s['container_padding'] ); ?>" min="0" max="80" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th>Shadow</th>
					<td><label><input type="checkbox" name="shadow_enabled" <?php checked( ! empty( $s['shadow_enabled'] ) ); ?>> Enabled</label></td>
				</tr>
				<tr>
					<th><label for="shadow_color">Shadow color</label></th>
					<td><input type="text" name="shadow_color" id="shadow_color" value="<?php echo esc_attr( $s['shadow_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="shadow_opacity">Shadow opacity (%)</label></th>
					<td><input type="number" name="shadow_opacity" id="shadow_opacity" value="<?php echo esc_attr( $s['shadow_opacity'] ); ?>" min="0" max="100" step="1" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="shadow_blur">Shadow blur (px)</label></th>
					<td><input type="number" name="shadow_blur" id="shadow_blur" value="<?php echo esc_attr( $s['shadow_blur'] ); ?>" min="0" max="100" step="1" class="small-text"></td>
				</tr>
			</table>

			<h2 class="title">Revisit button</h2>
			<p class="description">The floating pill button that lets a returning visitor reopen the banner after they've already made a choice.</p>
			<table class="form-table">
				<tr>
					<th><label for="revisit_bg_color">Color</label></th>
					<td><input type="text" name="revisit_bg_color" id="revisit_bg_color" value="<?php echo esc_attr( $s['revisit_bg_color'] ); ?>" class="alchemy-cookie-consent-color-field"></td>
				</tr>
				<tr>
					<th><label for="revisit_opacity">Opacity at rest (%)</label></th>
					<td>
						<input type="number" name="revisit_opacity" id="revisit_opacity" value="<?php echo esc_attr( $s['revisit_opacity'] ); ?>" min="0" max="100" step="5" class="small-text">
						<p class="description">Lower = more see-through.</p>
					</td>
				</tr>
				<tr>
					<th><label for="revisit_hover_opacity">Opacity on hover (%)</label></th>
					<td>
						<input type="number" name="revisit_hover_opacity" id="revisit_hover_opacity" value="<?php echo esc_attr( $s['revisit_hover_opacity'] ); ?>" min="0" max="100" step="5" class="small-text">
						<p class="description">Default 100 (fully solid) so it's still a clear, findable target once someone's looking for it.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Style' ); ?>
		</form>
		<script>
		jQuery( function ( $ ) {
			$( '.alchemy-cookie-consent-color-field' ).wpColorPicker();

			$( '#layout_preset' ).on( 'change', function () {
				var layout = this.value;
				$( '.alchemy-cookie-consent-layout-desc' ).prop( 'hidden', true );
				$( '.alchemy-cookie-consent-layout-desc[data-layout="' + layout + '"]' ).prop( 'hidden', false );
				$( '.alchemy-cookie-consent-card-only' ).toggle( 'card' === layout );
			} ).trigger( 'change' );
		} );
		</script>
		<?php
	}

	private function render_categories_tab() {
		$settings = get_option( 'alchemy_cookie_consent_settings' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_categories">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_categories' ); ?>
			<table class="form-table">
				<tr>
					<th>Necessary</th>
					<td><input type="checkbox" checked disabled> Always on — can't be disabled</td>
				</tr>
				<tr>
					<th>Analytics</th>
					<td><label><input type="checkbox" name="cat_analytics" <?php checked( ! empty( $settings['categories_enabled']['analytics'] ) ); ?>> Show this category on the banner</label></td>
				</tr>
				<tr>
					<th>Marketing</th>
					<td><label><input type="checkbox" name="cat_marketing" <?php checked( ! empty( $settings['categories_enabled']['marketing'] ) ); ?>> Show this category on the banner — only needed if this client runs Google Ads or similar remarketing</label></td>
				</tr>
			</table>
			<?php submit_button( 'Save Categories' ); ?>
		</form>
		<?php
	}

	private function render_geo_tab() {
		$settings = get_option( 'alchemy_cookie_consent_settings' );
		$strict   = isset( $settings['strict_countries'] ) ? $settings['strict_countries'] : Alchemy_Cookie_Consent_Activator::default_strict_countries();
		$light    = isset( $settings['light_countries'] ) ? $settings['light_countries'] : Alchemy_Cookie_Consent_Activator::default_light_countries();
		?>
		<p>When enabled, first-time visitors are checked against these lists before the banner decides how to behave:</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>Strict</strong> — country in the Strict list, or country can't be determined — normal blocking banner, visitor must choose (opt-in).</li>
			<li><strong>Light</strong> — country in the Light list — banner doesn't interrupt them; enabled categories are granted automatically, but the cookie-settings button appears immediately so they can still opt out.</li>
			<li><strong>Exempt</strong> — everyone else — enabled categories are granted automatically and <em>nothing</em> is shown at all, not even the button. For regions with no consent requirement at all.</li>
		</ul>
		<p class="description">Detection failure always falls back to Strict — it never accidentally relaxes the banner for someone it couldn't identify.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_geo">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_geo' ); ?>
			<table class="form-table">
				<tr>
					<th>Enable geo-targeting</th>
					<td><label><input type="checkbox" name="geo_targeting_enabled" <?php checked( ! empty( $settings['geo_targeting_enabled'] ) ); ?>> Off by default — leaves the current block-everywhere behaviour unchanged until turned on for this site.</label></td>
				</tr>
				<tr>
					<th><label for="strict_countries">Strict (opt-in) countries</label></th>
					<td>
						<textarea name="strict_countries" id="strict_countries" rows="3" class="large-text" style="font-family: monospace;"><?php echo esc_textarea( $strict ); ?></textarea>
						<p class="description">Comma-separated ISO country codes. Pre-filled with EU/EEA + UK + Canada.</p>
					</td>
				</tr>
				<tr>
					<th><label for="light_countries">Light (opt-out, visible) countries</label></th>
					<td>
						<textarea name="light_countries" id="light_countries" rows="2" class="large-text" style="font-family: monospace;"><?php echo esc_textarea( $light ); ?></textarea>
						<p class="description">Defaults to the whole US, covering the 20 states with opt-out privacy laws — country-level detection can't isolate individual states. Anything not in Strict or Light falls through to Exempt.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Geo Targeting' ); ?>
		</form>
		<?php
	}

	private function render_cookies_tab() {
		$cookies = get_option( 'alchemy_cookie_consent_cookie_list', array() );
		?>
		<p>This list drives the <code>[alchemy_cookie_policy]</code> shortcode on your policy page. Add or edit rows for whatever this site actually runs.</p>
		<p class="description">"High-risk" tools (session recording, live chat) get their own always-on consent prompt shown to every visitor regardless of geo-targeting tier — separate from the Necessary/Analytics/Marketing categories above, since the legal question for those tools (CIPA-style "wiretap" risk) turns on consent timing, not visitor location.</p>
		<p>
			<label for="alchemy-cookie-consent-common-service">Quick-add a common service:</label>
			<select id="alchemy-cookie-consent-common-service">
				<option value="">— Select —</option>
				<?php foreach ( $this->get_common_services() as $key => $svc ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $svc['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="alchemy-cookie-consent-add-common">Add</button>
			<span class="description"> — fills in the row with the standard category/purpose so it isn't re-researched per client; edit before saving if this client's use differs.</span>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_cookies">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_cookies' ); ?>
			<table class="widefat" id="alchemy-cookie-consent-cookie-table">
				<thead>
					<tr><th>Cookie name</th><th>Category</th><th>Purpose</th><th>Duration</th><th>High-risk</th><th>Visitor notice</th><th></th></tr>
				</thead>
				<tbody>
				<?php foreach ( $cookies as $i => $c ) : ?>
					<tr>
						<td><input type="text" name="cookies[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $c['name'] ); ?>" class="regular-text"></td>
						<td>
							<select name="cookies[<?php echo (int) $i; ?>][category]">
								<?php foreach ( array( 'necessary', 'analytics', 'marketing' ) as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $c['category'], $cat ); ?>><?php echo esc_html( ucfirst( $cat ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" name="cookies[<?php echo (int) $i; ?>][purpose]" value="<?php echo esc_attr( $c['purpose'] ); ?>" class="regular-text"></td>
						<td><input type="text" name="cookies[<?php echo (int) $i; ?>][duration]" value="<?php echo esc_attr( $c['duration'] ); ?>" class="small-text"></td>
						<td style="text-align:center;"><input type="checkbox" name="cookies[<?php echo (int) $i; ?>][high_risk]" value="1" <?php checked( ! empty( $c['high_risk'] ) ); ?>></td>
						<td><input type="text" name="cookies[<?php echo (int) $i; ?>][notice]" value="<?php echo esc_attr( isset( $c['notice'] ) ? $c['notice'] : '' ); ?>" class="regular-text" placeholder="Shown to visitors if High-risk is checked"></td>
						<td><button type="button" class="button alchemy-cookie-consent-remove-row">Remove</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="alchemy-cookie-consent-add-row">+ Add cookie</button></p>
			<?php submit_button( 'Save Cookie List' ); ?>
		</form>
		<script>
		( function () {
			var commonServices = <?php echo wp_json_encode( $this->get_common_services() ); ?>;

			function addRow( prefill ) {
				var tbody = document.querySelector( '#alchemy-cookie-consent-cookie-table tbody' );
				var i = tbody.children.length;
				var name = prefill ? prefill.name : '';
				var category = prefill ? prefill.category : 'necessary';
				var purpose = prefill ? prefill.purpose : '';
				var duration = prefill ? prefill.duration : '';
				var highRisk = prefill ? !! prefill.high_risk : false;
				var notice = prefill ? ( prefill.notice || '' ) : '';
				var row = document.createElement( 'tr' );
				row.innerHTML =
					'<td><input type="text" name="cookies[' + i + '][name]" value="' + name + '" class="regular-text"></td>' +
					'<td><select name="cookies[' + i + '][category]">' +
						'<option value="necessary"' + ( category === 'necessary' ? ' selected' : '' ) + '>Necessary</option>' +
						'<option value="analytics"' + ( category === 'analytics' ? ' selected' : '' ) + '>Analytics</option>' +
						'<option value="marketing"' + ( category === 'marketing' ? ' selected' : '' ) + '>Marketing</option>' +
					'</select></td>' +
					'<td><input type="text" name="cookies[' + i + '][purpose]" value="' + purpose + '" class="regular-text"></td>' +
					'<td><input type="text" name="cookies[' + i + '][duration]" value="' + duration + '" class="small-text"></td>' +
					'<td style="text-align:center;"><input type="checkbox" name="cookies[' + i + '][high_risk]" value="1"' + ( highRisk ? ' checked' : '' ) + '></td>' +
					'<td><input type="text" name="cookies[' + i + '][notice]" value="' + notice + '" class="regular-text" placeholder="Shown to visitors if High-risk is checked"></td>' +
					'<td><button type="button" class="button alchemy-cookie-consent-remove-row">Remove</button></td>';
				tbody.appendChild( row );
			}

			document.getElementById( 'alchemy-cookie-consent-add-common' ).addEventListener( 'click', function () {
				var key = document.getElementById( 'alchemy-cookie-consent-common-service' ).value;
				if ( key && commonServices[ key ] ) {
					addRow( commonServices[ key ] );
				}
			} );

			document.getElementById( 'alchemy-cookie-consent-add-row' ).addEventListener( 'click', function () {
				addRow( null );
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'alchemy-cookie-consent-remove-row' ) ) {
					e.target.closest( 'tr' ).remove();
				}
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * The categorisation reference sheet, kept as data instead of a
	 * separate document so it can't drift out of sync with what the
	 * Cookie List tab actually offers. Extend this list as new tools
	 * come up across the portfolio rather than re-researching each time.
	 *
	 * high_risk / notice: session-replay and chat tools default to
	 * flagged, per the CIPA-style wiretap theory (capturing keystrokes,
	 * mouse movement, or message content before consent) — a different
	 * legal question from the Necessary/Analytics/Marketing categories,
	 * so they get their own always-ask prompt regardless of geo tier.
	 * Ad/analytics pixels (Bing UET, Facebook Pixel) are named more
	 * loosely in that case law, so left off by default — a judgment
	 * call to flip on per client if wanted, not forced.
	 */
	private function get_common_services() {
		return array(
			'bing_uet'      => array(
				'label'     => 'Bing UET (Microsoft Ads)',
				'name'      => '_uetsid / _uetvid',
				'category'  => 'marketing',
				'purpose'   => 'Microsoft Bing Ads — tracks conversions and enables remarketing. Not supported by Site Kit; needs a GTM tag wired to the alchemy_cookie_consent_marketing signal.',
				'duration'  => 'Session / 13 months',
				'high_risk' => false,
				'notice'    => '',
			),
			'facebook_pixel' => array(
				'label'     => 'Facebook / Meta Pixel',
				'name'      => '_fbp',
				'category'  => 'marketing',
				'purpose'   => 'Meta Pixel — tracks conversions and enables retargeting.',
				'duration'  => '3 months',
				'high_risk' => false,
				'notice'    => '',
			),
			'hotjar'        => array(
				'label'     => 'Hotjar',
				'name'      => '_hjSession_* / _hjSessionUser_*',
				'category'  => 'analytics',
				'purpose'   => 'Hotjar — session recording and heatmaps.',
				'duration'  => '30 min / 1 year',
				'high_risk' => true,
				'notice'    => "This site uses screen recording software to see how visitors interact with our pages, including mouse movements and clicks. Recording won't start unless you agree.",
			),
			'clarity'       => array(
				'label'     => 'Microsoft Clarity',
				'name'      => '_clck / _clsk',
				'category'  => 'analytics',
				'purpose'   => 'Microsoft Clarity — session recording and heatmaps.',
				'duration'  => '1 year / 1 day',
				'high_risk' => true,
				'notice'    => "This site uses screen recording software to see how visitors interact with our pages, including mouse movements and clicks. Recording won't start unless you agree.",
			),
			'live_chat'     => array(
				'label'     => 'Live chat widget (generic)',
				'name'      => '(varies by vendor)',
				'category'  => 'necessary',
				'purpose'   => 'Live chat — remembers your conversation while you\'re actively chatting. Recategorise as Analytics/Marketing if this vendor also profiles visitors who never open the chat.',
				'duration'  => 'Session',
				'high_risk' => true,
				'notice'    => 'This site offers live chat through a third-party provider. If you start a chat, your messages are shared with that provider. You can still browse the site without using chat.',
			),
		);
	}

	/**
	 * Builds the copy-paste policy page text. Deliberately leaves the
	 * shortcodes as literal [bracket] text rather than expanding them —
	 * the whole point is that they stay live on the pasted page, so
	 * editing the Cookie List later updates the published page
	 * automatically without needing to regenerate or re-paste anything.
	 * Nothing here is pre-escaped: the caller wraps the whole result in
	 * esc_textarea() once, so double-escaping (e.g. a "&" in the contact
	 * email becoming "&amp;amp;") isn't a risk to guard against here.
	 */
	private function build_policy_page_html( $include_high_risk, $include_ccpa, $contact_email ) {
		$last_updated = date_i18n( 'F Y' );
		$lines        = array();

		$lines[] = '<h1>Cookie Policy</h1>';
		$lines[] = '<em>Last updated: ' . $last_updated . '</em>';
		$lines[] = '';
		$lines[] = '<h2>What are cookies?</h2>';
		$lines[] = "<p>Cookies are small text files that a website stores on your device when you visit. They help the site remember information about your visit — like whether you're logged in, what's in your cart, or how you like the site set up — and, where you've agreed to it, help us understand how visitors use the site.</p>";
		$lines[] = '';
		$lines[] = '<h2>How we use cookies</h2>';
		$lines[] = '<p>We use cookies for three broad purposes:</p>';
		$lines[] = '<ul>';
		$lines[] = "\t<li><strong>Necessary</strong> — required for the site to function (staying logged in, keeping items in your cart). These can't be switched off.</li>";
		$lines[] = "\t<li><strong>Analytics</strong> — help us understand how visitors use the site, so we can improve it. Only run if you opt in.</li>";
		$lines[] = "\t<li><strong>Marketing</strong> — support advertising and retargeting. Only run if you opt in, and only if this site actually uses them.</li>";
		$lines[] = '</ul>';
		$lines[] = '<p>Nothing outside Necessary runs before you make a choice in the banner.</p>';
		$lines[] = '';

		if ( $include_high_risk ) {
			$lines[] = '<h2>Session recording and live chat</h2>';
			$lines[] = '<p>Some tools on this site — such as session recording software or live chat — ask for your consent separately from the categories above, the moment you first arrive, regardless of your location. Recording or chat logging never starts until you respond to that prompt.</p>';
			$lines[] = '';
		}

		$lines[] = '<h2>Cookies we use</h2>';
		$lines[] = '[alchemy_cookie_policy]';
		$lines[] = '';
		$lines[] = '<h2>Third-party services</h2>';
		$lines[] = '<p>Some cookies are set by services we use rather than by us directly — see the list above for the specific services this site runs, and each one\'s own privacy policy for how it handles data.</p>';
		$lines[] = '';
		$lines[] = '<h2>Managing your preferences</h2>';
		$lines[] = '<p>You can change your choice at any time: [alchemy_cookie_consent_settings_link]</p>';
		$lines[] = "<p>Your choice is remembered for 6 months, or until you clear your browser's cookies — after that, you'll be asked again.</p>";
		$lines[] = '';

		if ( $include_ccpa ) {
			$lines[] = '<h2>Do Not Sell or Share My Personal Information</h2>';
			$lines[] = "<p>If you're a California resident, you have the right to opt out of the sale or sharing of your personal information: [alchemy_privacy_choices]. We also automatically honor the Global Privacy Control signal if your browser sends one.</p>";
			$lines[] = '';
		}

		$lines[] = '<h2>How we store your choice</h2>';
		$lines[] = "<p>Your preference is saved in a cookie in your own browser. We also keep a record of the choice made (which categories were accepted, and when) so we can demonstrate compliance if asked. This record doesn't include your name or a way to directly identify you — only a one-way hashed version of your IP address, which can't be reversed back to the original.</p>";
		$lines[] = '';
		$lines[] = '<h2>Your right to complain</h2>';
		$lines[] = '<p>If you believe your data has been handled incorrectly, you have the right to complain to the data protection authority relevant to where you live:</p>';
		$lines[] = '';
		$lines[] = '[alchemy_regulatory_links]';
		$lines[] = '';
		$lines[] = '<h2>Questions?</h2>';
		$lines[] = '<p>Contact us at <a href="mailto:' . $contact_email . '">' . $contact_email . '</a> with any questions about this policy.</p>';

		return implode( "\n", $lines );
	}

	private function render_policy_tab() {
		$settings      = get_option( 'alchemy_cookie_consent_settings' );
		$include_ccpa  = isset( $settings['policy_include_ccpa'] ) ? ! empty( $settings['policy_include_ccpa'] ) : true;
		$contact_email = ! empty( $settings['policy_contact_email'] ) ? $settings['policy_contact_email'] : get_option( 'admin_email' );

		$cookies           = get_option( 'alchemy_cookie_consent_cookie_list', array() );
		$include_high_risk = false;
		foreach ( $cookies as $c ) {
			if ( ! empty( $c['high_risk'] ) ) {
				$include_high_risk = true;
				break;
			}
		}

		$page_html = $this->build_policy_page_html( $include_high_risk, $include_ccpa, $contact_email );
		?>
		<p>Generates a ready-to-paste Cookie/Privacy Policy page from the current Cookie List and settings below. The shortcodes inside stay live once pasted — editing the Cookie List later updates the published page automatically, with nothing to regenerate or re-paste.</p>
		<?php if ( $include_high_risk ) : ?>
			<p class="description">The Cookie List has at least one row flagged High-risk, so the session-recording/chat section is included automatically below.</p>
		<?php else : ?>
			<p class="description">No Cookie List row is currently flagged High-risk, so that section is left out. Flag one on the Cookie List tab, then revisit this tab to include it.</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_cookie_consent_save_policy">
			<?php wp_nonce_field( 'alchemy_cookie_consent_save_policy' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="policy_contact_email">Contact email</label></th>
					<td>
						<input type="email" name="policy_contact_email" id="policy_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" class="regular-text">
						<p class="description">Defaults to this site's admin email unless changed here.</p>
					</td>
				</tr>
				<tr>
					<th>California (CCPA)</th>
					<td><label><input type="checkbox" name="policy_include_ccpa" <?php checked( $include_ccpa ); ?>> Include the "Do Not Sell or Share" section — leave this on unless this site clearly has no US/California visitors.</label></td>
				</tr>
			</table>
			<?php submit_button( 'Save &amp; Regenerate' ); ?>
		</form>

		<h2 class="title">Copy this into a new page</h2>
		<textarea id="alchemy-cookie-consent-policy-output" readonly rows="30" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea( $page_html ); ?></textarea>
		<p>
			<button type="button" class="button button-primary" id="alchemy-cookie-consent-copy-policy">Copy to Clipboard</button>
			<span id="alchemy-cookie-consent-copy-confirm" style="display:none; color: #2271b1; margin-left: 8px;">Copied!</span>
		</p>
		<script>
		document.getElementById( 'alchemy-cookie-consent-copy-policy' ).addEventListener( 'click', function () {
			var textarea = document.getElementById( 'alchemy-cookie-consent-policy-output' );
			textarea.select();
			navigator.clipboard.writeText( textarea.value ).then( function () {
				var confirmEl = document.getElementById( 'alchemy-cookie-consent-copy-confirm' );
				confirmEl.style.display = 'inline';
				setTimeout( function () { confirmEl.style.display = 'none'; }, 2000 );
			} );
		} );
		</script>
		<?php
	}

	public function save_policy() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_policy' );

		$settings                          = get_option( 'alchemy_cookie_consent_settings' );
		$settings['policy_include_ccpa']   = ! empty( $_POST['policy_include_ccpa'] );
		$posted_email                      = isset( $_POST['policy_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['policy_contact_email'] ) ) : '';
		$settings['policy_contact_email']  = is_email( $posted_email ) ? $posted_email : '';

		update_option( 'alchemy_cookie_consent_settings', $settings );
		$this->redirect_to_tab( 'policy' );
	}

	private function render_log_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_cookie_consent_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table; a live consent-audit listing shouldn't be cached.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY consent_time DESC LIMIT %d', $table, 200 ) );
		?>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alchemy_cookie_consent_export_log' ), 'alchemy_cookie_consent_export_log' ) ); ?>" class="button">Export CSV</a>
			<span class="description"> — showing the most recent 200 records; export for the full log.</span>
		</p>
		<table class="widefat striped">
			<thead><tr><th>Date/time</th><th>Categories accepted</th><th>Source</th><th>Page</th></tr></thead>
			<tbody>
			<?php if ( $rows ) : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->consent_time ); ?></td>
						<td><?php echo esc_html( implode( ', ', json_decode( $row->categories, true ) ?: array() ) ); ?></td>
						<td><?php echo esc_html( ! empty( $row->source ) ? $row->source : 'explicit' ); ?></td>
						<td><?php echo esc_html( $row->page_url ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">No consent records yet.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	public function save_geo() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_geo' );

		$settings                          = get_option( 'alchemy_cookie_consent_settings' );
		$settings['geo_targeting_enabled'] = ! empty( $_POST['geo_targeting_enabled'] );

		// A submitted-but-empty field (e.g. the textarea got cleared before
		// saving) must fall back to the safe default just like a missing
		// field does — otherwise it silently empties the Strict list and
		// relaxes the banner everywhere, the opposite of "never accidentally
		// relax" that geo-targeting is built around.
		$strict_input                 = isset( $_POST['strict_countries'] ) ? sanitize_text_field( wp_unslash( $_POST['strict_countries'] ) ) : '';
		$settings['strict_countries'] = '' !== $strict_input ? strtoupper( $strict_input ) : Alchemy_Cookie_Consent_Activator::default_strict_countries();

		$light_input                  = isset( $_POST['light_countries'] ) ? sanitize_text_field( wp_unslash( $_POST['light_countries'] ) ) : '';
		$settings['light_countries']  = '' !== $light_input ? strtoupper( $light_input ) : Alchemy_Cookie_Consent_Activator::default_light_countries();

		update_option( 'alchemy_cookie_consent_settings', $settings );
		$this->redirect_to_tab( 'geo' );
	}

	public function save_general() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_general' );

		$settings                    = get_option( 'alchemy_cookie_consent_settings' );
		$settings['heading_text']    = isset( $_POST['heading_text'] ) ? sanitize_text_field( wp_unslash( $_POST['heading_text'] ) ) : 'We use cookies';
		$settings['banner_message']  = isset( $_POST['banner_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['banner_message'] ) ) : '';
		$settings['accept_label']    = isset( $_POST['accept_label'] ) ? sanitize_text_field( wp_unslash( $_POST['accept_label'] ) ) : 'Accept All';
		$settings['reject_label']    = isset( $_POST['reject_label'] ) ? sanitize_text_field( wp_unslash( $_POST['reject_label'] ) ) : 'Reject All';
		$settings['customize_label'] = isset( $_POST['customize_label'] ) ? sanitize_text_field( wp_unslash( $_POST['customize_label'] ) ) : 'Customize';
		$settings['policy_page_id']  = isset( $_POST['policy_page_id'] ) ? absint( $_POST['policy_page_id'] ) : 0;

		update_option( 'alchemy_cookie_consent_settings', $settings );
		$this->redirect_to_tab( 'general' );
	}

	/**
	 * Validates a posted font key against the curated Google Fonts list,
	 * falling back to the given default rather than storing an arbitrary
	 * string the CSS-variable resolver wouldn't recognise.
	 */
	private function sanitize_font_choice( $posted, $default ) {
		$fonts = Alchemy_Cookie_Consent_Activator::google_fonts();
		$key   = isset( $posted ) ? sanitize_text_field( wp_unslash( $posted ) ) : '';
		return isset( $fonts[ $key ] ) ? $key : $default;
	}

	/**
	 * Validates a posted weight against the fixed weight-option list, same
	 * reasoning as sanitize_font_choice() above.
	 */
	private function sanitize_font_weight( $posted, $default ) {
		$weights = Alchemy_Cookie_Consent_Activator::font_weight_options();
		$value   = isset( $posted ) ? (int) $posted : 0;
		return isset( $weights[ $value ] ) ? $value : $default;
	}

	public function save_style() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_style' );

		$settings = get_option( 'alchemy_cookie_consent_settings' );
		$d        = Alchemy_Cookie_Consent_Activator::style_defaults();

		$layouts                  = Alchemy_Cookie_Consent_Activator::layout_presets();
		$posted_layout            = isset( $_POST['layout_preset'] ) ? sanitize_key( wp_unslash( $_POST['layout_preset'] ) ) : '';
		$settings['layout_preset'] = isset( $layouts[ $posted_layout ] ) ? $posted_layout : $d['layout_preset'];
		$posted_position          = isset( $_POST['card_position'] ) ? sanitize_key( wp_unslash( $_POST['card_position'] ) ) : '';
		$settings['card_position'] = in_array( $posted_position, array( 'left', 'right' ), true ) ? $posted_position : $d['card_position'];

		$settings['heading_font']      = $this->sanitize_font_choice( $_POST['heading_font'] ?? null, $d['heading_font'] );
		$settings['heading_weight']    = $this->sanitize_font_weight( $_POST['heading_weight'] ?? null, $d['heading_weight'] );
		$settings['heading_font_size'] = $this->sanitize_px( $_POST['heading_font_size'] ?? null, $d['heading_font_size'], 14, 32 );
		$settings['heading_color']     = isset( $_POST['heading_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['heading_color'], $d['heading_color'] ) : $d['heading_color'];

		$settings['body_font']      = $this->sanitize_font_choice( $_POST['body_font'] ?? null, $d['body_font'] );
		$settings['body_weight']    = $this->sanitize_font_weight( $_POST['body_weight'] ?? null, $d['body_weight'] );
		$settings['body_font_size'] = $this->sanitize_px( $_POST['body_font_size'] ?? null, $d['body_font_size'], 10, 24 );
		$settings['body_color']     = isset( $_POST['body_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['body_color'], $d['body_color'] ) : $d['body_color'];

		$settings['button_font']   = $this->sanitize_font_choice( $_POST['button_font'] ?? null, $d['button_font'] );
		$settings['button_weight'] = $this->sanitize_font_weight( $_POST['button_weight'] ?? null, $d['button_weight'] );

		$settings['accent_color']            = isset( $_POST['accent_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['accent_color'], $d['accent_color'] ) : $d['accent_color'];
		$settings['button_hover_color']      = isset( $_POST['button_hover_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['button_hover_color'], $d['button_hover_color'] ) : $d['button_hover_color'];
		$settings['button_text_color']       = isset( $_POST['button_text_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['button_text_color'], $d['button_text_color'] ) : $d['button_text_color'];
		$settings['button_outline_color']    = isset( $_POST['button_outline_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['button_outline_color'], $d['button_outline_color'] ) : $d['button_outline_color'];
		$settings['button_outline_hover_bg'] = isset( $_POST['button_outline_hover_bg'] ) ? $this->sanitize_hex_color_or_default( $_POST['button_outline_hover_bg'], $d['button_outline_hover_bg'] ) : $d['button_outline_hover_bg'];
		$settings['button_radius']           = $this->sanitize_px( $_POST['button_radius'] ?? null, $d['button_radius'], 0, 40 );
		$settings['button_font_size']        = $this->sanitize_px( $_POST['button_font_size'] ?? null, $d['button_font_size'], 10, 24 );
		$settings['button_padding']          = $this->sanitize_px( $_POST['button_padding'] ?? null, $d['button_padding'], 4, 40 );

		$settings['container_bg_color']     = isset( $_POST['container_bg_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['container_bg_color'], $d['container_bg_color'] ) : $d['container_bg_color'];
		$settings['container_border_color'] = isset( $_POST['container_border_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['container_border_color'], $d['container_border_color'] ) : $d['container_border_color'];
		$settings['container_padding']      = $this->sanitize_px( $_POST['container_padding'] ?? null, $d['container_padding'], 0, 80 );
		$settings['shadow_enabled']         = ! empty( $_POST['shadow_enabled'] );
		$settings['shadow_color']           = isset( $_POST['shadow_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['shadow_color'], $d['shadow_color'] ) : $d['shadow_color'];
		$settings['shadow_opacity']         = $this->sanitize_px( $_POST['shadow_opacity'] ?? null, $d['shadow_opacity'], 0, 100 );
		$settings['shadow_blur']            = $this->sanitize_px( $_POST['shadow_blur'] ?? null, $d['shadow_blur'], 0, 100 );

		$settings['revisit_bg_color']      = isset( $_POST['revisit_bg_color'] ) ? $this->sanitize_hex_color_or_default( $_POST['revisit_bg_color'], $d['revisit_bg_color'] ) : $d['revisit_bg_color'];
		$settings['revisit_opacity']       = $this->sanitize_px( $_POST['revisit_opacity'] ?? null, $d['revisit_opacity'], 0, 100 );
		$settings['revisit_hover_opacity'] = $this->sanitize_px( $_POST['revisit_hover_opacity'] ?? null, $d['revisit_hover_opacity'], 0, 100 );

		update_option( 'alchemy_cookie_consent_settings', $settings );
		$this->redirect_to_tab( 'style' );
	}

	public function save_categories() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_categories' );

		$settings                                     = get_option( 'alchemy_cookie_consent_settings' );
		$settings['categories_enabled']['analytics']  = ! empty( $_POST['cat_analytics'] );
		$settings['categories_enabled']['marketing']  = ! empty( $_POST['cat_marketing'] );

		update_option( 'alchemy_cookie_consent_settings', $settings );
		$this->redirect_to_tab( 'categories' );
	}

	public function save_cookies() {
		$this->verify_admin_request( 'alchemy_cookie_consent_save_cookies' );

		$cookies      = array();
		$cookies_post = isset( $_POST['cookies'] ) ? wp_unslash( $_POST['cookies'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here; every field is individually sanitized in the loop below before use.
		$cookies_raw  = is_array( $cookies_post ) ? $cookies_post : array();
		foreach ( $cookies_raw as $c ) {
			if ( empty( $c['name'] ) ) {
				continue;
			}
			$cookies[] = array(
				'name'      => sanitize_text_field( $c['name'] ),
				'category'  => sanitize_key( $c['category'] ),
				'purpose'   => sanitize_text_field( $c['purpose'] ),
				'duration'  => sanitize_text_field( $c['duration'] ),
				'high_risk' => ! empty( $c['high_risk'] ),
				'notice'    => isset( $c['notice'] ) ? sanitize_text_field( $c['notice'] ) : '',
			);
		}

		update_option( 'alchemy_cookie_consent_cookie_list', $cookies );
		$this->redirect_to_tab( 'cookies' );
	}

	public function export_log() {
		$this->verify_admin_request( 'alchemy_cookie_consent_export_log' );

		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_cookie_consent_log';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="alchemy-cookie-consent-log.csv"' );

		// phpcs:disable WordPress.WP.AlternativeFunctions -- streaming a CSV to
		// the browser via php://output, not writing to a file on disk, so the
		// WP_Filesystem API (which is for actual filesystem files) doesn't apply.
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date/Time', 'Categories', 'Source', 'IP Hash', 'Page URL' ) );

		// Streamed in batches rather than SELECT * with no LIMIT — a
		// long-lived site logs a row per first-time visitor, not just admin
		// actions, so the table can grow well past what's safe to hold in
		// memory as a single PHP array.
		//
		// Keyset pagination on id, not OFFSET: this table takes live inserts
		// from the public consent-save endpoint for the whole duration of the
		// export. Under OFFSET, a row inserted mid-export shifts every later
		// page by one position, re-emitting some rows and silently dropping
		// others — corrupting what's meant to be a compliance audit trail.
		// New rows always get a larger id and sort ahead of our cursor, so
		// `WHERE id < :last_id` is unaffected by anything inserted after the
		// first batch was read. id also breaks ties within the same
		// consent_time second, which a plain ORDER BY consent_time DESC does not.
		$batch_size = 500;
		$last_id    = null;
		do {
			if ( null === $last_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table; an export needs the current data, not a cached copy.
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $batch_size ), ARRAY_A );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table; an export needs the current data, not a cached copy.
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id < %d ORDER BY id DESC LIMIT %d', $table, $last_id, $batch_size ), ARRAY_A );
			}
			foreach ( $rows as $row ) {
				// page_url originates from the public, unauthenticated
				// consent-save endpoint and only passes through esc_url_raw()
				// (which doesn't strip leading =/+/-/@) — csv_safe() stops it
				// from being interpreted as a formula when the export is
				// opened in Excel/Sheets.
				fputcsv(
					$out,
					array(
						$this->csv_safe( $row['consent_time'] ),
						$this->csv_safe( $row['categories'] ),
						$this->csv_safe( ! empty( $row['source'] ) ? $row['source'] : 'explicit' ),
						$this->csv_safe( $row['ip_hash'] ),
						$this->csv_safe( $row['page_url'] ),
					)
				);
			}
			if ( $rows ) {
				$last_row = end( $rows );
				$last_id  = (int) $last_row['id'];
			}
		} while ( count( $rows ) === $batch_size );

		fclose( $out );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		exit;
	}
}
