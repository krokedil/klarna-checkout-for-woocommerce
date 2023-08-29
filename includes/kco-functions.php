<?php
/**
 * Functions file for the plugin.
 *
 * @package  Klarna_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets a Klarna order. Either creates or updates existing order.
 *
 * @return array|null If an order could not be created or updated, NULL is returned.
 */
function kco_create_or_update_order() {
	// Need to calculate these here, because WooCommerce hasn't done it yet.
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
	if ( WC()->session->get( 'kco_wc_order_id' ) ) { // Check if we have an order id.
		// Try to update the order, if it fails try to create new order.
		$klarna_order = KCO_WC()->api->update_klarna_order( WC()->session->get( 'kco_wc_order_id' ), null, true );
		if ( ! $klarna_order ) {
			// If update order failed try to create new order.
			$klarna_order = KCO_WC()->api->create_klarna_order();
			if ( ! $klarna_order ) {
				// If failed then bail.
				return;
			}
			WC()->session->set( 'kco_wc_order_id', $klarna_order['order_id'] );
			return $klarna_order;
		}
		return $klarna_order;
	} else {
		// Create new order, since we dont have one.
		$klarna_order = KCO_WC()->api->create_klarna_order();
		if ( ! $klarna_order ) {
			return;
		}
		WC()->session->set( 'kco_wc_order_id', $klarna_order['order_id'] );
		return $klarna_order;
	}
}

/**
 * Creates or updates a Klarna order for the Pay for order feature.
 *
 * @return array|null If an order could not be created or updated, NULL is returned.
 */
function kco_create_or_update_order_pay_for_order() {
	global $wp;
	$order_id = $wp->query_vars['order-pay'];
	$order    = wc_get_order( $order_id );

	if ( $order->get_meta( 'kco_order_id', true ) ) { // Check if we have an order id.
		$klarna_order_id = $order->get_meta( 'kco_order_id', true );
		// Try to update the order, if it fails try to create new order.
		$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id, $order_id, true );
		if ( ! $klarna_order ) {
			// If update order failed try to create new order.
			$klarna_order = KCO_WC()->api->create_klarna_order( $order_id );
			if ( ! $klarna_order ) {
				// If failed then bail.
				return;
			}
			$order->update_meta_data( 'kco_wc_order_id', $klarna_order['order_id'] );
			$order->save();
			return $klarna_order;
		}
		return $klarna_order;
	} else {
		// Create new order, since we dont have one.
		$klarna_order = KCO_WC()->api->create_klarna_order( $order_id );
		if ( ! $klarna_order ) {
			return;
		}
		$order->update_meta_data( 'kco_wc_order_id', $klarna_order['order_id'] );
		$order->save();
		return $klarna_order;
	}
}

/**
 * Echoes Klarna Checkout iframe snippet.
 *
 * @param bool $pay_for_order If this is for a pay for order page or not.
 * @return void
 */
function kco_wc_show_snippet( $pay_for_order = false ) {
	if ( $pay_for_order ) {
		$klarna_order = kco_create_or_update_order_pay_for_order();
	} else {
		$klarna_order = kco_create_or_update_order();
	}

	if ( isset( $klarna_order['html_snippet'] ) ) {
		do_action( 'kco_wc_show_snippet', $klarna_order );
		echo kco_extract_script( $klarna_order['html_snippet'] );
	}
}

/**
 * Returns the HTML snippet with the script tag (and its content) removed.
 *
 * The extracted JavaScript is inserted in kco_add_inline_script. This is required since certain themes escapes the HTML snippet,
 * which renders its content (and thus the script) useless.
 *
 * @param string $html_snippet
 * @return string The HTML snippet without the script tag (and its content).
 */
function kco_extract_script( $html_snippet ) {
	preg_match( '/<script(.|\n)*\/script>/', $html_snippet, $js ); // extract the <script> tag.

	// Remove the HTML tags from the script.
	kco_add_inline_script( $js );

	// Return the snippet without script tag.
	return preg_replace(
		'/<script(.|\n)*\/script>/',
		'',
		$html_snippet
	);
}

/**
 * Inserts the script tag.
 *
 * Inserts the JavaScript that was extracted from kco_extract_script.
 *
 * @param string $js The extract JavaScript (excluding the tags).
 * @return void
 */
function kco_add_inline_script( $js ) {
	$js = preg_replace( '/<\/?script[^>]*>/', '', $js[0] );
	wp_register_script( 'kco-script', '', array(), '', true );
	wp_enqueue_script( 'kco-script' );
	wp_add_inline_script( 'kco-script', $js, 'before' );
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
 * Shows select another payment method button in Klarna Checkout page.
 */
function kco_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_kco_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'klarna-checkout-for-woocommerce' );

		?>
		<p class="klarna-checkout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="klarna-checkout-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Adds the extra checkout field div to the checkout page.
 *
 * @return void
 */
function kco_wc_add_extra_checkout_fields() {
	do_action( 'kco_wc_before_extra_fields' );
	?>
	<div id="kco-extra-checkout-fields">
	</div>
	<?php
	do_action( 'kco_wc_after_extra_fields' );
}

/**
 * Get the selected, or the first, payment method.
 */
function kco_wc_get_selected_payment_method() {
	$selected_payment_method = '';
	if ( null !== WC()->session && method_exists( WC()->session, 'get' ) && WC()->session->get( 'chosen_payment_method' ) ) {
		$selected_payment_method = WC()->session->get( 'chosen_payment_method' );
	} else {
		$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
		reset( $available_payment_gateways );
		$selected_payment_method = key( $available_payment_gateways );
	}

	return $selected_payment_method;
}

/**
 * Is it OK to prefill customer data?
 */
function kco_wc_prefill_allowed() {
	$base_location = wc_get_base_location();

	if ( 'DE' === $base_location['country'] || 'AT' === $base_location['country'] ) {
		$settings                = get_option( 'woocommerce_kco_settings' );
		$consent_setting_checked = ( in_array( 'prefill_consent', $settings, true ) && 'yes' === $settings['prefill_consent'] );

		if ( $consent_setting_checked && is_user_logged_in() && WC()->session->get( 'kco_wc_prefill_consent', false ) ) {
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
	if ( ! kco_wc_prefill_allowed() && is_user_logged_in() ) {
		$consent_url = add_query_arg(
			array( 'prefill_consent' => 'yes' ),
			wc_get_checkout_url()
		);

		$credentials = KCO_WC()->credentials->get_credentials_from_session();
		$merchant_id = $credentials['merchant_id'];

		if ( 'de_DE' === get_locale() || 'de_DE_formal' === get_locale() ) {
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
		<p><a class="button" href="<?php echo esc_attr( $consent_url ); ?>"><?php echo esc_html( $button_text ); ?></a></p>
		<p><a href="#TB_inline?width=600&height=550&inlineId=consent-text"
			class="thickbox"><?php echo esc_html( $link_text ); ?></a>
		</p>
		<div id="consent-text" style="display:none;">
			<p><?php echo esc_html( $popup_text ); ?></p>
		</div>
		<?php
	}
}

/**
 * Converts 3-letter ISO returned from Klarna to 2-letter code used in WooCommerce.
 *
 * @param string $country Country code.
 */
function kco_wc_country_code_converter( $country ) {
	$countries = kco_get_country_codes();

	return array_search( strtoupper( $country ), $countries, true );
}

/**
 * Returns a list of country codes, 2-letter ISO => 3-letter ISO.
 *
 * @return array
 */
function kco_get_country_codes() {
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

	return $countries;
}

/**
 * Checks if the current page is the confirmation page.
 *
 * @return boolean
 */
function is_kco_confirmation() {
	if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['kco_wc_order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- No nonce possible on this page.
		return true;
	}

	return false;
}

/**
 * Prints error message as notices.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function kco_print_error_message( $wp_error ) {
	if ( is_ajax() ) {
		wc_add_notice( $wp_error->get_error_message(), 'error' );
	} else {
		wc_print_notice( $wp_error->get_error_message(), 'error' );
	}
}

/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function kco_unset_sessions() {
	WC()->session->__unset( 'kco_valid_checkout' );
	WC()->session->__unset( 'kco_wc_prefill_consent' );
	WC()->session->__unset( 'kco_wc_order_id' );
}

/**
 * Confirms and finishes the Klarna Order for processing.
 *
 * @param int    $order_id The WooCommerce Order id.
 * @param string $klarna_order_id The Klarna Order id.
 * @return void
 */
function kco_confirm_klarna_order( $order_id = null, $klarna_order_id = null ) {
	if ( $order_id ) {
		$order = wc_get_order( $order_id );
		// If the order is already completed, return.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		// Get the Klarna OM order.
		$klarna_order = KCO_WC()->api->get_klarna_om_order( $klarna_order_id );

		if ( ! is_wp_error( $klarna_order ) ) {
			kco_maybe_save_surcharge( $order_id, $klarna_order );
			kco_maybe_save_org_nr( $order_id, $klarna_order );
			kco_maybe_save_reference( $order_id, $klarna_order );

			// Let other plugins hook into this sequence.
			do_action( 'kco_wc_confirm_klarna_order', $order_id, $klarna_order );

			// Acknowledge order in Klarna.
			KCO_WC()->api->acknowledge_klarna_order( $klarna_order_id );
			// Set the merchant references for the order.
			KCO_WC()->api->set_merchant_reference( $klarna_order_id, $order_id );
			// Empty cart to be safe.
			WC()->cart->empty_cart();
			// Check fraud status.
			if ( 'ACCEPTED' === $klarna_order['fraud_status'] ) {
				// Payment complete and set transaction id.
				// translators: Klarna order ID.
				$note = sprintf( __( 'Payment via Klarna Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
				$order->add_order_note( $note );
				$order->payment_complete( $klarna_order_id );
				KCO_Logger::log( $klarna_order_id . ': Fraud status accepted for order ' . $order->get_order_number() . '. payment_complete triggered.' );
				do_action( 'kco_wc_payment_complete', $order_id, $klarna_order );
			} elseif ( 'PENDING' === $klarna_order['fraud_status'] ) {
				// Set status to on-hold.
				// translators: Klarna order ID.
				$note = sprintf( __( 'Klarna order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
				$order->set_status( 'on-hold', $note );
				$order->save();
				KCO_Logger::log( $klarna_order_id . ': Fraud status pending for order ' . $order->get_order_number() . '. Order set to on-hold.' );
			} elseif ( 'REJECTED' === $klarna_order['fraud_status'] ) {
				// Cancel the order.
				$order->set_status( 'cancelled', __( 'Klarna Checkout order was rejected', 'klarna-checkout-for-woocommerce' ) );
				$order->save();
				KCO_Logger::log( $klarna_order_id . ': Fraud status rejected for order ' . $order->get_order_number() . '. Order cancelled.' );
			}
		} else {
			$order->set_status( 'on-hold', __( 'Waiting for verification from Klarnas push notification', 'klarna-checkout-for-woocommerce' ) );
			$order->save();
			KCO_Logger::log( $klarna_order_id . ': No order found in order management. Waiting for push verification. Order #' . $order->get_order_number() . ' set to on-hold.' );
		}
	}
}

/**
 * Converts a region string to the expected country code format for WooCommerce.
 *
 * @param string $region_string The region string from Klarna.
 * @param string $country_code The country code from Klarna.
 * @return string
 */
function kco_convert_region( $region_string, $country_code ) {
	// Country specific formatting.
	switch ( $country_code ) {
		case 'ie':
			// If ireland, then remove "CO. " from the region string.
			$region_string = str_replace( 'CO. ', '', $region_string );
			break;
		default:
			break;
	}

	$region_string = htmlentities( mb_convert_case( $region_string, MB_CASE_TITLE, 'UTF-8' ), ENT_XHTML, 'UTF-8' );
	$states        = include WC()->plugin_path() . '/i18n/states.php';
	if ( key_exists( strtoupper( $country_code ), $states ) ) {
		// Check if the region is already unicode format.
		if ( key_exists( strtoupper( $region_string ), $states[ strtoupper( $country_code ) ] ) ) {
			return strtoupper( $region_string );
		}

		// Get the code by region name.
		$region_code = array_keys( $states[ strtoupper( $country_code ) ], $region_string, false ); //phpcs:ignore WordPress.PHP.StrictInArray -- We need to pass false here
		if ( ! empty( $region_code ) ) {
			return $region_code[0];
		}
	}
	return $region_string;
}

/**
 * Maybe saves the surcharge to the order so that it can be completed properly.
 *
 * @param int   $order_id The WooCommerce order id.
 * @param array $klarna_order The Klarna order.
 * @return void
 */
function kco_maybe_save_surcharge( $order_id, $klarna_order ) {
	if ( isset( $klarna_order['order_lines'] ) ) {
		$order = wc_get_order( $order_id );
		foreach ( $klarna_order['order_lines'] as $order_line ) {
			if ( 'added-surcharge' === $order_line['reference'] ) {
				$order->update_meta_data( '_kco_added_surcharge', wp_json_encode( $order_line ) );
			}
		}

		$order->save();
	}
}

/**
 * Maybe saves the org number for a B2B purchase to the WooCommerce order.
 *
 * @param int   $order_id The WooCommerce order id.
 * @param array $klarna_order The Klarna order.
 * @return void
 */
function kco_maybe_save_org_nr( $order_id, $klarna_order ) {
	if ( isset( $klarna_order['customer'] ) && isset( $klarna_order['customer']['type'] ) && 'organization' === $klarna_order['customer']['type'] ) {
		$org_nr = isset( $klarna_order['customer']['organization_registration_id'] ) ? $klarna_order['customer']['organization_registration_id'] : null;
		if ( ! empty( $org_nr ) ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_billing_org_nr', $org_nr );
			$order->save();
		}
	}
}

/**
 * Maybe saves the references for a B2B purchase to the WooCommerce order.
 *
 * @param int   $order_id The WooCommerce order id.
 * @param array $klarna_order The Klarna order.
 * @return void
 */
function kco_maybe_save_reference( $order_id, $klarna_order ) {
	if ( isset( $klarna_order['customer'] ) && isset( $klarna_order['customer']['type'] ) && 'organization' === $klarna_order['customer']['type'] ) {
		$billing_reference  = isset( $klarna_order['billing_address']['attention'] ) ? $klarna_order['billing_address']['attention'] : null;
		$shipping_reference = isset( $klarna_order['shipping_address']['attention'] ) ? $klarna_order['shipping_address']['attention'] : null;
		$order              = wc_get_order( $order_id );
		if ( ! empty( $billing_reference ) ) {
			$order->update_meta_data( '_billing_reference', $billing_reference );
		}
		if ( ! empty( $shipping_reference ) ) {
			$order->update_meta_data( '_shipping_reference', $shipping_reference );
		}
		$order->save();
	}
}

/**
 * Undocumented function
 *
 * @param array|bool $data The shipping data from Klarna. False if not set.
 * @param array|bool $klarna_order The Klarna order if we have one already. False if we don't.
 * @return void
 */
function kco_update_wc_shipping( $data, $klarna_order = false ) {
	// Set cart definition.
	$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

	// If we don't have a Klarna order, return void.
	if ( empty( $klarna_order_id ) ) {
		return;
	}

	// Set the data to the session.
	if ( ! $data && $klarna_order ) {
		$data = isset( $klarna_order['selected_shipping_option'] ) ? $klarna_order['selected_shipping_option'] : false;
	} else {
		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
		$data         = isset( $klarna_order['selected_shipping_option'] ) ? $klarna_order['selected_shipping_option'] : false;
	}

	// If the data is empty, return void.
	if ( empty( $data ) ) {
		return;
	}

	$data['currency'] = $klarna_order['purchase_currency'];
	do_action( 'kco_update_shipping_data', $data );

	set_transient( 'kss_data_' . $klarna_order_id, $data, HOUR_IN_SECONDS );
	$chosen_shipping_methods   = array();
	$chosen_shipping_methods[] = wc_clean( $data['id'] );

	KCO_Logger::Log( "Set chosen shipping method for $klarna_order_id " . json_encode( $chosen_shipping_methods ) );

	WC()->session->set( 'chosen_shipping_methods', apply_filters( 'kco_wc_chosen_shipping_method', $chosen_shipping_methods ) );
}

/**
 * Returns the WooCommerce order that has a matching Klarna order id saved as a meta field. If no order is found, returns false, and if many orders are found the newest one is returned.
 * @param string $klarna_order_id
 * @param string|null $date_after
 * @return WC_Order|false
 */
function kco_get_order_by_klarna_id( $klarna_order_id, $date_after = null ) {
	$args = array(
		'meta_key'     => '_wc_klarna_order_id',
		'meta_value'   => $klarna_order_id,
		'meta_compare' => '=',
		'order'        => 'DESC',
		'orderby'      => 'date',
		'limit'        => 1,
	);

	if ( $date_after ) {
		$args['date_after'] = $date_after;
	}

	$orders = wc_get_orders( $args );

	// If the orders array is empty, return false.
	if ( empty( $orders ) ) {
		return false;
	}

	// Get the first order in the array.
	$order = reset( $orders );

	// Validate that the order actuall has the metadata we're looking for, and that it is the same.
	$meta_value = $order->get_meta( '_wc_klarna_order_id', true );

	// If the meta value is not the same as the Klarna order id, return false.
	if ( $meta_value !== $klarna_order_id ) {
		return false;
	}

	return $order;
}
