<?php
namespace Krokedil\KustomCheckout\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store setup checks for the WooCommerce system status report.
 *
 * Tests the WordPress and WooCommerce settings that Kustom Checkout requires
 * or recommends, and renders the results as a section in the system status
 * report (WooCommerce → Status).
 */
class StoreSetupChecks {

	/**
	 * Check type for settings that are required for the plugin to work.
	 *
	 * @var string
	 */
	const TYPE_REQUIRED = 'required';

	/**
	 * Check type for settings that are recommended, but not required.
	 *
	 * @var string
	 */
	const TYPE_RECOMMENDED = 'recommended';

	/**
	 * URL to the setup documentation.
	 *
	 * @var string
	 */
	const DOCUMENTATION_URL = 'https://docs.krokedil.com/kustom-checkout-for-woocommerce/get-started/install-and-activate/#step-1-required-wordpresswoocommerce-settings';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Priority 5 places the section above the Kustom Checkout request log.
		add_action( 'woocommerce_system_status_report', array( $this, 'render' ), 5 );
	}

	/**
	 * Returns the store setup checks and their results.
	 *
	 * Each check is an associative array with the following keys:
	 * - id           (string) Unique check id.
	 * - label        (string) Translated row label, phrased as an assertion (e.g. "Pretty permalinks configured").
	 * - export_label (string) Untranslated label used in the report text export.
	 * - type         (string) One of the TYPE_* constants.
	 * - passed       (bool)   Result of the check.
	 * - message      (string) Shown when the check fails. Escaped HTML describing how to fix the
	 *                         setting, may contain anchor tags (see link_open()).
	 * - help         (string) Help tip describing why the setting matters.
	 *
	 * @return array[] The checks.
	 */
	public function get_checks() {
		$checks = array(
			$this->check_https(),
			$this->check_permalinks(),
			$this->check_terms_page(),
			$this->check_decimals(),
		);

		/**
		 * Filters the Kustom Checkout store setup checks shown in the WooCommerce system status report.
		 *
		 * @since 2.21.0
		 *
		 * @param array[] $checks The checks. See StoreSetupChecks::get_checks() for the format of each check.
		 */
		return apply_filters( 'kco_wc_store_setup_checks', $checks );
	}

	/**
	 * Checks that the checkout is served over HTTPS.
	 *
	 * @return array The check.
	 */
	private function check_https() {
		return array(
			'id'           => 'https',
			'label'        => __( 'Checkout served over HTTPS', 'klarna-checkout-for-woocommerce' ),
			'export_label' => 'Checkout served over HTTPS',
			'type'         => self::TYPE_REQUIRED,
			'passed'       => wc_checkout_is_https(),
			'message'      => sprintf(
				/* translators: 1: opening link tag to the WooCommerce HTTPS documentation, 2: closing link tag, 3: opening link tag to the plugin documentation, 4: closing link tag. */
				esc_html__( 'Your checkout is not using HTTPS. %1$sLearn more about HTTPS and SSL certificates%2$s. Read more about this requirement in the %3$splugin docs%4$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( 'https://woocommerce.com/document/ssl-and-https/' ),
				'</a>',
				$this->link_open( self::DOCUMENTATION_URL ),
				'</a>'
			),
			'help'         => __( 'The checkout must be served over a secure connection (HTTPS) for Kustom Checkout to work.', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Checks that pretty permalinks are enabled.
	 *
	 * @return array The check.
	 */
	private function check_permalinks() {
		$permalink_structure = get_option( 'permalink_structure' );

		return array(
			'id'           => 'permalinks',
			'label'        => __( 'Pretty permalinks configured', 'klarna-checkout-for-woocommerce' ),
			'export_label' => 'Pretty permalinks configured',
			'type'         => self::TYPE_REQUIRED,
			'passed'       => ! empty( $permalink_structure ),
			'message'      => sprintf(
				/* translators: 1: opening link tag to the permalinks settings page, 2: closing link tag, 3: opening link tag to the plugin documentation, 4: closing link tag. */
				esc_html__( 'Please update your permalink structure %1$shere%2$s. Read more about this requirement in the %3$splugin docs%4$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( admin_url( 'options-permalink.php' ), false ),
				'</a>',
				$this->link_open( self::DOCUMENTATION_URL ),
				'</a>'
			),
			'help'         => __( 'Pretty permalinks are required for callbacks from Kustom to reach your store.', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Checks that a published terms and conditions page is selected in WooCommerce.
	 *
	 * @return array The check.
	 */
	private function check_terms_page() {
		$terms_page_id = wc_terms_and_conditions_page_id();
		$passed        = ! empty( $terms_page_id ) && 'publish' === get_post_status( $terms_page_id );

		return array(
			'id'           => 'terms_page',
			'label'        => __( 'Terms and conditions page set', 'klarna-checkout-for-woocommerce' ),
			'export_label' => 'Terms and conditions page set',
			'type'         => self::TYPE_REQUIRED,
			'passed'       => $passed,
			'message'      => sprintf(
				/* translators: 1: opening link tag to the WooCommerce advanced settings page, 2: closing link tag, 3: opening link tag to the plugin documentation, 4: closing link tag. */
				esc_html__( 'No published terms and conditions page is set %1$shere%2$s. Read more about this requirement in the %3$splugin docs%4$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( admin_url( 'admin.php?page=wc-settings&tab=advanced' ), false ),
				'</a>',
				$this->link_open( self::DOCUMENTATION_URL ),
				'</a>'
			),
			'help'         => __( 'Kustom Checkout displays a link to your terms and conditions page in the checkout. The page must be published and selected in WooCommerce.', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Checks that prices are displayed with 2 decimals.
	 *
	 * @return array The check.
	 */
	private function check_decimals() {
		$decimals = wc_get_price_decimals();

		return array(
			'id'           => 'decimals',
			'label'        => __( 'Number of decimals set to 2', 'klarna-checkout-for-woocommerce' ),
			'export_label' => 'Number of decimals set to 2',
			'type'         => self::TYPE_RECOMMENDED,
			'passed'       => 2 === $decimals,
			'message'      => sprintf(
				/* translators: 1: the current number of decimals, 2: opening link tag to the WooCommerce general settings page, 3: closing link tag, 4: opening link tag to the plugin documentation, 5: closing link tag. */
				esc_html__( 'Set to %1$d %2$shere%3$s. Read more about this strong recommendation in the %4$splugin docs%5$s.', 'klarna-checkout-for-woocommerce' ),
				$decimals,
				$this->link_open( admin_url( 'admin.php?page=wc-settings&tab=general' ), false ),
				'</a>',
				$this->link_open( self::DOCUMENTATION_URL ),
				'</a>'
			),
			'help'         => __( 'Displaying prices with 2 decimals is recommended to avoid rounding issues, so that the order total matches between WooCommerce and Kustom.', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Renders the store setup section in the WooCommerce system status report.
	 *
	 * @return void
	 */
	public function render() {
		$checks = $this->get_checks();
		?>
		<style>
			.kco-store-setup-check mark.warning {
				color: #996800;
			}
		</style>
		<table class="wc_status_table widefat kco-store-setup-check" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="Kustom Checkout Store Setup Check">
						<h2><?php esc_html_e( 'Kustom Checkout – Store setup check', 'klarna-checkout-for-woocommerce' ); ?><?php echo wc_help_tip( esc_html__( 'Checks of the WordPress and WooCommerce settings that Kustom Checkout requires or recommends.', 'klarna-checkout-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() returns escaped HTML. ?></h2>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<tr>
						<td data-export-label="<?php echo esc_attr( $check['export_label'] ); ?>"><?php echo esc_html( $check['label'] . $this->get_type_suffix( $check['type'] ) ); ?>:</td>
						<td class="help"><?php echo wc_help_tip( $check['help'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() returns escaped HTML. ?></td>
						<td><?php $this->render_result( $check ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Returns the translated label suffix for a check type.
	 *
	 * @param string $type One of the TYPE_* constants.
	 *
	 * @return string The label suffix, or an empty string for unknown types.
	 */
	private function get_type_suffix( $type ) {
		switch ( $type ) {
			case self::TYPE_REQUIRED:
				return ' (' . __( 'required', 'klarna-checkout-for-woocommerce' ) . ')';
			case self::TYPE_RECOMMENDED:
				return ' (' . __( 'recommended', 'klarna-checkout-for-woocommerce' ) . ')';
			default:
				return '';
		}
	}

	/**
	 * Renders the result cell for a single check.
	 *
	 * @param array $check The check. See get_checks() for the format.
	 *
	 * @return void
	 */
	private function render_result( $check ) {
		if ( $check['passed'] ) {
			echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
			return;
		}

		$mark_class = self::TYPE_REQUIRED === $check['type'] ? 'error' : 'warning';
		echo '<mark class="' . esc_attr( $mark_class ) . '"><span class="dashicons dashicons-warning"></span> ' . wp_kses(
			$check['message'],
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		) . '</mark>';
	}

	/**
	 * Returns an opening anchor tag for use in a check message.
	 *
	 * @param string $url     The URL to link to.
	 * @param bool   $new_tab Whether the link should open in a new tab. Use false for links within wp-admin.
	 *
	 * @return string The opening anchor tag.
	 */
	private function link_open( $url, $new_tab = true ) {
		return '<a href="' . esc_url( $url ) . '"' . ( $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>';
	}
}
