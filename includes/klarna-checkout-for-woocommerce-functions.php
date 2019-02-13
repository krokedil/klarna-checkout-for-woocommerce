<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Echoes Klarna Checkout iframe snippet.
 */
function kco_wc_show_snippet() {
	$klarna_order = KCO_WC()->api->get_order();
	echo KCO_WC()->api->get_snippet( $klarna_order );
}

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

/**
 * Shows extra fields in Klarna Checkout page.
 */
function kco_wc_show_extra_fields() {
	// Clear extra fields session values on reload.
	// WC()->session->__unset( 'kco_wc_extra_fields_values' );
	echo '<div id="kco-extra-fields">';
	do_action( 'kco_wc_before_extra_fields' );

	$extra_fields_values          = WC()->session->get( 'kco_wc_extra_fields_values', array() );
	$kco_wc_extra_checkout_fields = new Klarna_Checkout_For_WooCommerce_Extra_Checkout_Fields();
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
							   id="createaccount" <?php checked( ( true === WC()->checkout()->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?>
							   type="checkbox" name="createaccount" value="1"/>
						<span><?php _e( 'Create an account?', 'klarna-checkout-for-woocommerce' ); ?></span>
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
	do_action( 'woocommerce_before_order_notes', WC()->checkout() );
	if ( apply_filters( 'woocommerce_enable_order_notes_field', true ) ) {
		foreach ( $extra_fields['order'] as $key => $field ) {
			$key_value = array_key_exists( $key, $extra_fields_values ) ? $extra_fields_values[ $key ] : '';
			woocommerce_form_field( $key, $field, $key_value );
		}
	}
	do_action( 'woocommerce_after_order_notes', WC()->checkout() );
	do_action( 'kco_wc_after_extra_fields' );
	echo '</div>';
}

/**
 * Shows select another payment method button in Klarna Checkout page.
 */
function kco_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_kco_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'klarna-checkout-for-woocommerce' );

		?>
		<p style="margin-top:30px">
			<a class="checkout-button button" href="#" id="klarna-checkout-select-other">
				<?php echo $select_another_method_text; ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Is it OK to prefill customer data?
 */
function kco_wc_prefill_allowed() {
	$base_location = wc_get_base_location();

	if ( 'DE' === $base_location['country'] || 'AT' === $base_location['country'] ) {
		$settings                = get_option( 'woocommerce_kco_settings' );
		$consent_setting_checked = ( isset( $settings['prefill_consent'] ) && 'yes' === $settings['prefill_consent'] );

		if ( $consent_setting_checked && is_user_logged_in() && WC()->session->get( 'kco_wc_prefill_consent', false ) ) {
			return true;
		}

		return false;
	}

	return true;
}

/**
 * Calculates cart totals.
 */
function kco_wc_calculate_totals() {
	WC()->cart->calculate_fees();
	WC()->cart->calculate_totals();
}

function kco_wc_show_payment_method_field() {
	?>
	<input style="display:none" type="radio" name="payment_method" value="kco"/>
	<?php
}

/**
 * Shows prefill consent text.
 */
function kco_wc_prefill_consent() {
	if ( ! kco_wc_prefill_allowed() && is_user_logged_in() ) {
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
		<p><a href="#TB_inline?width=600&height=550&inlineId=consent-text"
			  class="thickbox"><?php echo $link_text; ?></a>
		</p>
		<div id="consent-text" style="display:none;">
			<p><?php echo $popup_text; ?></p>
		</div>
		<?php
	}
}

/**
 * Converts 3-letter ISO returned from Klarna to 2-letter code used in WooCommerce.
 *
 * @param $country
 */
function kco_wc_country_code_converter( $country ) {
	$countries = array(
		'AF' => 'AFG', // Afghanistan.
		'AX' => 'ALA', // Aland Islands.
		'AL' => 'ALB', // Albania.
		'DZ' => 'DZA', // Algeria.
		'AS' => 'ASM', // American Samoa.
		'AD' => 'AND', // Andorra.
		'AO' => 'AGO', // Angola.
		'AI' => 'AIA', // Anguilla.
		'AQ' => 'ATA', // Antarctica.
		'AG' => 'ATG', // Antigua and Barbuda.
		'AR' => 'ARG', // Argentina.
		'AM' => 'ARM', // Armenia.
		'AW' => 'ABW', // Aruba.
		'AU' => 'AUS', // Australia.
		'AT' => 'AUT', // Austria.
		'AZ' => 'AZE', // Azerbaijan.
		'BS' => 'BHS', // Bahamas.
		'BH' => 'BHR', // Bahrain.
		'BD' => 'BGD', // Bangladesh.
		'BB' => 'BRB', // Barbados.
		'BY' => 'BLR', // Belarus.
		'BE' => 'BEL', // Belgium.
		'BZ' => 'BLZ', // Belize.
		'BJ' => 'BEN', // Benin.
		'BM' => 'BMU', // Bermuda.
		'BT' => 'BTN', // Bhutan.
		'BO' => 'BOL', // Bolivia.
		'BQ' => 'BES', // Bonaire, Saint Estatius and Saba.
		'BA' => 'BIH', // Bosnia and Herzegovina.
		'BW' => 'BWA', // Botswana.
		'BV' => 'BVT', // Bouvet Islands.
		'BR' => 'BRA', // Brazil.
		'IO' => 'IOT', // British Indian Ocean Territory.
		'BN' => 'BRN', // Brunei.
		'BG' => 'BGR', // Bulgaria.
		'BF' => 'BFA', // Burkina Faso.
		'BI' => 'BDI', // Burundi.
		'KH' => 'KHM', // Cambodia.
		'CM' => 'CMR', // Cameroon.
		'CA' => 'CAN', // Canada.
		'CV' => 'CPV', // Cape Verde.
		'KY' => 'CYM', // Cayman Islands.
		'CF' => 'CAF', // Central African Republic.
		'TD' => 'TCD', // Chad.
		'CL' => 'CHL', // Chile.
		'CN' => 'CHN', // China.
		'CX' => 'CXR', // Christmas Island.
		'CC' => 'CCK', // Cocos (Keeling) Islands.
		'CO' => 'COL', // Colombia.
		'KM' => 'COM', // Comoros.
		'CG' => 'COG', // Congo.
		'CD' => 'COD', // Congo, Democratic Republic of the.
		'CK' => 'COK', // Cook Islands.
		'CR' => 'CRI', // Costa Rica.
		'CI' => 'CIV', // Côte d\'Ivoire.
		'HR' => 'HRV', // Croatia.
		'CU' => 'CUB', // Cuba.
		'CW' => 'CUW', // Curaçao.
		'CY' => 'CYP', // Cyprus.
		'CZ' => 'CZE', // Czech Republic.
		'DK' => 'DNK', // Denmark.
		'DJ' => 'DJI', // Djibouti.
		'DM' => 'DMA', // Dominica.
		'DO' => 'DOM', // Dominican Republic.
		'EC' => 'ECU', // Ecuador.
		'EG' => 'EGY', // Egypt.
		'SV' => 'SLV', // El Salvador.
		'GQ' => 'GNQ', // Equatorial Guinea.
		'ER' => 'ERI', // Eritrea.
		'EE' => 'EST', // Estonia.
		'ET' => 'ETH', // Ethiopia.
		'FK' => 'FLK', // Falkland Islands.
		'FO' => 'FRO', // Faroe Islands.
		'FJ' => 'FIJ', // Fiji.
		'FI' => 'FIN', // Finland.
		'FR' => 'FRA', // France.
		'GF' => 'GUF', // French Guiana.
		'PF' => 'PYF', // French Polynesia.
		'TF' => 'ATF', // French Southern Territories.
		'GA' => 'GAB', // Gabon.
		'GM' => 'GMB', // Gambia.
		'GE' => 'GEO', // Georgia.
		'DE' => 'DEU', // Germany.
		'GH' => 'GHA', // Ghana.
		'GI' => 'GIB', // Gibraltar.
		'GR' => 'GRC', // Greece.
		'GL' => 'GRL', // Greenland.
		'GD' => 'GRD', // Grenada.
		'GP' => 'GLP', // Guadeloupe.
		'GU' => 'GUM', // Guam.
		'GT' => 'GTM', // Guatemala.
		'GG' => 'GGY', // Guernsey.
		'GN' => 'GIN', // Guinea.
		'GW' => 'GNB', // Guinea-Bissau.
		'GY' => 'GUY', // Guyana.
		'HT' => 'HTI', // Haiti.
		'HM' => 'HMD', // Heard Island and McDonald Islands.
		'VA' => 'VAT', // Holy See (Vatican City State).
		'HN' => 'HND', // Honduras.
		'HK' => 'HKG', // Hong Kong.
		'HU' => 'HUN', // Hungary.
		'IS' => 'ISL', // Iceland.
		'IN' => 'IND', // India.
		'ID' => 'IDN', // Indonesia.
		'IR' => 'IRN', // Iran.
		'IQ' => 'IRQ', // Iraq.
		'IE' => 'IRL', // Republic of Ireland.
		'IM' => 'IMN', // Isle of Man.
		'IL' => 'ISR', // Israel.
		'IT' => 'ITA', // Italy.
		'JM' => 'JAM', // Jamaica.
		'JP' => 'JPN', // Japan.
		'JE' => 'JEY', // Jersey.
		'JO' => 'JOR', // Jordan.
		'KZ' => 'KAZ', // Kazakhstan.
		'KE' => 'KEN', // Kenya.
		'KI' => 'KIR', // Kiribati.
		'KP' => 'PRK', // Korea, Democratic People's Republic of.
		'KR' => 'KOR', // Korea, Republic of (South).
		'KW' => 'KWT', // Kuwait.
		'KG' => 'KGZ', // Kyrgyzstan.
		'LA' => 'LAO', // Laos.
		'LV' => 'LVA', // Latvia.
		'LB' => 'LBN', // Lebanon.
		'LS' => 'LSO', // Lesotho.
		'LR' => 'LBR', // Liberia.
		'LY' => 'LBY', // Libya.
		'LI' => 'LIE', // Liechtenstein.
		'LT' => 'LTU', // Lithuania.
		'LU' => 'LUX', // Luxembourg.
		'MO' => 'MAC', // Macao S.A.R., China.
		'MK' => 'MKD', // Macedonia.
		'MG' => 'MDG', // Madagascar.
		'MW' => 'MWI', // Malawi.
		'MY' => 'MYS', // Malaysia.
		'MV' => 'MDV', // Maldives.
		'ML' => 'MLI', // Mali.
		'MT' => 'MLT', // Malta.
		'MH' => 'MHL', // Marshall Islands.
		'MQ' => 'MTQ', // Martinique.
		'MR' => 'MRT', // Mauritania.
		'MU' => 'MUS', // Mauritius.
		'YT' => 'MYT', // Mayotte.
		'MX' => 'MEX', // Mexico.
		'FM' => 'FSM', // Micronesia.
		'MD' => 'MDA', // Moldova.
		'MC' => 'MCO', // Monaco.
		'MN' => 'MNG', // Mongolia.
		'ME' => 'MNE', // Montenegro.
		'MS' => 'MSR', // Montserrat.
		'MA' => 'MAR', // Morocco.
		'MZ' => 'MOZ', // Mozambique.
		'MM' => 'MMR', // Myanmar.
		'NA' => 'NAM', // Namibia.
		'NR' => 'NRU', // Nauru.
		'NP' => 'NPL', // Nepal.
		'NL' => 'NLD', // Netherlands.
		'AN' => 'ANT', // Netherlands Antilles.
		'NC' => 'NCL', // New Caledonia.
		'NZ' => 'NZL', // New Zealand.
		'NI' => 'NIC', // Nicaragua.
		'NE' => 'NER', // Niger.
		'NG' => 'NGA', // Nigeria.
		'NU' => 'NIU', // Niue.
		'NF' => 'NFK', // Norfolk Island.
		'MP' => 'MNP', // Northern Mariana Islands.
		'NO' => 'NOR', // Norway.
		'OM' => 'OMN', // Oman.
		'PK' => 'PAK', // Pakistan.
		'PW' => 'PLW', // Palau.
		'PS' => 'PSE', // Palestinian Territory.
		'PA' => 'PAN', // Panama.
		'PG' => 'PNG', // Papua New Guinea.
		'PY' => 'PRY', // Paraguay.
		'PE' => 'PER', // Peru.
		'PH' => 'PHL', // Philippines.
		'PN' => 'PCN', // Pitcairn.
		'PL' => 'POL', // Poland.
		'PT' => 'PRT', // Portugal.
		'PR' => 'PRI', // Puerto Rico.
		'QA' => 'QAT', // Qatar.
		'RE' => 'REU', // Reunion.
		'RO' => 'ROU', // Romania.
		'RU' => 'RUS', // Russia.
		'RW' => 'RWA', // Rwanda.
		'BL' => 'BLM', // Saint Bartholemy.
		'SH' => 'SHN', // Saint Helena.
		'KN' => 'KNA', // Saint Kitts and Nevis.
		'LC' => 'LCA', // Saint Lucia.
		'MF' => 'MAF', // Saint Martin (French part).
		'SX' => 'SXM', // Sint Maarten / Saint Martin (Dutch part).
		'PM' => 'SPM', // Saint Pierre and Miquelon.
		'VC' => 'VCT', // Saint Vincent and the Grenadines.
		'WS' => 'WSM', // Samoa.
		'SM' => 'SMR', // San Marino.
		'ST' => 'STP', // Sso Tome and Principe.
		'SA' => 'SAU', // Saudi Arabia.
		'SN' => 'SEN', // Senegal.
		'RS' => 'SRB', // Serbia.
		'SC' => 'SYC', // Seychelles.
		'SL' => 'SLE', // Sierra Leone.
		'SG' => 'SGP', // Singapore.
		'SK' => 'SVK', // Slovakia.
		'SI' => 'SVN', // Slovenia.
		'SB' => 'SLB', // Solomon Islands.
		'SO' => 'SOM', // Somalia.
		'ZA' => 'ZAF', // South Africa.
		'GS' => 'SGS', // South Georgia/Sandwich Islands.
		'SS' => 'SSD', // South Sudan.
		'ES' => 'ESP', // Spain.
		'LK' => 'LKA', // Sri Lanka.
		'SD' => 'SDN', // Sudan.
		'SR' => 'SUR', // Suriname.
		'SJ' => 'SJM', // Svalbard and Jan Mayen.
		'SZ' => 'SWZ', // Swaziland.
		'SE' => 'SWE', // Sweden.
		'CH' => 'CHE', // Switzerland.
		'SY' => 'SYR', // Syria.
		'TW' => 'TWN', // Taiwan.
		'TJ' => 'TJK', // Tajikistan.
		'TZ' => 'TZA', // Tanzania.
		'TH' => 'THA', // Thailand.
		'TL' => 'TLS', // Timor-Leste.
		'TG' => 'TGO', // Togo.
		'TK' => 'TKL', // Tokelau.
		'TO' => 'TON', // Tonga.
		'TT' => 'TTO', // Trinidad and Tobago.
		'TN' => 'TUN', // Tunisia.
		'TR' => 'TUR', // Turkey.
		'TM' => 'TKM', // Turkmenistan.
		'TC' => 'TCA', // Turks and Caicos Islands.
		'TV' => 'TUV', // Tuvalu.
		'UG' => 'UGA', // Uganda.
		'UA' => 'UKR', // Ukraine.
		'AE' => 'ARE', // United Arab Emirates.
		'GB' => 'GBR', // United Kingdom.
		'US' => 'USA', // United States.
		'UM' => 'UMI', // United States Minor Outlying Islands.
		'UY' => 'URY', // Uruguay.
		'UZ' => 'UZB', // Uzbekistan.
		'VU' => 'VUT', // Vanuatu.
		'VE' => 'VEN', // Venezuela.
		'VN' => 'VNM', // Vietnam.
		'VG' => 'VGB', // Virgin Islands, British.
		'VI' => 'VIR', // Virgin Island, U.S..
		'WF' => 'WLF', // Wallis and Futuna.
		'EH' => 'ESH', // Western Sahara.
		'YE' => 'YEM', // Yemen.
		'ZM' => 'ZMB', // Zambia.
		'ZW' => 'ZWE', // Zimbabwe.
	);

	return array_search( strtoupper( $country ), $countries, true );
}

/**
 * Prints error notices if needed.
 */
function kco_wc_print_notices() {
	if ( isset( $_GET['stock_validate_failed'] ) ) {
		wc_add_notice( __( 'Not all products are in stock.', 'klarna-checkout-for-woocommerce' ), 'error' );
	} elseif ( isset( $_GET['no_shipping'] ) ) {
		wc_add_notice( __( 'No shipping was selected.', 'klarna-checkout-for-woocommerce' ), 'error' );
	} elseif ( isset( $_GET['required_fields'] ) ) {
		$failed_fields = json_decode( base64_decode( $_GET['required_fields'] ) );
		$fields_string = '';
		foreach ( $failed_fields as $field ) {
			$fields_string = $fields_string . ' ' . $field;
		}
		wc_add_notice( __( sprintf( 'The following fields are required:%s.', $fields_string ), 'klarna-checkout-for-woocommerce' ), 'error' );
	} elseif ( isset( $_GET['invalid_coupon'] ) ) {
		wc_add_notice( __( 'Invalid coupon.', 'klarna-checkout-for-woocommerce' ), 'error' );
	} elseif ( isset( $_GET['needs_login'] ) ) {
		wc_add_notice( __( 'You must be logged in to checkout.', 'woocommerce' ), 'error' );
	} elseif ( isset( $_GET['email_exists'] ) ) {
		wc_add_notice( __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ), 'error' );
	} elseif ( isset( $_GET['totals_dont_match'] ) ) {
		wc_add_notice( __( 'A mismatch in order totals between WooCommerce and Klarna was detected. Please try again.', 'klarna-checkout-for-woocommerce' ), 'error' );
	} elseif ( isset( $_GET['unable_to_process'] ) ) {
		wc_add_notice( __( 'We were unable to process your order, please try again.', 'woocommerce' ), 'error' );
	}
}

/**
 * Save cart hash to KCO session.
 */
function kco_wc_save_cart_hash() {
	WC()->cart->calculate_totals();
	$cart_hash = md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
	WC()->session->set( 'kco_cart_hash', $cart_hash );
}

function is_kco_confirmation() {
	if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['kco_wc_order_id'] ) ) {
		return true;
	}

	return false;
}
