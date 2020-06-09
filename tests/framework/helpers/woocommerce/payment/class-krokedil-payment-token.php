<?php

/**
 * Class Krokedil_Payment_Token
 */
class Krokedil_Payment_Token extends WC_Payment_Token {

	/**
	 * @var string
	 */
	protected $type = 'stub';

	/**
	 * @return mixed
	 */
	public function get_extra() {
		return $this->get_meta( 'extra' );
	}

	/**
	 * @param $extra
	 */
	public function set_extra( $extra ) {
		$this->add_meta_data( 'extra', $extra, true );
	}
}