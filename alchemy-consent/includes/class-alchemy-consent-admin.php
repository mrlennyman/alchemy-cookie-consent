<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Consent_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_alchemy_consent_save_general', array( $this, 'save_general' ) );
		add_action( 'admin_post_alchemy_consent_save_categories', array( $this, 'save_categories' ) );
		add_action( 'admin_post_alchemy_consent_save_cookies', array( $this, 'save_cookies' ) );
		add_action( 'admin_post_alchemy_consent_export_log', array( $this, 'export_log' ) );
		add_action( 'admin_post_alchemy_consent_save_geo', array( $this, 'save_geo' ) );
	}

	public function add_menu() {
		add_menu_page( 'Alchemy Consent', 'Alchemy Consent', 'manage_options', 'alchemy-consent', array( $this, 'render_page' ), 'dashicons-shield', 58 );
	}

	public function render_page() {
		// Read-only tab selector for display only, doesn't change state, no nonce needed.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap"><h1>Alchemy Consent</h1>';
		echo '<h2 class="nav-tab-wrapper">';

		$tabs = array(
			'general'    => 'General',
			'categories' => 'Categories',
			'cookies'    => 'Cookie List',
			'log'        => 'Consent Log',
			'geo'        => 'Geo Targeting',
		);

		foreach ( $tabs as $key => $label ) {
			$class = ( $tab === $key ) ? ' nav-tab-active' : '';
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'alchemy-consent', 'tab' => $key ), admin_url( 'admin.php' ) ) ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'categories':
				$this->render_categories_tab();
				break;
			case 'cookies':
				$this->render_cookies_tab();
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
		$settings = get_option( 'alchemy_consent_settings' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_consent_save_general">
			<?php wp_nonce_field( 'alchemy_consent_save_general' ); ?>
			<table class="form-table">
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
					<th><label for="accent_color">Accent color</label></th>
					<td><input type="text" name="accent_color" id="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="regular-text" placeholder="#1a73e8"></td>
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
				<tr>
					<th><label for="revisit_bg_color">Revisit button color</label></th>
					<td><input type="text" name="revisit_bg_color" id="revisit_bg_color" value="<?php echo esc_attr( isset( $settings['revisit_bg_color'] ) ? $settings['revisit_bg_color'] : '#ffffff' ); ?>" class="regular-text" placeholder="#ffffff"></td>
				</tr>
				<tr>
					<th><label for="revisit_opacity">Revisit button opacity</label></th>
					<td>
						<input type="number" name="revisit_opacity" id="revisit_opacity" value="<?php echo esc_attr( isset( $settings['revisit_opacity'] ) ? $settings['revisit_opacity'] : 55 ); ?>" min="0" max="100" step="5" style="width: 80px;"> %
						<p class="description">At rest (not hovered). Lower = more see-through. Default 55.</p>
					</td>
				</tr>
				<tr>
					<th><label for="revisit_hover_opacity">Revisit button hover opacity</label></th>
					<td>
						<input type="number" name="revisit_hover_opacity" id="revisit_hover_opacity" value="<?php echo esc_attr( isset( $settings['revisit_hover_opacity'] ) ? $settings['revisit_hover_opacity'] : 100 ); ?>" min="0" max="100" step="5" style="width: 80px;"> %
						<p class="description">On hover/focus. Default 100 (fully solid) so it's still a clear, findable target once someone's looking for it.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}

	private function render_categories_tab() {
		$settings = get_option( 'alchemy_consent_settings' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_consent_save_categories">
			<?php wp_nonce_field( 'alchemy_consent_save_categories' ); ?>
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
		$settings = get_option( 'alchemy_consent_settings' );
		$strict   = isset( $settings['strict_countries'] ) ? $settings['strict_countries'] : Alchemy_Consent_Activator::default_strict_countries();
		$light    = isset( $settings['light_countries'] ) ? $settings['light_countries'] : Alchemy_Consent_Activator::default_light_countries();
		?>
		<p>When enabled, first-time visitors are checked against these lists before the banner decides how to behave:</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>Strict</strong> — country in the Strict list, or country can't be determined — normal blocking banner, visitor must choose (opt-in).</li>
			<li><strong>Light</strong> — country in the Light list — banner doesn't interrupt them; enabled categories are granted automatically, but the cookie-settings button appears immediately so they can still opt out.</li>
			<li><strong>Exempt</strong> — everyone else — enabled categories are granted automatically and <em>nothing</em> is shown at all, not even the button. For regions with no consent requirement at all.</li>
		</ul>
		<p class="description">Detection failure always falls back to Strict — it never accidentally relaxes the banner for someone it couldn't identify.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_consent_save_geo">
			<?php wp_nonce_field( 'alchemy_consent_save_geo' ); ?>
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
		$cookies = get_option( 'alchemy_consent_cookie_list', array() );
		?>
		<p>This list drives the <code>[alchemy_cookie_policy]</code> shortcode on your policy page. Add or edit rows for whatever this site actually runs.</p>
		<p>
			<label for="alchemy-consent-common-service">Quick-add a common service:</label>
			<select id="alchemy-consent-common-service">
				<option value="">— Select —</option>
				<?php foreach ( $this->get_common_services() as $key => $svc ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $svc['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="alchemy-consent-add-common">Add</button>
			<span class="description"> — fills in the row with the standard category/purpose so it isn't re-researched per client; edit before saving if this client's use differs.</span>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alchemy_consent_save_cookies">
			<?php wp_nonce_field( 'alchemy_consent_save_cookies' ); ?>
			<table class="widefat" id="alchemy-consent-cookie-table">
				<thead>
					<tr><th>Cookie name</th><th>Category</th><th>Purpose</th><th>Duration</th><th></th></tr>
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
						<td><button type="button" class="button alchemy-consent-remove-row">Remove</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="alchemy-consent-add-row">+ Add cookie</button></p>
			<?php submit_button( 'Save Cookie List' ); ?>
		</form>
		<script>
		( function () {
			var commonServices = <?php echo wp_json_encode( $this->get_common_services() ); ?>;

			function addRow( prefill ) {
				var tbody = document.querySelector( '#alchemy-consent-cookie-table tbody' );
				var i = tbody.children.length;
				var name = prefill ? prefill.name : '';
				var category = prefill ? prefill.category : 'necessary';
				var purpose = prefill ? prefill.purpose : '';
				var duration = prefill ? prefill.duration : '';
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
					'<td><button type="button" class="button alchemy-consent-remove-row">Remove</button></td>';
				tbody.appendChild( row );
			}

			document.getElementById( 'alchemy-consent-add-common' ).addEventListener( 'click', function () {
				var key = document.getElementById( 'alchemy-consent-common-service' ).value;
				if ( key && commonServices[ key ] ) {
					addRow( commonServices[ key ] );
				}
			} );

			document.getElementById( 'alchemy-consent-add-row' ).addEventListener( 'click', function () {
				addRow( null );
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'alchemy-consent-remove-row' ) ) {
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
	 */
	private function get_common_services() {
		return array(
			'bing_uet'      => array(
				'label'    => 'Bing UET (Microsoft Ads)',
				'name'     => '_uetsid / _uetvid',
				'category' => 'marketing',
				'purpose'  => 'Microsoft Bing Ads — tracks conversions and enables remarketing. Not supported by Site Kit; needs a GTM tag wired to the alchemy_consent_marketing signal.',
				'duration' => 'Session / 13 months',
			),
			'facebook_pixel' => array(
				'label'    => 'Facebook / Meta Pixel',
				'name'     => '_fbp',
				'category' => 'marketing',
				'purpose'  => 'Meta Pixel — tracks conversions and enables retargeting.',
				'duration' => '3 months',
			),
			'hotjar'        => array(
				'label'    => 'Hotjar',
				'name'     => '_hjSession_* / _hjSessionUser_*',
				'category' => 'analytics',
				'purpose'  => 'Hotjar — session recording and heatmaps.',
				'duration' => '30 min / 1 year',
			),
			'clarity'       => array(
				'label'    => 'Microsoft Clarity',
				'name'     => '_clck / _clsk',
				'category' => 'analytics',
				'purpose'  => 'Microsoft Clarity — session recording and heatmaps.',
				'duration' => '1 year / 1 day',
			),
			'live_chat'     => array(
				'label'    => 'Live chat widget (generic)',
				'name'     => '(varies by vendor)',
				'category' => 'necessary',
				'purpose'  => 'Live chat — remembers your conversation while you\'re actively chatting. Recategorise as Analytics/Marketing if this vendor also profiles visitors who never open the chat.',
				'duration' => 'Session',
			),
		);
	}

	private function render_log_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_consent_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table; a live consent-audit listing shouldn't be cached.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY consent_time DESC LIMIT %d', $table, 200 ) );
		?>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alchemy_consent_export_log' ), 'alchemy_consent_export_log' ) ); ?>" class="button">Export CSV</a>
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
		check_admin_referer( 'alchemy_consent_save_geo' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-consent' ) );
		}

		$settings                            = get_option( 'alchemy_consent_settings' );
		$settings['geo_targeting_enabled']   = ! empty( $_POST['geo_targeting_enabled'] );
		$settings['strict_countries']        = isset( $_POST['strict_countries'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['strict_countries'] ) ) )
			: Alchemy_Consent_Activator::default_strict_countries();
		$settings['light_countries']         = isset( $_POST['light_countries'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['light_countries'] ) ) )
			: Alchemy_Consent_Activator::default_light_countries();

		update_option( 'alchemy_consent_settings', $settings );
		wp_safe_redirect( add_query_arg( array( 'page' => 'alchemy-consent', 'tab' => 'geo', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_general() {
		check_admin_referer( 'alchemy_consent_save_general' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-consent' ) );
		}

		$settings                    = get_option( 'alchemy_consent_settings' );
		$settings['banner_message']  = isset( $_POST['banner_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['banner_message'] ) ) : '';
		$settings['accept_label']    = isset( $_POST['accept_label'] ) ? sanitize_text_field( wp_unslash( $_POST['accept_label'] ) ) : 'Accept All';
		$settings['reject_label']    = isset( $_POST['reject_label'] ) ? sanitize_text_field( wp_unslash( $_POST['reject_label'] ) ) : 'Reject All';
		$settings['customize_label'] = isset( $_POST['customize_label'] ) ? sanitize_text_field( wp_unslash( $_POST['customize_label'] ) ) : 'Customize';
		$settings['accent_color']    = isset( $_POST['accent_color'] ) ? sanitize_text_field( wp_unslash( $_POST['accent_color'] ) ) : '#1a73e8';
		$settings['policy_page_id']  = isset( $_POST['policy_page_id'] ) ? absint( $_POST['policy_page_id'] ) : 0;
		$settings['revisit_bg_color']      = isset( $_POST['revisit_bg_color'] ) ? sanitize_text_field( wp_unslash( $_POST['revisit_bg_color'] ) ) : '#ffffff';
		$settings['revisit_opacity']       = isset( $_POST['revisit_opacity'] ) ? max( 0, min( 100, absint( $_POST['revisit_opacity'] ) ) ) : 55;
		$settings['revisit_hover_opacity'] = isset( $_POST['revisit_hover_opacity'] ) ? max( 0, min( 100, absint( $_POST['revisit_hover_opacity'] ) ) ) : 100;

		update_option( 'alchemy_consent_settings', $settings );
		wp_safe_redirect( add_query_arg( array( 'page' => 'alchemy-consent', 'tab' => 'general', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_categories() {
		check_admin_referer( 'alchemy_consent_save_categories' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-consent' ) );
		}

		$settings                                     = get_option( 'alchemy_consent_settings' );
		$settings['categories_enabled']['analytics']  = ! empty( $_POST['cat_analytics'] );
		$settings['categories_enabled']['marketing']  = ! empty( $_POST['cat_marketing'] );

		update_option( 'alchemy_consent_settings', $settings );
		wp_safe_redirect( add_query_arg( array( 'page' => 'alchemy-consent', 'tab' => 'categories', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_cookies() {
		check_admin_referer( 'alchemy_consent_save_cookies' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-consent' ) );
		}

		$cookies      = array();
		$cookies_post = isset( $_POST['cookies'] ) ? wp_unslash( $_POST['cookies'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here; every field is individually sanitized in the loop below before use.
		$cookies_raw  = is_array( $cookies_post ) ? $cookies_post : array();
		foreach ( $cookies_raw as $c ) {
			if ( empty( $c['name'] ) ) {
				continue;
			}
			$cookies[] = array(
				'name'     => sanitize_text_field( $c['name'] ),
				'category' => sanitize_key( $c['category'] ),
				'purpose'  => sanitize_text_field( $c['purpose'] ),
				'duration' => sanitize_text_field( $c['duration'] ),
			);
		}

		update_option( 'alchemy_consent_cookie_list', $cookies );
		wp_safe_redirect( add_query_arg( array( 'page' => 'alchemy-consent', 'tab' => 'cookies', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function export_log() {
		check_admin_referer( 'alchemy_consent_export_log' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'alchemy-consent' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'alchemy_consent_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table; an export needs the current data, not a cached copy.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY consent_time DESC', $table ), ARRAY_A );

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="alchemy-consent-log.csv"' );

		// phpcs:disable WordPress.WP.AlternativeFunctions -- streaming a CSV to
		// the browser via php://output, not writing to a file on disk, so the
		// WP_Filesystem API (which is for actual filesystem files) doesn't apply.
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date/Time', 'Categories', 'Source', 'IP Hash', 'Page URL' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row['consent_time'], $row['categories'], ! empty( $row['source'] ) ? $row['source'] : 'explicit', $row['ip_hash'], $row['page_url'] ) );
		}
		fclose( $out );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		exit;
	}
}
