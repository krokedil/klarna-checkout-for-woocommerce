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
 * Gets a Kustom order. Either creates or updates existing order.
 *
 * @return array|null If an order could not be created or updated, NULL is returned.
 */
function kco_create_or_update_order() {
	// Make sure cart is initialized.
	if ( ! WC()->cart ) {
		return;
	}

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
			set_transient( "kustom_customer_id_{$klarna_order['order_id']}", WC()->session->get_customer_id(), WEEK_IN_SECONDS * 2 );
			return $klarna_order;
		}

		// Ensure the transient is set correctly and updated with the customer id in case the order was updated or the customer was logged in after the order was created.
		if ( ! empty( $klarna_order['order_id'] ) ) {
			set_transient( "kustom_customer_id_{$klarna_order['order_id']}", WC()->session->get_customer_id(), WEEK_IN_SECONDS * 2 );
		}
		return $klarna_order;
	} else {
		// Create new order, since we dont have one.
		$klarna_order = KCO_WC()->api->create_klarna_order();
		if ( ! $klarna_order ) {
			return;
		}
		WC()->session->set( 'kco_wc_order_id', $klarna_order['order_id'] );
		set_transient( "kustom_customer_id_{$klarna_order['order_id']}", WC()->session->get_customer_id(), WEEK_IN_SECONDS * 2 );
		return $klarna_order;
	}
}

/**
 * Creates or updates a Kustom order for the Pay for order feature.
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
 * Echoes Kustom Checkout iframe snippet.
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- We trust the HTML snippet.
		echo kco_extract_script( $klarna_order['html_snippet'] );
	}
}

/**
 * Returns the HTML snippet with the script tag (and its content) removed.
 *
 * The extracted JavaScript is inserted in kco_add_inline_script. This is required since certain themes escapes the HTML snippet,
 * which renders its content (and thus the script) useless.
 *
 * @param string $html_snippet The HTML snippet containing the script tag.
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
	wp_register_script( 'kco-script', '', array(), false, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters -- Ignore no version number since we have no actual file.
	wp_enqueue_script( 'kco-script' );
	wp_add_inline_script( 'kco-script', $js, 'before' );
}

/**
 * Shows order notes field in Kustom Checkout page.
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
 * Shows select another payment method button in Kustom Checkout page.
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

		$credentials           = KCO_WC()->credentials->get_credentials_from_session();
		$sanitized_merchant_id = sanitize_text_field( $credentials['merchant_id'] ?? '' );

		if ( 'de_DE' === get_locale() || 'de_DE_formal' === get_locale() ) {
			$button_text = 'Meine Adressdaten vorausfüllen';
			$link_text   = 'Es gelten die Nutzungsbedingungen zur Datenübertragung';
			$terms_url   = "https://www.kustom.co/legal{$sanitized_merchant_id}/de_de/checkout";
			$popup_text  = 'In unserem Kassenbereich nutzen wir Kustom Checkout. Dazu werden Ihre Daten, wie E-Mail-Adresse, Vor- und Nachname, Geburtsdatum, Adresse und Telefonnummer, soweit erforderlich, automatisch an Kustom AB übertragen, sobald Sie in den Kassenbereich gelangen. Die Nutzungsbedingungen für Kustom Checkout finden Sie hier:';
		} else {
			$button_text = 'Meine Adressdaten vorausfüllen';
			$link_text   = 'Es gelten die Nutzungsbedingungen zur Datenübertragung';
			$terms_url   = "https://www.kustom.co/legal{$sanitized_merchant_id}/en_us/checkout";
			$popup_text  = 'We use Kustom Checkout as our checkout, which offers a simplified purchase experience. When you choose to go to the checkout, your email address, first name, last name, date of birth, address and phone number may be automatically transferred to Kustom AB, enabling the provision of Kustom Checkout. The User Terms that apply for the use of Kustom Checkout are available here:';
		}
		?>
		<p><a class="button" href="<?php echo esc_url( $consent_url ); ?>"><?php echo esc_html( $button_text ); ?></a></p>
		<p><a href="#TB_inline?width=600&height=550&inlineId=consent-text"
			class="thickbox"><?php echo esc_html( $link_text ); ?></a>
		</p>
		<div id="consent-text" style="display:none;">
			<p>
				<?php echo esc_html( $popup_text ); ?>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $terms_url ); ?>"><?php echo esc_html( $terms_url ); ?></a>
			</p>
		</div>
		<?php
	}
}

/**
 * Converts 3-letter ISO returned from Kustom to 2-letter code used in WooCommerce.
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
 * Confirms and finishes the Kustom Order for processing.
 *
 * @param int    $order_id The WooCommerce Order id.
 * @param string $klarna_order_id The Kustom Order id.
 * @return void
 */
function kco_confirm_klarna_order( $order_id = null, $klarna_order_id = null ) {
	if ( $order_id ) {

		$did_lock = false;
		if ( apply_filters( 'kco_wc_lock_confirmation', false, $klarna_order_id, $order_id ) ) {
			$did_lock = KCO_Confirmation::lock_kco_confirmation( $klarna_order_id, $order_id );
			if ( ! $did_lock ) {
				KCO_Logger::log( "Simultaneous confirmation attempt for Klarna order ID $klarna_order_id and WooCommerce order ID $order_id. Stopping process." );
				return;
			}
		}

		try {
			$order = wc_get_order( $order_id );
			// If the order is already completed, return.
			if ( empty( $order ) || ! empty( $order->get_date_paid() ) ) {
				return;
			}

			// Get the Kustom OM order.
			$klarna_order = KCO_WC()->api->get_klarna_om_order( $klarna_order_id );

			if ( ! is_wp_error( $klarna_order ) ) {
				/*
				 * Update the addresses before anything that can change the order status, both so the status emails are
				 * rendered from this order instance with the corrected address, and so the address is written even when
				 * the validation below puts the order on hold.
				 */
				kco_maybe_update_order_addresses( $order, $klarna_order );

				if ( ! kco_validate_order_total( $klarna_order, $order ) || ! kco_validate_order_content( $klarna_order, $order ) ) {
					return;
				}

				kco_maybe_save_surcharge( $order_id, $klarna_order );
				kco_maybe_save_org_nr( $order_id, $klarna_order );
				kco_maybe_save_reference( $order_id, $klarna_order );

				// Let other plugins hook into this sequence.
				do_action( 'kco_wc_confirm_klarna_order', $order_id, $klarna_order );

				// Acknowledge order in Kustom.
				KCO_WC()->api->acknowledge_klarna_order( $klarna_order_id );
				// Set the merchant references for the order.
				KCO_WC()->api->set_merchant_reference( $klarna_order_id, $order_id );
				// Empty cart to be safe.
				WC()->cart->empty_cart();
				// Check fraud status.
				if ( 'ACCEPTED' === $klarna_order['fraud_status'] ) {
					// Payment complete and set transaction id.
					// translators: Kustom order ID.
					$note = sprintf( __( 'Payment via Kustom Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->add_order_note( $note );
					$order->payment_complete( $klarna_order_id );
					KCO_Logger::log( $klarna_order_id . ': Fraud status accepted for order ' . $order->get_order_number() . '. payment_complete triggered.' );
					do_action( 'kco_wc_payment_complete', $order_id, $klarna_order );
				} elseif ( 'PENDING' === $klarna_order['fraud_status'] ) {
					// Set status to on-hold.
					// translators: Kustom order ID.
					$note = sprintf( __( 'Kustom order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order['order_id'] ) );
					$order->set_status( 'on-hold', $note );
					$order->save();
					KCO_Logger::log( $klarna_order_id . ': Fraud status pending for order ' . $order->get_order_number() . '. Order set to on-hold.' );
				} elseif ( 'REJECTED' === $klarna_order['fraud_status'] ) {
					// Cancel the order.
					$order->set_status( 'cancelled', __( 'Kustom Checkout order was rejected', 'klarna-checkout-for-woocommerce' ) );
					$order->save();
					KCO_Logger::log( $klarna_order_id . ': Fraud status rejected for order ' . $order->get_order_number() . '. Order cancelled.' );
				}
			} else {
				$order->set_status( 'on-hold', __( 'Waiting for verification from Kustom\'s push notification', 'klarna-checkout-for-woocommerce' ) );
				$order->save();
				KCO_Logger::log( $klarna_order_id . ': No order found in order management. Waiting for push verification. Order #' . $order->get_order_number() . ' set to on-hold.' );
			}
		} finally {
			if ( $did_lock ) {
				KCO_Confirmation::unlock_kco_confirmation( $klarna_order_id, $order_id );
			}
		}
	}
}

/**
 * Validate the Kustom Checkout order total against the WooCommerce order.
 *
 * @param array    $klarna_order The Kustom order.
 * @param WC_Order $order The WooCommerce order.
 *
 * @return bool
 */
function kco_validate_order_total( $klarna_order, $order ) {
	// Get the Kustom order total.
	$klarna_order_total = $klarna_order['order_amount'];

	// Get the WooCommerce order total.
	$order_total = $order->get_total();

	// Convert the WC Order total to be in minor units with zero decimal places.
	$order_total = wc_format_decimal( $order_total * 100, 0 );

	// Get the difference between the two.
	$diff = abs( $klarna_order_total - $order_total );

	// If the difference is greater than 1, then log the error and return false.
	if ( $diff > 1 ) {
		KCO_Logger::log( 'Order total mismatch. Kustom Order total: ' . $klarna_order_total . ' WC Order total: ' . $order_total . ' Kustom order ID: ' . $klarna_order['order_id'] . ' WC Order ID: ' . $order->get_id() );

		$klarna_order_total = wc_format_decimal( $klarna_order_total / 100, 2 );
		$order_total        = wc_format_decimal( $order_total / 100, 2 );

		// translators: 1: Kustom order total, 2: WooCommerce order total.
		$order->set_status(
			'on-hold',
			sprintf(
				// translators: 1: Kustom order total, 2: WooCommerce order total.
				__( 'Kustom order total (%1$s) does not match WooCommerce order total (%2$s). Please verify the order with Kustom before processing.', 'klarna-checkout-for-woocommerce' ),
				$klarna_order_total,
				$order_total
			)
		);
		$order->save();
		return false;
	}

	return true;
}

/**
 * Validate that the Woo order matches the corresponding Kustom order.
 *
 * @param array    $klarna_order The Kustom order.
 * @param WC_Order $order The Woo order.
 *
 * @return bool
 */
function kco_validate_order_content( $klarna_order, $order ) {
	// Skip if WooCommerce Product Bundles plugin is installed.
	if ( kco_is_bundle_plugin_installed() ) {
		return true;
	}

	$order_data = new KCO_Request_Order();
	$prefix     = "Kustom order ID: {$klarna_order['order_id']} | WC Order ID: {$order->get_order_number()}:";

	// An array of notes to display to the merchant.
	$notes = array( __( 'A mismatch between the WooCommerce and Kustom orders was identified. Please verify the order in the Kustom merchant portal before processing.', 'klarna-checkout-for-woocommerce' ) );

	// A match happens when the item reference and quantity matches in Woo and Kustom.
	$mismatch           = false;
	$items              = $order->get_items();
	$klarna_order_items = $klarna_order['order_lines'];

	// Stack items with same reference.
	$klarna_stack = array();
	foreach ( $klarna_order_items as $klarna_order_item ) {
		$type = $klarna_order_item['type'];
		if ( in_array( $type, array( 'discount', 'shipping_fee', 'sales_tax', 'gift_card', 'store_credit', 'surcharge' ), true ) ) {
			continue;
		}

		$reference = $klarna_order_item['reference'];
		if ( isset( $klarna_stack[ $reference ] ) ) {
			$klarna_stack[ $reference ]['quantity'] += $klarna_order_item['quantity'];
		} else {
			$klarna_stack[ $reference ] = array(
				'quantity' => $klarna_order_item['quantity'],
				'name'     => $klarna_order_item['name'],
			);
		}
	}

	$woo_stack = array();
	foreach ( $items as $order_item ) {
		$order_line = $order_data->get_order_line_items( $order_item );
		$reference  = $order_line['reference'];

		if ( isset( $woo_stack[ $reference ] ) ) {
			$woo_stack[ $reference ] += $order_line['quantity'];
		} else {
			$woo_stack[ $reference ] = $order_line['quantity'];
		}
	}

	foreach ( $woo_stack as $reference => $quantity ) {
		if ( $mismatch ) {
			break;
		}

		$match       = false;
		$klarna_item = $klarna_stack[ $reference ] ?? false;
		if ( $klarna_item ) {
			$match = true;
			$name  = $klarna_item['name'];

			if ( strval( $quantity ) !== strval( $klarna_item['quantity'] ) ) {
				// translators: %1$s: Product name, %2$d: Expected quantity, %3$d: Found quantity.
				$notes[] = sprintf( __( 'The product "%1$s" has a quantity mismatch. Expected %2$d found %3$d.', 'klarna-checkout-for-woocommerce' ), $name, $klarna_item['quantity'], $quantity );
				KCO_Logger::log( "$prefix WC order item reference: $reference ($name) has {$quantity} expected {$klarna_item['quantity']}." );
				$mismatch = true;
			}
		}

		// Check if the Woo item was not found in the Kustom order.
		if ( ! $match ) {
			KCO_Logger::log( "$prefix WC order item reference: $reference ($name) was not found in the Kustom order." );
			// translators: %s: Product name.
			$notes[]  = sprintf( __( 'The product "%s" was not found in the Kustom order.', 'klarna-checkout-for-woocommerce' ), $name );
			$mismatch = true;
		}
	}

	if ( $mismatch ) {
		KCO_Logger::log( "$prefix The Kustom and Woo orders do not match." );

		$order->set_status(
			'on-hold',
			implode( ' ', $notes )
		);
		$order->save();
		return false;
	}

	return true;
}

/**
 * Converts a region string to the expected country code format for WooCommerce.
 *
 * @param string $region_string The region string from Kustom.
 * @param string $country_code The country code from Kustom.
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
 * @param array $klarna_order The Kustom order.
 * @return void
 */
function kco_maybe_save_surcharge( $order_id, $klarna_order ) {
	if ( isset( $klarna_order['order_lines'] ) ) {
		$order = wc_get_order( $order_id );
		foreach ( $klarna_order['order_lines'] as $order_line ) {
			if ( 'added-surcharge' === ( $order_line['reference'] ?? '' ) ) {
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
 * @param array $klarna_order The Kustom order.
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
 * @param array $klarna_order The Kustom order.
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
 * Maybe update the WooCommerce order addresses with the addresses from the Kustom order.
 *
 * In the redirect flow the WooCommerce order is created before the customer is sent to Kustom's hosted payment page,
 * where the customer can still change their address. Kustom is the source of truth for the confirmed address, so any
 * change made there is written back to the WooCommerce order.
 *
 * The order totals are deliberately NOT recalculated. The Kustom order amount is already authorized, and recalculating
 * taxes for the new address could change the WooCommerce total so that it no longer matches Kustom. An order note is
 * added instead, so the merchant can verify the shipping cost and tax manually.
 *
 * @param WC_Order $order The WooCommerce order. The order object is used, and not the order id, so that the address is
 *                        set on the same instance that later triggers the order status emails.
 * @param array    $klarna_order The Kustom order from the order management API.
 * @return void
 */
function kco_maybe_update_order_addresses( $order, $klarna_order ) {
	if ( ! $order instanceof WC_Order || ! is_array( $klarna_order ) ) {
		return;
	}

	// Subscription addresses are handled separately, see KCO_Subscription::update_subscription_address().
	if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
		return;
	}

	$checkout_flow = $order->get_meta( '_wc_klarna_checkout_flow', true );

	/**
	 * Filter whether the Kustom order addresses should be written back to the WooCommerce order.
	 *
	 * Only the redirect flow needs this by default. In the embedded and block flows the address is already
	 * synchronized before the WooCommerce order is created. Return true to enable it for every flow.
	 *
	 * @param bool     $update Whether to update the WooCommerce order addresses.
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $klarna_order The Kustom order.
	 */
	if ( ! apply_filters( 'kco_wc_update_order_addresses', 'redirect' === $checkout_flow, $order, $klarna_order ) ) {
		return;
	}

	try {
		$changes = array();

		foreach ( array( 'billing', 'shipping' ) as $address_type ) {
			$address = isset( $klarna_order[ $address_type . '_address' ] ) ? $klarna_order[ $address_type . '_address' ] : array();
			if ( empty( $address ) || ! is_array( $address ) ) {
				continue;
			}

			$changes = array_merge( $changes, kco_update_order_address( $order, $address_type, $address ) );
		}

		// Nothing changed. Do not save, log or add a note, so that repeated confirmations stay free of side effects.
		if ( empty( $changes ) ) {
			return;
		}

		$order->save();
		$order->add_order_note( kco_get_address_change_note( $changes ) );

		$klarna_order_id = isset( $klarna_order['order_id'] ) ? $klarna_order['order_id'] : 'N/A';
		KCO_Logger::log( "Kustom order ID: {$klarna_order_id} | WC Order ID: {$order->get_order_number()}: Order addresses updated from the Kustom order. The order totals were not recalculated. Changes: " . wp_json_encode( $changes ) );

		if ( kco_address_changes_affect_destination( $changes ) ) {
			kco_flag_order_for_address_review( $order, $changes );
		}

		/**
		 * Fires after the WooCommerce order addresses were updated from the Kustom order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @param array    $changes The changed address fields.
		 * @param array    $klarna_order The Kustom order.
		 */
		do_action( 'kco_wc_order_addresses_updated', $order, $changes, $klarna_order );
	} catch ( Exception $e ) {
		// Never let an address problem break the confirmation, the payment has already been made.
		KCO_Logger::log( 'Failed to update the order addresses from the Kustom order: ' . $e->getMessage() );
	}
}

/**
 * Update a single WooCommerce order address from a Kustom address.
 *
 * Only fields that actually differ are written, see kco_address_values_match() for why.
 *
 * @param WC_Order $order The WooCommerce order.
 * @param string   $address_type Either 'billing' or 'shipping'.
 * @param array    $address The Kustom address.
 * @return array A list of the changed fields.
 */
function kco_update_order_address( $order, $address_type, $address ) {
	$changes = array();
	$country = isset( $address['country'] ) ? $address['country'] : '';

	foreach ( kco_get_address_field_map( $address_type ) as $klarna_key => $wc_field ) {
		if ( ! isset( $address[ $klarna_key ] ) ) {
			continue;
		}

		$new_value = sanitize_text_field( $address[ $klarna_key ] );

		switch ( $wc_field ) {
			case 'country':
				$new_value = strtoupper( $new_value );
				break;
			case 'state':
				$new_value = kco_get_wc_state_code( $new_value, $country );
				break;
			case 'email':
				// WC_Order::set_billing_email() throws a WC_Data_Exception for an invalid email address.
				if ( ! empty( $new_value ) && ! is_email( $new_value ) ) {
					KCO_Logger::log( "Skipped updating the {$address_type} email on order {$order->get_order_number()}. '{$new_value}' is not a valid email address." );
					$new_value = '';
				}
				break;
		}

		// An empty value means Kustom did not provide one, or that we could not resolve it. Keep what WooCommerce has.
		if ( '' === $new_value ) {
			continue;
		}

		$getter = "get_{$address_type}_{$wc_field}";
		$setter = "set_{$address_type}_{$wc_field}";
		if ( ! is_callable( array( $order, $getter ) ) || ! is_callable( array( $order, $setter ) ) ) {
			continue;
		}

		$old_value = $order->{$getter}();
		if ( kco_address_values_match( $old_value, $new_value, $wc_field ) ) {
			continue;
		}

		$order->{$setter}( $new_value );

		$changes[] = array(
			'address' => $address_type,
			'field'   => $wc_field,
			'from'    => $old_value,
			'to'      => $new_value,
		);
	}

	$changes = array_merge( $changes, kco_maybe_clear_orphaned_state( $order, $address_type, $changes ) );

	if ( 'shipping' === $address_type ) {
		$changes = array_merge( $changes, kco_update_shipping_contact_meta( $order, $address ) );
	}

	return $changes;
}

/**
 * Clear a state that is left over from the previous country after a country change.
 *
 * Kustom omits the region for countries that do not have one, so changing the country from e.g. US to SE would
 * otherwise leave the old state on the order. Only done when the country changed in this run and Kustom did not supply
 * a region for the new country, so that a state we just wrote is never discarded.
 *
 * @param WC_Order $order The WooCommerce order.
 * @param string   $address_type Either 'billing' or 'shipping'.
 * @param array    $changes The changes made to this address so far.
 * @return array A list of the changed fields.
 */
function kco_maybe_clear_orphaned_state( $order, $address_type, $changes ) {
	$country_changed = false;
	$state_changed   = false;
	foreach ( $changes as $change ) {
		if ( 'country' === $change['field'] ) {
			$country_changed = true;
		} elseif ( 'state' === $change['field'] ) {
			$state_changed = true;
		}
	}

	// Kustom supplied a region for the new country, so there is nothing stale left behind.
	if ( ! $country_changed || $state_changed ) {
		return array();
	}

	$state = $order->{"get_{$address_type}_state"}();
	if ( empty( $state ) ) {
		return array();
	}

	$order->{"set_{$address_type}_state"}( '' );

	return array(
		array(
			'address' => $address_type,
			'field'   => 'state',
			'from'    => $state,
			'to'      => '',
		),
	);
}

/**
 * Get the map of Kustom address keys to WooCommerce address fields.
 *
 * The shipping phone and email are excluded, since they need special handling, see kco_update_shipping_contact_meta().
 * The 'attention' field is excluded as well, since it is already stored by kco_maybe_save_reference().
 *
 * @param string $address_type Either 'billing' or 'shipping'.
 * @return array
 */
function kco_get_address_field_map( $address_type ) {
	$map = array(
		'given_name'        => 'first_name',
		'family_name'       => 'last_name',
		'organization_name' => 'company',
		'street_address'    => 'address_1',
		'street_address2'   => 'address_2',
		'city'              => 'city',
		'region'            => 'state',
		'postal_code'       => 'postcode',
		'country'           => 'country',
	);

	if ( 'billing' === $address_type ) {
		$map['email'] = 'email';
		$map['phone'] = 'phone';
	}

	/**
	 * Filter the map of Kustom address keys to WooCommerce address fields.
	 *
	 * @param array  $map The field map, keyed by the Kustom address key.
	 * @param string $address_type Either 'billing' or 'shipping'.
	 */
	return apply_filters( 'kco_wc_order_address_field_map', $map, $address_type );
}

/**
 * Update the shipping phone and email from a Kustom shipping address.
 *
 * WooCommerce has no shipping email field, and WC_Order::set_shipping_phone was only added in WooCommerce 5.6.0, so
 * both need to fall back to order meta. They are written at order creation from the Kustom order as it looked before
 * the customer reached the hosted payment page, and shipping plugins read them, so they must be refreshed too.
 *
 * @param WC_Order $order The WooCommerce order.
 * @param array    $address The Kustom shipping address.
 * @return array A list of the changed fields.
 */
function kco_update_shipping_contact_meta( $order, $address ) {
	$changes = array();

	$phone = isset( $address['phone'] ) ? sanitize_text_field( $address['phone'] ) : '';
	if ( ! empty( $phone ) ) {
		$has_phone_field = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '5.6.0', '>=' );
		$old_phone       = $has_phone_field ? $order->get_shipping_phone() : $order->get_meta( '_shipping_phone', true );

		if ( ! kco_address_values_match( $old_phone, $phone, 'phone' ) ) {
			if ( $has_phone_field ) {
				$order->set_shipping_phone( $phone );
			} else {
				$order->update_meta_data( '_shipping_phone', $phone );
			}

			$changes[] = array(
				'address' => 'shipping',
				'field'   => 'phone',
				'from'    => $old_phone,
				'to'      => $phone,
			);
		}
	}

	$email = isset( $address['email'] ) ? sanitize_text_field( $address['email'] ) : '';
	if ( ! empty( $email ) && is_email( $email ) ) {
		$old_email = $order->get_meta( '_shipping_email', true );

		if ( ! kco_address_values_match( $old_email, $email, 'email' ) ) {
			$order->update_meta_data( '_shipping_email', $email );

			$changes[] = array(
				'address' => 'shipping',
				'field'   => 'email',
				'from'    => $old_email,
				'to'      => $email,
			);
		}
	}

	return $changes;
}

/**
 * Get a WooCommerce state value for a Kustom region.
 *
 * Kustom returns the region name ("California") where WooCommerce expects the code ("CA") for countries that have a
 * state list. kco_convert_region() falls back to an HTML encoded region name when it cannot resolve a code, so the
 * result is only accepted if it is an actual state of the country. Countries without a state list accept a free text
 * state, so the region is passed through as is for those. An empty string means the region could not be used, and the
 * caller should leave the state untouched.
 *
 * @param string $region The region from the Kustom address.
 * @param string $country The country from the Kustom address.
 * @return string The WooCommerce state value, or an empty string if it could not be resolved.
 */
function kco_get_wc_state_code( $region, $country ) {
	if ( empty( $region ) || empty( $country ) ) {
		return '';
	}

	$states = WC()->countries->get_states( strtoupper( $country ) );

	// The country has no state list, so WooCommerce accepts the region as free text.
	if ( empty( $states ) ) {
		return $region;
	}

	// kco_convert_region() expects a lowercase country code.
	$state_code = kco_convert_region( $region, strtolower( $country ) );

	return isset( $states[ $state_code ] ) ? $state_code : '';
}

/**
 * Whether two address values are the same, ignoring the formatting differences that Kustom introduces.
 *
 * Kustom normalizes the values it stores: "12241" becomes "122 41", "enskede" becomes "Enskede" and "0700000000"
 * becomes "+46700000000". Comparing the raw values would therefore report a change on every single order, so the
 * comparison ignores those differences and nothing is written when only the formatting differs.
 *
 * @param string $old_value The current WooCommerce value.
 * @param string $new_value The value from Kustom.
 * @param string $field The WooCommerce address field name.
 * @return bool
 */
function kco_address_values_match( $old_value, $new_value, $field ) {
	return kco_normalize_address_value( $old_value, $field ) === kco_normalize_address_value( $new_value, $field );
}

/**
 * Normalize an address value for comparison. The normalized value is never stored.
 *
 * @param string $value The value to normalize.
 * @param string $field The WooCommerce address field name.
 * @return string
 */
function kco_normalize_address_value( $value, $field ) {
	$value = trim( (string) $value );

	switch ( $field ) {
		case 'postcode':
			return wc_normalize_postcode( $value );
		case 'country':
		case 'state':
			return strtoupper( $value );
		case 'email':
			return strtolower( $value );
		case 'phone':
			// Compare the subscriber number only, so that "+46700000000" and "0700000000" are treated as equal and
			// every order's phone number is not silently migrated to another format.
			$digits = preg_replace( '/\D/', '', $value );
			return strlen( $digits ) > 9 ? substr( $digits, -9 ) : $digits;
		default:
			return mb_strtolower( preg_replace( '/\s+/u', ' ', $value ) );
	}
}

/**
 * Whether any of the changed address fields can affect the shipping cost or the tax rate.
 *
 * @param array $changes The changed address fields.
 * @return bool
 */
function kco_address_changes_affect_destination( $changes ) {
	foreach ( $changes as $change ) {
		if ( in_array( $change['field'], array( 'country', 'state', 'postcode', 'city' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Build the order note describing the address changes.
 *
 * @param array $changes The changed address fields.
 * @return string
 */
function kco_get_address_change_note( $changes ) {
	$labels = array(
		'first_name' => __( 'First name', 'klarna-checkout-for-woocommerce' ),
		'last_name'  => __( 'Last name', 'klarna-checkout-for-woocommerce' ),
		'company'    => __( 'Company', 'klarna-checkout-for-woocommerce' ),
		'address_1'  => __( 'Address', 'klarna-checkout-for-woocommerce' ),
		'address_2'  => __( 'Address line 2', 'klarna-checkout-for-woocommerce' ),
		'city'       => __( 'City', 'klarna-checkout-for-woocommerce' ),
		'state'      => __( 'State', 'klarna-checkout-for-woocommerce' ),
		'postcode'   => __( 'Postcode', 'klarna-checkout-for-woocommerce' ),
		'country'    => __( 'Country', 'klarna-checkout-for-woocommerce' ),
		'email'      => __( 'Email', 'klarna-checkout-for-woocommerce' ),
		'phone'      => __( 'Phone', 'klarna-checkout-for-woocommerce' ),
	);

	$grouped = array(
		'billing'  => array(),
		'shipping' => array(),
	);

	foreach ( $changes as $change ) {
		$label = isset( $labels[ $change['field'] ] ) ? $labels[ $change['field'] ] : $change['field'];

		$grouped[ $change['address'] ][] = sprintf(
			// translators: 1: Address field label, 2: The previous value, 3: The new value.
			__( '%1$s: "%2$s" to "%3$s"', 'klarna-checkout-for-woocommerce' ),
			$label,
			$change['from'],
			$change['to']
		);
	}

	$note = __( 'The customer changed their address in Kustom after the order was created. The order has been updated to match the Kustom order.', 'klarna-checkout-for-woocommerce' );

	if ( ! empty( $grouped['billing'] ) ) {
		// translators: %s: A list of the changed billing address fields.
		$note .= ' ' . sprintf( __( 'Billing address, %s.', 'klarna-checkout-for-woocommerce' ), implode( '; ', $grouped['billing'] ) );
	}

	if ( ! empty( $grouped['shipping'] ) ) {
		// translators: %s: A list of the changed shipping address fields.
		$note .= ' ' . sprintf( __( 'Shipping address, %s.', 'klarna-checkout-for-woocommerce' ), implode( '; ', $grouped['shipping'] ) );
	}

	if ( kco_address_changes_affect_destination( $changes ) ) {
		$note .= ' ' . __( 'Shipping and tax were calculated for the previous address and have not been recalculated, since the order total would then no longer match the amount authorized in Kustom. Please verify the shipping cost and the tax before shipping this order.', 'klarna-checkout-for-woocommerce' );
	}

	return $note;
}

/**
 * Put the order on hold for manual review when the delivery destination changed in Kustom.
 *
 * The shipping cost and the tax were calculated for the previous address, and cannot be recalculated without changing
 * the order total away from the amount authorized in Kustom.
 *
 * @param WC_Order $order The WooCommerce order.
 * @param array    $changes The changed address fields.
 * @return void
 */
function kco_flag_order_for_address_review( $order, $changes ) {
	/**
	 * Filter whether the order should be put on hold when the delivery destination changed in Kustom.
	 *
	 * @param bool     $hold Whether to put the order on hold.
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $changes The changed address fields.
	 */
	if ( ! apply_filters( 'kco_wc_hold_order_on_address_change', true, $order, $changes ) ) {
		return;
	}

	$reason = __( 'The delivery destination was changed in Kustom after the order was created. Verify the shipping cost and the tax before processing the order.', 'klarna-checkout-for-woocommerce' );

	// The order was already paid by an earlier confirmation, so payment_complete() will not run again.
	if ( ! empty( $order->get_date_paid() ) ) {
		if ( in_array( $order->get_status(), array( 'pending', 'processing' ), true ) ) {
			$order->update_status( 'on-hold', $reason );
		}

		return;
	}

	/*
	 * payment_complete() runs after this and would overwrite an on-hold status set here, so the status it completes to
	 * is changed instead. That also avoids moving the order to a status that triggers a capture in Kustom.
	 */
	add_filter(
		'woocommerce_payment_complete_order_status',
		function ( $status, $order_id ) use ( $order ) {
			return absint( $order_id ) === $order->get_id() ? 'on-hold' : $status;
		},
		10,
		2
	);

	$order->add_order_note( $reason );
}

/**
 * Undocumented function
 *
 * @param array|bool $data The shipping data from Kustom False if not set.
 * @param array|bool $klarna_order The Kustom order if we have one already. False if we don't.
 * @return void
 */
function kco_update_wc_shipping( $data, $klarna_order = false ) {
	// Set cart definition.
	$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

	// If we don't have a Kustom order, return void.
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
	$chosen_shipping_methods[] = wc_clean( $data['id'] ?? '' );

	KCO_Logger::Log( "Set chosen shipping method for $klarna_order_id " . wp_json_encode( $chosen_shipping_methods ) );

	WC()->session->set( 'chosen_shipping_methods', apply_filters( 'kco_wc_chosen_shipping_method', $chosen_shipping_methods ) );

	// Maybe set the selected pickup point as well from the chosen shipping method if it exists.
	kco_maybe_set_selected_pickup_point( $klarna_order );
}

/**
 * Maybe set the pickup point for the Kustom order if it exists.
 *
 * @param array $klarna_order The Kustom order data.
 * @return void
 */
function kco_maybe_set_selected_pickup_point( $klarna_order ) {
	if ( ! is_array( $klarna_order ) ) {
		return;
	}

	// If we have delivery_details and pickup_location set.
	if ( isset( $klarna_order['selected_shipping_option']['delivery_details']['pickup_location'] ) ) {
		$shipping_method_id = $klarna_order['selected_shipping_option']['id'];
		$pickup_location    = $klarna_order['selected_shipping_option']['delivery_details']['pickup_location'];

		// Get the selected shipping rate from the session.
		$shipping_methods = WC()->cart->get_shipping_methods() ?? array();
		foreach ( $shipping_methods as $method ) {
			if ( $method->get_id() !== $shipping_method_id ) {
				continue;
			}

			// If the method has pickup points set and the selected pickup location exists, set the selected pickup point.
			$selected_pickup_point = KCO_WC()->pickup_points->get_pickup_point_from_rate_by_id( $method, $pickup_location['id'] );
			if ( ! empty( $selected_pickup_point ) ) {

				// If the selected pickup point is already the same as we have saved, return.
				$saved_pickup_point = KCO_WC()->pickup_points->get_selected_pickup_point_from_rate( $method );
				if ( ! empty( $saved_pickup_point ) && $saved_pickup_point->get_id() === $selected_pickup_point->get_id() ) {
					return;
				}

				KCO_WC()->pickup_points->save_selected_pickup_point_to_rate( $method, $selected_pickup_point );
				return;
			}
		}
	}
}

/**
 * Returns the WooCommerce order that has a matching Kustom order id saved as a meta field. If no order is found, returns false, and if many orders are found the newest one is returned.
 *
 * @param string      $klarna_order_id The Kustom order id.
 * @param string|null $date_after Optional. Date after which the order was created. Format 'YYYY-MM-DD'. Default null.
 * @return WC_Order|false
 */
function kco_get_order_by_klarna_id( $klarna_order_id, $date_after = null ) {
	$args = array(
		'meta_key'     => '_wc_klarna_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- We need to query by meta key.
		'meta_value'   => $klarna_order_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- We need to query by meta value.
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

	// If the meta value is not the same as the Kustom order id, return false.
	if ( $meta_value !== $klarna_order_id ) {
		return false;
	}

	return $order;
}

/**
 * Returns true if the WooCommerce Product Bundles plugin is installed.
 *
 * @return bool
 */
function kco_is_bundle_plugin_installed() {
	if ( class_exists( 'WC_Product_Bundle' ) ) {
		return true;
	}

	return false;
}

/**
 * Ensure that a value is numeric. If the value is not numeric, it will attempt to convert it.
 * If the value is an empty value, it will be set to 0.
 * If the value cannot be converted to a numeric value, it will return the default value.
 *
 * @param mixed     $value The value to ensure is numeric.
 * @param float|int $default The default value to return if the value is not numeric and $throw_error is false. Default 0.
 *
 * @return float|int Returns the numeric value of the input, or the default value if the input is not numeric and cannot be converted.
 */
function kco_ensure_numeric( $value, $default = 0 ) { //phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames -- We want to use "default" here
	if ( is_numeric( $value ) ) {
		return floatval( $value );
	}

	// If the value is empty, return 0 instead of default to reflect that the value is not set.
	if ( empty( $value ) ) {
		return 0;
	}

	// Try to convert the value to a numeric value.
	$converted_value = floatval( $value );

	if ( is_numeric( $converted_value ) ) {
		return $converted_value;
	}

	return $default; // Return the default value if the value is still not numeric.
}

/**
 * Checks if the cart needs payment.
 *
 * @return bool
 */
function kco_cart_needs_payment() {
	$needs_payment = isset( WC()->cart ) ? method_exists( WC()->cart, 'needs_payment' ) && WC()->cart->needs_payment() : false;

	// A subscription in the cart always needs payment.
	return $needs_payment || KCO_Subscription::cart_has_subscription();
}
