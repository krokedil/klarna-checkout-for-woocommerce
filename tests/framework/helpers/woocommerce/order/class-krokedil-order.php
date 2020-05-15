<?php // phpcs:ignore
/**
 * Helper order class
 */

/**
 * This is the class just for testing purpose
 *
 * @package Krokedil/tests
 */

/**
 * Class Krokedil_Order.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class Krokedil_Order implements IKrokedil_WC_Order, Krokedil_Order_Status {

	/**
	 * Order data.
	 *
	 * @var array $data order data.
	 */
	protected $data = [
		'status'        => null,
		'customer_id'   => null,
		'customer_note' => null,
		'parent'        => null,
		'created_via'   => null,
		'cart_hash'     => null,
		'order_id'      => 0,
		'first_name'    => 'Elkrokedilo',
		'last_name'     => 'Krokedil',
		'company'       => 'Code pursuit',
		'address1'      => 'Super address 1',
		'address2'      => '',
		'city'          => 'Arvika',
		'state'         => 'SE',
		'postcode'      => 12345,
		'country'       => 'RS',
		'emial'         => 'mail@mail.com',
		'phone'         => '555-32123',
	];

	/**
	 * Totals
	 *
	 * @var array key value pair
	 */

	protected $totals = [
		'shipping_total' => 10,
		'discount_total' => 0,
		'discount_tax'   => 0,
		'cart_tax'       => 0,
		'shipping_tax'   => 0,
		'total'          => 50,
	];

	/**
	 * Product.
	 *
	 * @var WC_Product|null
	 */
	protected $product = null;

	/**
	 * Customer
	 *
	 * @var WC_Customer|null $customer customer.
	 */
	protected $customer = null;

	/**
	 * Items.
	 *
	 * @var array $product_items items.
	 */
	protected $product_items = null;

	/**
	 * Rate
	 *
	 * @var WC_Shipping_Rate $rate rate
	 */
	protected $rate = null;

	/**
	 * Item
	 *
	 * @var array $items items
	 */
	protected $items = [];

	/**
	 * Krokedil_Order constructor.
	 *
	 * @param WC_Product  $product product.
	 * @param WC_Customer $customer customer.
	 * @param array       $items items.
	 * @param array       $data data.
	 * @param null        $rate rate.
	 */
	public function __construct( $product = null, $customer = null, array $items = [], $data = [], $rate = null ) {
		$this->set_product( $product );
		$this->set_customer( $customer );
		$this->set_data( $data );
		$this->set_rate( $rate );
		$this->items = $items;
		Krokedil_WC_Shipping::create_simple_flat_rate();
	}

	/**
	 * Sets customer.
	 *
	 * @param WC_Customer $customer customer.
	 */
	private function set_customer( $customer ) {
		if ( null === $customer || ! $customer instanceof WC_Customer ) {
			try {
				$this->customer = ( new Krokedil_Customer() )->create();
			} catch ( Exception $e ) {
				exit( $e->getMessage() ); // phpcs:ignore
			}
			return;
		}
		$this->customer = $customer;
	}

	/**
	 * Sets an product.
	 *
	 * @param WC_Product $product product.
	 */
	private function set_product( $product ) {
		if ( null === $product || ! ( $product instanceof WC_Product ) ) {
			$this->product = ( new Krokedil_Simple_Product(
				[
					'name'          => 'Simple product name',
					'regular_price' => 10,
					'sale_price'    => 9,
				]
			) )->create();
			return;
		}
		$this->product = $product;
	}

	/**
	 * Sets rate
	 *
	 * @param WC_Shipping_Rate $rate rate.
	 */
	private function set_rate( $rate ) {
		if ( null === $rate || ! $rate instanceof WC_Shipping_Rate ) {
			$this->rate = ( new Krokedil_Shipping_Rate() )->get_shipping_rate();
		}
	}

	/**
	 * Set order items.
	 *
	 * @param array $items items.
	 */
	private function set_items( $items ) {
		if ( ! empty( $items ) ) {
			$this->items = $items;
		}
	}



	/**
	 * Sets order data
	 *
	 * @param array $data data.
	 */
	private function set_data( $data ) {
		$this->data = wp_parse_args( $data, $this->data );
	}

	/**
	 * Sets product item
	 *
	 * @param array $product_items items.
	 */
	private function set_product_items( array $product_items ) {
		if ( empty( $product_items ) ) {
			$this->product_items[] = ( new Krokedil_Order_Item_Product(
				[
					'product'  => $this->product,
					'quantity' => 4,
					'subtotal' => wc_get_price_excluding_tax( $this->product, array( 'qty' => 4 ) ),
					'total'    => wc_get_price_excluding_tax( $this->product, array( 'qty' => 4 ) ),
				]
			) )->create();
		} else {
			foreach ( $product_items as $item ) {
				$item->save();
				$this->product_items[] = $item;
			}
		}
	}

	/**
	 * List of order statuses.
	 *
	 * @return array
	 */
	public function get_statuses(): array {
		return Krokedil_Order_Status::STATUSES;
	}

	/**
	 * Return order id.
	 *
	 * @return int
	 */
	public function get_order_id(): int {
		return $this->data['order_id'];
	}

	/**
	 * Return order id.
	 *
	 * @return int
	 */
	public function get_customer_id(): int {
		return $this->data['customer_id'];
	}

	/**
	 * Indicate whether to save or not.
	 */
	public function save(): bool {
		return true;
	}

	/**
	 * Creates order.
	 *
	 * @return WC_Order
	 */
	public function create(): WC_Order {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception.
		/**
		 * Order
		 *
		 * @var WC_Order $order
		 */
		$order = wc_create_order( $this->data );
		foreach ( $this->items as $item ) {
			$order->add_item( $item );
		}
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );
		// Totals.
		foreach ( $this->totals as $key => $value ) {
			$order->{"set_$key"}( $value );
		}
		$order->save();
		return $order;
	}
}
