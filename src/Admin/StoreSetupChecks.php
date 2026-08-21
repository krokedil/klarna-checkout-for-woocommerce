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
	 * HTML id of the section in the WooCommerce system status report, used as a deep link anchor.
	 *
	 * @var string
	 */
	const REPORT_ANCHOR = 'kco-store-setup-check';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Priority 5 places the section above the Kustom Checkout request log.
		add_action( 'woocommerce_system_status_report', array( $this, 'render' ), 5 );
		add_action( 'admin_notices', array( $this, 'output_admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_payments_page_script' ) );
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
	 * - read_more    (string) Escaped HTML sentence linking to the documentation, shown after the
	 *                         message where space allows.
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
				/* translators: 1: opening link tag to the WooCommerce HTTPS documentation, 2: closing link tag. */
				esc_html__( 'Your checkout is not using HTTPS. %1$sLearn more about HTTPS and SSL certificates%2$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( 'https://woocommerce.com/document/ssl-and-https/' ),
				'</a>'
			),
			'read_more'    => $this->get_requirement_read_more(),
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
				/* translators: 1: opening link tag to the permalinks settings page, 2: closing link tag. */
				esc_html__( 'Please update your permalink structure %1$shere%2$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( admin_url( 'options-permalink.php' ), false ),
				'</a>'
			),
			'read_more'    => $this->get_requirement_read_more(),
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
				/* translators: 1: opening link tag to the WooCommerce advanced settings page, 2: closing link tag. */
				esc_html__( 'No published terms and conditions page is set %1$shere%2$s.', 'klarna-checkout-for-woocommerce' ),
				$this->link_open( admin_url( 'admin.php?page=wc-settings&tab=advanced' ), false ),
				'</a>'
			),
			'read_more'    => $this->get_requirement_read_more(),
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
				/* translators: 1: the current number of decimals, 2: opening link tag to the WooCommerce general settings page, 3: closing link tag. */
				esc_html__( 'Currently set to %1$d %2$shere%3$s.', 'klarna-checkout-for-woocommerce' ),
				$decimals,
				$this->link_open( admin_url( 'admin.php?page=wc-settings&tab=general' ), false ),
				'</a>'
			),
			'read_more'    => sprintf(
				/* translators: 1: opening link tag to the plugin documentation, 2: closing link tag. */
				esc_html__( 'Read more about this strong recommendation in the %1$splugin docs%2$s.', 'klarna-checkout-for-woocommerce' ),
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
		<table class="wc_status_table widefat kco-store-setup-check" cellspacing="0" id="<?php echo esc_attr( self::REPORT_ANCHOR ); ?>">
			<thead>
				<tr>
					<th colspan="3" data-export-label="Kustom Checkout Store Setup Check">
						<h2><?php esc_html_e( 'Kustom Checkout – Store setup check', 'klarna-checkout-for-woocommerce' ); ?><?php echo wc_help_tip( esc_html__( 'Checks store settings that Kustom Checkout requires or recommends.', 'klarna-checkout-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() returns escaped HTML. ?></h2>
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
		$message    = $check['message'] . ( empty( $check['read_more'] ) ? '' : ' ' . $check['read_more'] );
		echo '<mark class="' . esc_attr( $mark_class ) . '"><span class="dashicons dashicons-warning"></span> ' . wp_kses( $message, $this->allowed_message_html() ) . '</mark>';
	}

	/**
	 * Returns the URL to the store setup check section in the WooCommerce system status report.
	 *
	 * @return string The URL.
	 */
	public function get_report_url() {
		return admin_url( 'admin.php?page=wc-status#' . self::REPORT_ANCHOR );
	}

	/**
	 * Outputs the content of the store setup check box in the settings page sidebar.
	 *
	 * Registered as a sidebar box content callback with the krokedil/settings-page
	 * package, which renders the box wrapper and title.
	 *
	 * @return void
	 */
	public function output_sidebar_box_content() {
		$checks = $this->get_checks();

		list( $required_failed, $recommended_failed ) = $this->get_failed_counts( $checks );

		$failed = array_filter(
			$checks,
			function ( $check ) {
				return empty( $check['passed'] );
			}
		);

		?>
		<style>
			.kco-store-setup-box__description {
				opacity: 0.8;
				margin-top: 4px;
				margin-bottom: 12px;
			}
			.kco-store-setup-box__notice {
				display: flex;
				align-items: center;
				gap: 4px;
				padding: 8px 12px;
				border-left-width: 4px;
				border-left-style: solid;
			}
			.kco-store-setup-box__notice--success {
				background: #edfaef;
				border-left-color: #00a32a;
			}
			.kco-store-setup-box__notice--success .dashicons {
				color: #00a32a;
			}
			.kco-store-setup-box__notice--warning {
				background: #fcf9e8;
				border-left-color: #dba617;
			}
			.kco-store-setup-box__notice--warning .dashicons {
				color: #996800;
			}
			.kco-store-setup-box__notice--error {
				background: #fcf0f1;
				border-left-color: #d63638;
			}
			.kco-store-setup-box__notice--error .dashicons {
				color: #d63638;
			}
			.kco-store-setup-box__label {
				font-weight: 600;
				margin-bottom: 0;
			}
			.kco-store-setup-box__message {
				margin-top: 4px;
				padding-left: 14px;
			}
		</style>
		<p class="kco-store-setup-box__description"><?php esc_html_e( 'Checks store settings that Kustom Checkout requires or recommends.', 'klarna-checkout-for-woocommerce' ); ?></p>
		<?php $this->output_sidebar_box_notice( count( $checks ), $required_failed, $recommended_failed ); ?>
		<?php $this->output_failed_check_rows( $failed ); ?>
		<p class="kco-store-setup-box__report-link"><a href="<?php echo esc_url( $this->get_report_url() ); ?>"><?php esc_html_e( 'View full report', 'klarna-checkout-for-woocommerce' ); ?></a></p>
		<?php
	}

	/**
	 * Outputs the list of failing checks as rows with a label and a fix message.
	 *
	 * @param array[] $failed The failing checks, see get_checks().
	 *
	 * @return void
	 */
	private function output_failed_check_rows( $failed ) {
		foreach ( $failed as $check ) {
			$message = $check['message'] . ( empty( $check['read_more'] ) ? '' : ' ' . $check['read_more'] );
			?>
			<div class="kco-store-setup-box__check">
				<p class="kco-store-setup-box__label">&raquo;&nbsp;<?php echo esc_html( $check['label'] . $this->get_type_suffix( $check['type'] ) ); ?><?php echo wc_help_tip( $check['help'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() returns escaped HTML. ?></p>
				<p class="kco-store-setup-box__message"><?php echo wp_kses( $message, $this->allowed_message_html() ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Returns the read more sentence used by the required checks.
	 *
	 * @return string Escaped HTML.
	 */
	private function get_requirement_read_more() {
		return sprintf(
			/* translators: 1: opening link tag to the plugin documentation, 2: closing link tag. */
			esc_html__( 'Read more about this requirement in the %1$splugin docs%2$s.', 'klarna-checkout-for-woocommerce' ),
			$this->link_open( self::DOCUMENTATION_URL ),
			'</a>'
		);
	}

	/**
	 * Outputs the summary notice at the top of the sidebar box.
	 *
	 * Green when every check passes, yellow when only recommended checks fail, and
	 * red as soon as a required check fails.
	 *
	 * @param int $total              The total number of checks.
	 * @param int $required_failed    The number of failing required checks.
	 * @param int $recommended_failed The number of failing recommended checks.
	 *
	 * @return void
	 */
	private function output_sidebar_box_notice( $total, $required_failed, $recommended_failed ) {
		$failed_count = $required_failed + $recommended_failed;

		if ( 0 === $failed_count ) {
			/* translators: %d: the number of checks. */
			$message = sprintf( _n( 'All %d check passed.', 'All %d checks passed.', $total, 'klarna-checkout-for-woocommerce' ), $total );
			$state   = 'success';
			$icon    = 'dashicons-yes-alt';
		} else {
			$message = $this->get_fail_sentence( $required_failed, $recommended_failed );
			$state   = $required_failed > 0 ? 'error' : 'warning';
			$icon    = 'dashicons-warning';
		}

		?>
		<p class="kco-store-setup-box__notice kco-store-setup-box__notice--<?php echo esc_attr( $state ); ?>"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span> <span><?php echo esc_html( $message ); ?></span></p>
		<?php
	}

	/**
	 * Outputs an admin notice when at least one required check fails.
	 *
	 * Shown while the gateway is enabled, on the WooCommerce screens, the dashboard and
	 * the plugins page — but not on the Kustom Checkout settings page, where the store
	 * setup check box in the sidebar already shows the status. Not dismissible; it
	 * disappears once the failing required settings are fixed.
	 *
	 * @return void
	 */
	public function output_admin_notice() {
		$settings = get_option( 'woocommerce_kco_settings', array() );
		if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
			return;
		}

		$screen     = get_current_screen();
		$screen_ids = array_merge( wc_get_screen_ids(), array( 'dashboard', 'plugins' ) );
		if ( empty( $screen ) || ! in_array( $screen->id, $screen_ids, true ) ) {
			return;
		}

		// The Kustom Checkout settings page shows the store setup check box in the sidebar.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the current settings section.
		if ( 'woocommerce_page_wc-settings' === $screen->id && 'kco' === $section ) {
			return;
		}

		$checks = $this->get_checks();

		list( $required_failed, $recommended_failed ) = $this->get_failed_counts( $checks );
		if ( $required_failed < 1 ) {
			return;
		}

		$failed = array_filter(
			$checks,
			function ( $check ) {
				return empty( $check['passed'] );
			}
		);

		?>
		<div class="notice notice-error">
			<p><strong>
				<?php
				printf(
					/* translators: %s: summary of the failing checks. */
					esc_html__( 'Kustom Checkout store setup, %s', 'klarna-checkout-for-woocommerce' ),
					esc_html( $this->get_fail_sentence( $required_failed, $recommended_failed ) )
				);
				?>
			</strong></p>
			<?php foreach ( $failed as $check ) : ?>
				<p>&raquo; <?php echo esc_html( $check['label'] . $this->get_type_suffix( $check['type'] ) ); ?> - <?php echo wp_kses( $check['message'], $this->allowed_message_html() ); ?></p>
			<?php endforeach; ?>
			<p><a href="<?php echo esc_url( $this->get_report_url() ); ?>"><?php esc_html_e( 'Read more and view full report', 'klarna-checkout-for-woocommerce' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Counts the failing checks per type.
	 *
	 * @param array[] $checks The checks, see get_checks().
	 *
	 * @return int[] The number of failing required checks, and the number of failing recommended checks.
	 */
	private function get_failed_counts( $checks ) {
		$required_failed    = 0;
		$recommended_failed = 0;
		foreach ( $checks as $check ) {
			if ( ! empty( $check['passed'] ) ) {
				continue;
			}

			if ( self::TYPE_REQUIRED === $check['type'] ) {
				++$required_failed;
			} else {
				++$recommended_failed;
			}
		}

		return array( $required_failed, $recommended_failed );
	}

	/**
	 * Whether at least one required check fails.
	 *
	 * @return bool True when a required check fails.
	 */
	public function has_failing_required_checks() {
		list( $required_failed ) = $this->get_failed_counts( $this->get_checks() );

		return $required_failed > 0;
	}

	/**
	 * Returns the translated sentence summarizing the failing checks, or an empty string when all pass.
	 *
	 * @return string The sentence, e.g. "1 required and 1 recommended check fails.".
	 */
	public function get_failed_summary() {
		list( $required_failed, $recommended_failed ) = $this->get_failed_counts( $this->get_checks() );

		if ( $required_failed + $recommended_failed < 1 ) {
			return '';
		}

		return $this->get_fail_sentence( $required_failed, $recommended_failed );
	}

	/**
	 * Enqueues the script that shows the store setup status under the gateway on the
	 * WooCommerce payments settings page.
	 *
	 * WooCommerce suppresses classic admin notices on that page, so the status is
	 * injected into the gateway row client side instead, the same way the official
	 * Stripe gateway shows its row notice there.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_payments_page_script( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Only the payments providers list: the checkout tab without a section.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the current settings tab.
		if ( 'checkout' !== $tab || isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the current settings section.
			return;
		}

		if ( ! $this->has_failing_required_checks() ) {
			return;
		}

		$failed_rows = array();
		foreach ( $this->get_checks() as $check ) {
			if ( ! empty( $check['passed'] ) ) {
				continue;
			}

			$failed_rows[] = array(
				'label'   => $check['label'] . $this->get_type_suffix( $check['type'] ),
				'message' => wp_kses( $check['message'], $this->allowed_message_html() ),
			);
		}

		wp_register_script( 'kco-store-setup-payments-page', KCO_WC_PLUGIN_URL . '/assets/js/klarna-checkout-for-woocommerce-payments-page.js', array(), KCO_WC_VERSION, true );
		wp_localize_script(
			'kco-store-setup-payments-page',
			'kcoStoreSetupNotice',
			array(
				'gatewayId' => 'kco',
				'summary'   => sprintf(
					/* translators: %s: summary of the failing checks. */
					__( 'Kustom Checkout store setup, %s', 'klarna-checkout-for-woocommerce' ),
					$this->get_failed_summary()
				),
				'checks'    => $failed_rows,
				'linkText'  => __( 'Read more and view full report', 'klarna-checkout-for-woocommerce' ),
				'reportUrl' => $this->get_report_url(),
			)
		);
		wp_enqueue_script( 'kco-store-setup-payments-page' );
	}

	/**
	 * Returns the translated sentence summarizing the failing checks.
	 *
	 * @param int $required_failed    The number of failing required checks.
	 * @param int $recommended_failed The number of failing recommended checks.
	 *
	 * @return string The sentence.
	 */
	private function get_fail_sentence( $required_failed, $recommended_failed ) {
		if ( $required_failed > 0 && $recommended_failed > 0 ) {
			/* translators: 1: the number of failing required checks, 2: the number of failing recommended checks. */
			return sprintf( _n( '%1$d required and %2$d recommended check fails.', '%1$d required and %2$d recommended checks fail.', $recommended_failed, 'klarna-checkout-for-woocommerce' ), $required_failed, $recommended_failed );
		}

		if ( $required_failed > 0 ) {
			/* translators: %d: the number of failing required checks. */
			return sprintf( _n( '%d required check fails.', '%d required checks fail.', $required_failed, 'klarna-checkout-for-woocommerce' ), $required_failed );
		}

		/* translators: %d: the number of failing recommended checks. */
		return sprintf( _n( '%d recommended check fails.', '%d recommended checks fail.', $recommended_failed, 'klarna-checkout-for-woocommerce' ), $recommended_failed );
	}

	/**
	 * Returns the HTML tags allowed in a check message, for use with wp_kses().
	 *
	 * @return array The allowed HTML tags.
	 */
	private function allowed_message_html() {
		return array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		);
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
