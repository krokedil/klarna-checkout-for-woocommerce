<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kco_wc_show_snippet' ) ) {
	/**
	 * Echoes Klarna Checkout iframe snippet.
	 */
	function kco_wc_show_snippet() {
		$klarna_order = KCO_WC()->api->get_order();
		echo KCO_WC()->api->get_snippet( $klarna_order );
	}
}

if ( ! function_exists( 'kco_wc_show_order_notes' ) ) {
	/**
	 * Shows order notes field in Klarna Checkout page.
	 */
	function kco_wc_show_order_notes() {
		$order_fields = WC()->checkout()->get_checkout_fields( 'order' );
		$key          = 'order_comments';
		if ( array_key_exists( $key, $order_fields ) ) {
			$order_notes_field = $order_fields[ $key ];
			woocommerce_form_field( $key, $order_notes_field, WC()->checkout()->get_value( $key ) );
		}
	}
}

if ( ! function_exists( 'kco_wc_show_extra_fields' ) ) {
	/**
	 * Shows extra fields in Klarna Checkout page.
	 */
	function kco_wc_show_extra_fields() {
		// Clear extra fields session values on reload.
		// WC()->session->__unset( 'kco_wc_extra_fields_values' );

		echo '<div id="kco-extra-fields">';

		$extra_fields_values          = WC()->session->get( 'kco_wc_extra_fields_values', array() );
		$kco_wc_extra_checkout_fields = new Klarna_Checkout_For_WooCommerce_Extra_Checkout_Fields;
		$extra_fields                 = $kco_wc_extra_checkout_fields->get_remaining_checkout_fields();

		// Billing.
		do_action( 'woocommerce_before_checkout_billing_form', WC()->checkout() );
		foreach ( $extra_fields['billing'] as $key => $field ) {
			if ( isset( $field['country_field'], $default_billing_fields[ $field['country_field'] ] ) ) {
				$field['country'] = WC()->checkout()->get_value( $field['country_field'] );
			}
			$key_value = array_key_exists( $key, $extra_fields_values ) ? $extra_fields_values[ $key ] : '';
			woocommerce_form_field( $key, $field, $key_value );
		}
		do_action( 'woocommerce_after_checkout_billing_form', WC()->checkout() );

		if ( ! is_user_logged_in() && WC()->checkout()->is_registration_enabled() ) { ?>
			<div class="woocommerce-account-fields">
				<?php if ( ! WC()->checkout()->is_registration_required() ) { ?>
					<p class="form-row form-row-wide create-account">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
							       id="createaccount" <?php checked( ( true === WC()->checkout()->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ) ?>
							       type="checkbox" name="createaccount" value="1"/>
							<span><?php _e( 'Create an account?', 'woocommerce' ); ?></span>
						</label>
					</p>
				<?php } ?>

				<?php do_action( 'woocommerce_before_checkout_registration_form', WC()->checkout() ); ?>

				<?php if ( WC()->checkout()->get_checkout_fields( 'account' ) ) { ?>

					<div class="create-account">
						<?php foreach ( WC()->checkout()->get_checkout_fields( 'account' ) as $key => $field ) { ?>
							<?php woocommerce_form_field( $key, $field, WC()->checkout()->get_value( $key ) ); ?>
						<?php } ?>
						<div class="clear"></div>
					</div>

				<?php } ?>

				<?php do_action( 'woocommerce_after_checkout_registration_form', WC()->checkout() ); ?>
			</div>
			<?php
		}

		// Shipping.
		do_action( 'woocommerce_before_checkout_shipping_form', WC()->checkout() );
		foreach ( $extra_fields['shipping'] as $key => $field ) {
			if ( isset( $field['country_field'], $default_shipping_fields[ $field['country_field'] ] ) ) {
				$field['country'] = WC()->checkout()->get_value( $field['country_field'] );
			}
			$key_value = array_key_exists( $key, $extra_fields_values ) ? $extra_fields_values[ $key ] : '';
			woocommerce_form_field( $key, $field, $key_value );
		}
		do_action( 'woocommerce_after_checkout_shipping_form', WC()->checkout() );

		// Order.
		foreach ( $extra_fields['order'] as $key => $field ) {
			$key_value = array_key_exists( $key, $extra_fields_values ) ? $extra_fields_values[ $key ] : '';
			woocommerce_form_field( $key, $field, $key_value );
		}
		do_action( 'woocommerce_after_order_notes', WC()->checkout() );

		echo '</div>';
	}
}

/**
 * Is it OK to prefill customer data?
 */
function kco_wc_prefill_allowed() {
	if ( 'DE' === WC()->checkout()->get_value( 'billing_country' ) ) {
		if ( is_user_logged_in() && WC()->session->get( 'kco_wc_prefill_consent', false ) ) {
			return true;
		}

		return false;
	}

	return true;
}

/**
 * Shows prefill consent text.
 */
function kco_wc_prefill_consent() {
	$consent_url = add_query_arg(
		[ 'prefill_consent' => 'yes' ],
		wc_get_checkout_url()
	);

	$credentials = KCO_WC()->credentials->get_credentials_from_session();
	$merchant_id = $credentials['merchant_id'];

	if ( 'de_DE' === get_locale() ) {
		$button_text = 'Meine Adressdaten vorausfüllen';
		$link_text   = 'Es gelten die Nutzungsbedingungen zur Datenübertragung';
		$popup_text  = 'In unserem Kassenbereich nutzen wir Klarna Checkout. Dazu werden Ihre Daten, wie E-Mail-Adresse, Vor- und
			Nachname, Geburtsdatum, Adresse und Telefonnummer, soweit erforderlich, automatisch an Klarna AB übertragen,
			sobald Sie in den Kassenbereich gelangen. Die Nutzungsbedingungen für Klarna Checkout finden Sie hier:
			<a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/' . $merchant_id . '/de_de/checkout" target="_blank">https://cdn.klarna.com/1.0/shared/content/legal/terms/' . $merchant_id . '/de_de/checkout</a>';
	} else {
		$button_text = 'Meine Adressdaten vorausfüllen';
		$link_text   = 'Es gelten die Nutzungsbedingungen zur Datenübertragung';
		$popup_text  = 'We use Klarna Checkout as our checkout, which offers a simplified purchase experience. When you choose to go to the checkout, your email address, first name, last name, date of birth, address and phone number may be automatically transferred to Klarna AB, enabling the provision of Klarna Checkout. These User Terms apply for the use of Klarna Checkout is available here: 
			<a target="_blank" href="https://cdn.klarna.com/1.0/shared/content/legal/terms/' . $merchant_id . '/en_us/checkout">https://cdn.klarna.com/1.0/shared/content/legal/terms/' . $merchant_id . '/en_us/checkout</a>';
	}
	?>
	<p><a class="button" href="<?php echo $consent_url; ?>"><?php echo $button_text; ?></a></p>
	<p><a href="#TB_inline?width=600&height=550&inlineId=consent-text" class="thickbox"><?php echo $link_text; ?></a></p>
	<div id="consent-text" style="display:none;">
		<p><?php echo $popup_text; ?></p>
	</div>
	<?php
}
