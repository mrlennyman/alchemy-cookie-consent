<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alchemy_Consent_Shortcodes {

	public function __construct() {
		add_shortcode( 'alchemy_cookie_policy', array( $this, 'render_cookie_table' ) );
		add_shortcode( 'alchemy_consent_settings_link', array( $this, 'render_settings_link' ) );
		add_shortcode( 'alchemy_regulatory_links', array( $this, 'render_regulatory_links' ) );

		// Backward-compatible aliases for the old wa_-prefixed shortcode
		// names, so page content written before the rename (e.g. an
		// already-published Cookie Policy page) keeps working without
		// needing to be edited.
		add_shortcode( 'wa_cookie_policy', array( $this, 'render_cookie_table' ) );
		add_shortcode( 'wa_consent_settings_link', array( $this, 'render_settings_link' ) );
		add_shortcode( 'wa_regulatory_links', array( $this, 'render_regulatory_links' ) );
	}

	/**
	 * Renders the cookie table straight from the admin-configured list, so
	 * it can never drift out of sync with what's actually on the site the
	 * way a hand-written policy page can.
	 */
	public function render_cookie_table() {
		$cookies = get_option( 'alchemy_consent_cookie_list', array() );
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
			echo '<table class="alchemy-consent-policy-table"><thead><tr><th>Name</th><th>Purpose</th><th>Duration</th></tr></thead><tbody>';
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
		return '<button type="button" class="alchemy-consent-reopen-link" onclick="document.dispatchEvent(new Event(\'alchemy-consent-reopen\'))">' . esc_html( $atts['label'] ) . '</button>';
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
		echo '<table class="alchemy-consent-policy-table"><thead><tr><th>Region</th><th>Authority</th><th>Note</th></tr></thead><tbody>';
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
