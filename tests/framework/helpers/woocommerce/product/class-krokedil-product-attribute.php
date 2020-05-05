<?php // phpcs:ignore
/**
 *
 */

/**
 *
 */
class Krokedil_Product_Attribute {

	/**
	 * Product attribute
	 *
	 * @var WC_Product_Attribute $product_attribute
	 */
	protected $product_attribute;

	/**
	 * Name
	 *
	 * @var string $name
	 */
	protected $name;

	/**
	 * Terms
	 *
	 * @var array $terms
	 */
	protected $terms;

	/**
	 * Attribute data
	 *
	 * @var array $attribute_data
	 */
	protected $attribute_data = [];


	/**
	 * Krokedil_Product_Attribute constructor.
	 *
	 * @param string $name name.
	 * @param array  $terms terms.
	 */
	public function __construct( $name, $terms ) {
		$this->name  = $name;
		$this->terms = $terms;
		$this->init_data();
	}

	/**
	 * Init data attributes
	 */
	public function init_data() {
		if ( ! empty( $this->name ) && ! empty( $this->terms ) ) {
			$this->attribute_data = self::create_attribute( $this->name, $this->terms );
		} else {
			$this->attribute_data = self::create_attribute();
		}
		$this->product_attribute = new WC_Product_Attribute();
		$this->product_attribute->set_id( $this->attribute_data['attribute_id'] );
		$this->product_attribute->set_name( $this->attribute_data['attribute_taxonomy'] );
		$this->product_attribute->set_options( $this->attribute_data['term_ids'] );
		$this->product_attribute->set_position( 1 );
		$this->product_attribute->set_visible( true );
		$this->product_attribute->set_variation( true );
	}

	/**
	 * Returns product attribute.
	 *
	 * @return WC_Product_Attribute
	 */
	public function get_product_attribute(): WC_Product_Attribute {
		return $this->product_attribute;
	}

	/**
	 * Create a dummy attribute.
	 *
	 * @since 2.3
	 *
	 * @param string        $raw_name Name of attribute to create.
	 * @param array(string) $terms          Terms to create for the attribute.
	 * @return array
	 */
	public static function create_attribute( $raw_name = 'size', $terms = array( 'small' ) ): array {
		/**
		 *  Helps IDE
		 *
		 * @var WC_Product_Attribute[] $wc_product_attributes
		 */
		global $wc_product_attributes;

		// Make sure caches are clean.
		delete_transient( 'wc_attribute_taxonomies' );
		WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = wc_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		if ( ! $attribute_id ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );

			// Degister taxonomy which other tests may have created...
			unregister_taxonomy( $taxonomy_name );

			$attribute_id = wc_create_attribute(
				array(
					'name'         => $raw_name,
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => 0,
				)
			);

			// Register as taxonomy.
			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					array(
						'labels'       => array(
							'name' => $raw_name,
						),
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				)
			);

			// Set product attributes global.
			$wc_product_attributes = [];

			foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
				$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
			}
		}

		$attribute = wc_get_attribute( $attribute_id );
		$return    = array(
			'attribute_name'     => $attribute->name,
			'attribute_taxonomy' => $attribute->slug,
			'attribute_id'       => $attribute_id,
			'term_ids'           => [],
		);

		foreach ( $terms as $term ) {
			$result = term_exists( $term, $attribute->slug );

			if ( ! $result ) {
				$result               = wp_insert_term( $term, $attribute->slug );
				$return['term_ids'][] = $result['term_id'];
			} else {
				$return['term_ids'][] = $result['term_id'];
			}
		}

		return $return;
	}
}
