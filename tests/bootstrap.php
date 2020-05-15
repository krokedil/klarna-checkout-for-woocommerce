<?php //phpcs:ignore
/**
 *  PHPUnit bootstrap file.
 */

/**
 * PHPUnit bootstrap file
 *
 * @author Krokedil
 * @package Krokedil_Unit_Tests_Bootstrap
 * @since 1.0.0
 */
class Krokedil_Unit_Tests_Bootstrap {

	/**
	 * Instance of this class
	 *
	 * @var Krokedil_Unit_Tests_Bootstrap instance
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * WordPress tests library
	 *
	 * @var string directory where wordpress-tests-lib is installed
	 * @since 1.0.0
	 */
	protected $wp_tests_dir;

	/**
	 * Test directory
	 *
	 * @var string testing directory
	 * @since 1.0.0
	 */
	protected $tests_dir;

	/**
	 * Plugin directory
	 *
	 * @var string plugin directory
	 * @since 1.0.0
	 * */
	protected $plugin_dir;

	/**
	 * Plugins path.
	 *
	 * @var string
	 */
	protected $plugins_dir;

	/**
	 * Plugin dependencies.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $dependencies = array(
		'woocommerce' => 'woocommerce.php',
	);

	/**
	 * Plugin configuration
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 *
	 * Setup the unit testing environment.
	 *
	 * @throws Exception If file does not exist.
	 */
	public function __construct() {
		if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . 'config.php' ) ) {
			$this->config = require 'config.php';
		} else {
			throw new Exception( 'Configuration file is missing!' );
		}

		// init plugin paths.
		$this->set_paths();

		// load test functions.
		require_once $this->wp_tests_dir . '/includes/functions.php';

		// activate plugin.
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugin' ) );

		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// load the WP testing environment.
		require_once $this->wp_tests_dir . '/includes/bootstrap.php';

		$this->includes();

	}

	/**
	 * Sets required paths.
	 *
	 * @since 1.0.0
	 */
	protected function set_paths() {

		// plugin test dir.
		$this->tests_dir = __DIR__;

		// plugin path.
		$this->plugin_dir = dirname( $this->tests_dir );

		// plugins path.
		$this->plugins_dir = realpath( $this->plugin_dir . DIRECTORY_SEPARATOR . '..' );

		// wordpress-tests-lib.
		$this->wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

	}


	/**
	 * Load plugin.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin() {
		if ( ! empty( $this->dependencies ) ) {
			foreach ( $this->dependencies as $dir => $plugin_file ) {
				echo $this->plugins_dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $plugin_file;
				require_once $this->plugins_dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $plugin_file;
			}
		}
		require_once $this->plugin_dir . DIRECTORY_SEPARATOR . $this->config['name'];
	}

	/**
	 * Load krokedil-specific test cases.
	 *
	 * @since 1.0.0
	 */
	public function includes() {
		// framework.
		require_once $this->tests_dir . '/framework/krokedil/core/class-krokedil-constants.php';
		require_once $this->tests_dir . '/framework/krokedil/core/class-krokedil-crud.php';
		require_once $this->tests_dir . '/framework/krokedil/core/interface-krokedil-wc.php';
		require_once $this->tests_dir . '/framework/krokedil/core/abstract-class-krokedil-unit-test-case.php';

		// order.
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/interface-krokedil-order.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/interface-krokedil-order-statues.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/interface-krokedil-order-item-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/class-krokedil-order.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/class-krokedil-order-item-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/order/interface-krokedil-order.php';

		// product.
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/interface-krokedil-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/abstract-class-krokedil-product-helper.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/class-krokedil-simple-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/class-krokedil-external-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/class-krokedil-grouped-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/class-krokedil-variable-product.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/product/class-krokedil-variation-product.php';
		// customer.
		require_once $this->tests_dir . '/framework/helpers/woocommerce/customer/interface-krokedil-customer.php';
		require_once $this->tests_dir . '/framework/helpers/woocommerce/customer/class-krokedil-customer.php';
		// shipping
		require_once $this->tests_dir . '/framework/helpers/woocommerce/shipping/class-krokedil-wc-shipping.php';
	}

	/**
	 * Get the single class instance.
	 *
	 * @return Krokedil_Unit_Tests_Bootstrap
	 * @throws Exception If the configuration file does not exist.
	 * @since 1.0.0
	 */
	public static function get_instance(): \Krokedil_Unit_Tests_Bootstrap {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function install_wc() {
		WC_Install::install();

		// Initialize the WC API extensions.
		\Automattic\WooCommerce\Admin\Install::create_tables();
		\Automattic\WooCommerce\Admin\Install::create_events();

		// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
		if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
			$GLOBALS['wp_roles']->reinit();
		} else {
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_roles();
		}

		echo esc_html( 'Installing WooCommerce...' . PHP_EOL );
	}
}

try {
	Krokedil_Unit_Tests_Bootstrap::get_instance();
} catch ( Exception $e ) {
	echo $e->getMessage() . PHP_EOL; // phpcs:ignore
}


