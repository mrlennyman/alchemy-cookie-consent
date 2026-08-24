<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Cookie_Consent_Shortcodes {

	public function __construct() {
		add_shortcode( 'alchemy_cookie_policy', array( $this, 'render_cookie_table' ) );
		add_shortcode( 'alchemy_cookie_consent_settings_link', array( $this, 'render_settings_link' ) );
		add_shortcode( 'alchemy_regulatory_links', array( $this, 'render_regulatory_links' ) );
		add_shortcode( 'alchemy_privacy_choices', array( $this, 'render_privacy_choices_link' ) );
	}

	/**
	 * Renders the cookie table straight from the admin-configured list, so
	 * it can never drift out of sync with what's actually on the site the
	 * way a hand-written policy page can.
	 */
	public function render_cookie_table() {
		$cookies = get_option( 'alchemy_cookie_consent_cookie_list', array() );
		if ( empty( $cookies ) ) {
			return '';
		}

		$labels  = array(
			'necessary' => 'Necessary',
			'analytics' => 'Analytics',
			'marketing' => 'Marketing',
		);
		$grouped = array();
		foreach ( $cookies as $c ) {
			$grouped[ $c['category'] ][] = $c;
		}

		ob_start();
		foreach ( $labels as $key => $label ) {
			if ( empty( $grouped[ $key ] ) ) {
				continue;
			}
			echo '<h3>' . esc_html( $label ) . '</h3>';
			echo '<table class="alchemy-cookie-consent-policy-table"><thead><tr><th>Name</th><th>Purpose</th><th>Duration</th></tr></thead><tbody>';
			foreach ( $grouped[ $key ] as $c ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $c['name'] ),
					esc_html( $c['purpose'] ),
					esc_html( $c['duration'] )
				);
			}
			echo '</tbody></table>';
		}
		return ob_get_clean();
	}

	public function render_settings_link( $atts ) {
		$atts = shortcode_atts( array( 'label' => 'Cookie Settings' ), $atts );
		return '<button type="button" class="alchemy-cookie-consent-reopen-link" onclick="document.dispatchEvent(new Event(\'alchemy-cookie-consent-reopen\'))">' . esc_html( $atts['label'] ) . '</button>';
	}

	/**
	 * CPRA/CCPA's "Do Not Sell or Share My Personal Information" link —
	 * intended for a client's site footer, one click, no need to reopen
	 * and hunt through the full banner. The icon is a generic two-tone
	 * toggle rendered inline (not the exact official CPPA artwork, which
	 * isn't something this plugin can fetch/bundle) — swap it for the
	 * official asset per client if pixel-exact regulatory icon match
	 * matters. Clicking dispatches an event banner.js listens for, so
	 * this shortcode doesn't need to know the plugin's consent internals.
	 */
	public function render_privacy_choices_link( $atts ) {
		$atts = shortcode_atts( array( 'label' => 'Your Privacy Choices' ), $atts );
		$icon = '<svg width="20" height="12" viewBox="0 0 20 12" aria-hidden="true" focusable="false" style="vertical-align:middle;margin-right:6px;"><rect x="0" y="0" width="20" height="12" rx="6" fill="#000"/><rect x="0" y="0" width="10" height="12" rx="6" fill="#06f"/><circle cx="14" cy="6" r="4.5" fill="#fff"/></svg>';
		return '<button type="button" class="alchemy-cookie-consent-privacy-choices" onclick="document.dispatchEvent(new Event(\'alchemy-cookie-consent-optout\'))">' . $icon . esc_html( $atts['label'] ) . '</button>';
	}

	/**
	 * The "right to complain to a supervisory authority" disclosure that
	 * GDPR/UK GDPR/Law 25 require, kept as data in one place rather than
	 * pasted into each client's policy by hand. URLs verified directly
	 * against each regulator's own site — update this array (and the
	 * plugin version) if a regulator restructures their site, rather than
	 * editing individual client policies.
	 *
	 * Included for every region regardless of a given client's actual
	 * audience, consistent with the block-by-default-everywhere approach:
	 * one list, not a per-client subset to maintain.
	 */
	private function get_regulatory_authorities() {
		return array(
			array(
				'region'    => 'United Kingdom',
				'authority' => "Information Commissioner's Office (ICO)",
				'url'       => 'https://ico.org.uk/for-organisations/direct-marketing-and-privacy-and-electronic-communications/guide-to-pecr/cookies-and-similar-technologies/',
				'note'      => 'Regulates cookies under PECR and the UK GDPR.',
			),
			array(
				'region'    => 'European Union',
				'authority' => 'European Data Protection Board (EDPB)',
				'url'       => 'https://www.edpb.europa.eu/',
				'note'      => 'Coordinates the national data protection authority in each EU member state.',
			),
			array(
				'region'    => 'Canada',
				'authority' => 'Office of the Privacy Commissioner of Canada (OPC)',
				'url'       => 'https://www.priv.gc.ca/',
				'note'      => "Regulates PIPEDA, Canada's federal privacy law.",
			),
			array(
				'region'    => 'Quebec',
				'authority' => "Commission d'accès à l'information (CAI)",
				'url'       => 'https://www.cai.gouv.qc.ca/',
				'note'      => 'Regulates Law 25, which sets a stricter opt-in standard than the rest of Canada.',
			),
			array(
				'region'    => 'California',
				'authority' => 'California Privacy Protection Agency (CPPA)',
				'url'       => 'https://privacy.ca.gov/',
				'note'      => 'Regulates the CCPA/CPRA.',
			),
		);
	}

	public function render_regulatory_links() {
		ob_start();
		echo '<table class="alchemy-cookie-consent-policy-table"><thead><tr><th>Region</th><th>Authority</th><th>Note</th></tr></thead><tbody>';
		foreach ( $this->get_regulatory_authorities() as $a ) {
			printf(
				'<tr><td>%s</td><td><a href="%s" target="_blank" rel="noopener">%s</a></td><td>%s</td></tr>',
				esc_html( $a['region'] ),
				esc_url( $a['url'] ),
				esc_html( $a['authority'] ),
				esc_html( $a['note'] )
			);
		}
		echo '</tbody></table>';
		return ob_get_clean();
	}
}
