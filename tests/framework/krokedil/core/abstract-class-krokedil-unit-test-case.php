<?php // phpcs:ignore
/**
 * Undocumented class
 */

/**
 * Undocumented class
 */
abstract class AKrokedil_Unit_Test_Case extends WP_UnitTestCase implements IKrokedilConstants, IKrokedil_CRUD {
	/**
	 * Is multi site or not
	 *
	 * @var boolean
	 */
	protected $is_multi_site = false;
	/**
	 * A interface that's provide crud functionality.
	 *
	 * @var IKrokedil_CRUD
	 */
	private $crud;

	/**
	 * Class constructor.
	 * Initialize required plugins
	 *
	 * @throws Exception Error message.
	 * @see WP_UnitTestCase
	 */
	public function __construct() {
		$this->activate_plugins();
		parent::__construct();
	}


	/**
	 * List of a plugins.
	 *
	 * @var array
	 */
	public $plugins = array(
		// key is a dir and a value is filename.php.
		'woocommerce' => 'woocommerce.php',
	);

	/**
	 * Activate plugins
	 *
	 * @return void
	 */
	protected function activate_plugins() {
		foreach ( $this->plugins as $dir => $plugin_file ) {
			// if a plugin is not already active, activate it then.
			if ( ! is_plugin_active( self::DS . $dir . self::DS . $plugin_file ) ) {
				// activate plugin.
				do_action( 'activate_' . self::PLUGIN_BASENAME . self::DS . $dir . self::DS . $plugin_file );
			}
		}
	}

	/**
	 * It must be defined as final to prevent overriding in a subclass definition.
	 * Prepare the environment for the test.
	 *
	 * @return void
	 */
	final public function setUp() {
		$this->create();
		$this->update();
		$this->view();
	}

	/**
	 * Function is called after every test.
	 *
	 * @return void
	 */
	final public function tearDown() {
		$this->delete();
	}

	/**
	 * Crud setter
	 *
	 * @param IKrokedil_CRUD $crud concrete class.
	 *
	 * @return void
	 */
	public function set_crud( IKrokedil_CRUD $crud ) {
		$this->crud = $crud;
	}

	/**
	 * Helper function for creation user.
	 *
	 * @param null       $role of the user.
	 * @param array|null $attributes of the user.
	 *
	 * @return WP_User|null
	 */
	public function create_user( $role = null, array $attributes = null ) {
		/**
		 * New user.
		 *
		 * @var $user WP_User|int
		 */
		$user = null;
		if ( empty( $attributes ) ) {
			$user = self::factory()->user->create_and_get();
		} else {
			$user = self::factory()->user->create( $attributes );
		}
		if ( ! empty( $role ) && in_array( $role, self::DEFAULT_WP_ROLES, true ) ) {
			$user->set_role( $role );
		}

		return $user;

	}

	/**
	 * /**
	 * Create product by type
	 *
	 * @param string $type product type.
	 * @param array  $data product data.
	 * @return WC_Product
	 */
	public static function create_wc_product( $type, $data ) :WC_Product {
		if ( IKrokedilConstants::PRODUCT_SIMPLE === $type ) {
			return ( new Krokedil_Simple_Product( $data ) )->create();
		}
		// todo add other product types.
	}

	/**
	 * Helper function for creation posts.
	 *
	 * @param array $data of the post.
	 *
	 * @return void
	 */
	public function create_post( array $data ) {
		self::factory()->post->create( $data );
	}

	/**
	 * Allows to add plugin on the fly
	 *
	 * @param string $plugin_dir plugin directory.
	 * @param string $plugin_file_name name of plugin file.
	 *
	 * @return void
	 */
	protected function add_plugin( $plugin_dir, $plugin_file_name ) {
		if ( ! array_key_exists( $plugin_dir, $this->plugins ) ) {
			$this->plugins[ $plugin_dir ] = $plugin_file_name;
			$this->activate_plugins();
		}
	}
}
