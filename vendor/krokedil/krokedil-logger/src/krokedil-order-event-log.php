<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! function_exists( 'krokedil_log_events' ) ) {
	define( 'KROKEDIL_LOGGER_VERSION', '1.0.4' );

	add_action( 'admin_enqueue_scripts', 'krokedi_load_admin_scripts' );
	if ( defined( 'KROKEDIL_LOGGER_ON' ) ) {
		add_action( 'add_meta_boxes', 'krokedil_meta_box' );
	}
	add_action( 'woocommerce_new_order', 'krokedil_add_sessions_to_events', 10, 1 );

	function krokedi_load_admin_scripts() {
		wp_register_script(
			'krokedil_event_log',
			plugins_url( 'assets/js/krokedil-event-log.js', __FILE__ ),
			array( 'jquery' ),
			KROKEDIL_LOGGER_VERSION
		);

		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'krokedil_event_log', 'krokedil_event_log_params', $params );

		wp_enqueue_script( 'krokedil_event_log' );

		wp_register_script(
			'render_json',
			plugins_url( 'assets/js/renderjson.js', __FILE__ ),
			array( 'jquery' ),
			KROKEDIL_LOGGER_VERSION
		);

		$params = array();

		wp_localize_script( 'render_json', 'render_json_params', $params );

		wp_enqueue_script( 'render_json' );

		wp_register_style(
			'krokedil_events_style',
			plugin_dir_url( __FILE__ ) . 'assets/css/krokedil-event-log.css',
			array(),
			KROKEDIL_LOGGER_VERSION
		);
		wp_enqueue_style( 'krokedil_events_style' );
	}

	function krokedil_log_events( $order_id, $title, $data = null ) {
		if ( WC()->session ) {
			if ( null === $order_id ) {
				if ( WC()->session->get( '_krokedil_events_session' ) ) {
					$events = WC()->session->get( '_krokedil_events_session' );
				} else {
					$events = array();
				}
				$event    = array(
					'title'     => $title,
					'data'      => $data,
					'timestamp' => current_time( 'Y-m-d H:i:s' ),
				);
				$events[] = $event;
				WC()->session->set( '_krokedil_events_session', $events );
			} else {
				if ( get_post_meta( $order_id, '_krokedil_order_events' ) ) {
					$events = get_post_meta( $order_id, '_krokedil_order_events', true );
				} else {
					$events = array();
				}

				$event = array(
					'title'     => $title,
					'data'      => $data,
					'timestamp' => current_time( 'Y-m-d H:i:s' ),
				);

				$events[] = $event;
				update_post_meta( $order_id, '_krokedil_order_events', $events );
			}
		}
	}

	function krokedil_log_response( $order_id, $response ) {
		if ( WC()->session ) {
			if ( null === $order_id ) {
				$events = WC()->session->get( '_krokedil_events_session' );
				end( $events );
				$event                        = key( $events );
				$events[ $event ]['response'] = $response;
				WC()->session->set( '_krokedil_events_session', $events );
			} else {
				$events = get_post_meta( $order_id, '_krokedil_order_events', true );
				end( $events );
				$event                        = key( $events );
				$events[ $event ]['response'] = $response;
				update_post_meta( $order_id, '_krokedil_order_events', $events );
			}
		}
	}

	function krokedil_add_sessions_to_events( $order_id ) {
		if ( WC()->session ) {
			if ( WC()->session->get( '_krokedil_events_session' ) ) {
				$session_events = WC()->session->get( '_krokedil_events_session' );
				update_post_meta( $order_id, '_krokedil_order_events', $session_events );
				WC()->session->__unset( '_krokedil_events_session' );
			}
		}
	}

	function krokedil_get_events() {
		return get_post_meta( get_the_ID(), '_krokedil_order_events', true );
	}

	function krokedil_meta_box( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( false !== strpos( $order->get_payment_method(), KROKEDIL_LOGGER_GATEWAY ) ) {
				if ( get_post_meta( $order_id, '_krokedil_order_events' ) ) {
					add_meta_box( 'krokedil_order_events', __( 'Events', 'krokedil-for-woocommerce' ), 'krokedil_meta_contents', 'shop_order', 'normal', 'core' );
				}
			}
		}
	}

	function krokedil_set_order_gateway_version( $order_id, $version ) {
		update_post_meta( $order_id, '_krokedil_order_gateway_version', $version );
	}

	function krokedil_get_order_version() {
		$order_id = get_the_ID();

		return get_post_meta( $order_id, '_krokedil_order_gateway_version', true );
	}

	function krokedil_meta_contents() {
		$events = krokedil_get_events();
		$i      = 0;
		foreach ( $events as $event ) {
			$i += 1;
			echo '<div class="krokedil_event">';
			echo '<div class="krokedil_event_header">';
			echo '<h4>' . $event['title'] . '</h4>';
			echo '<h5 class="krokedil_timestamp" data-event-nr="' . $i . '"><a href="#krokedil_event_nr_' . $i . '">Time: ' . $event['timestamp'] . '</a></h5>';
			echo '</div>';
			echo '<div class="krokedil_json krokedil_hidden" id="krokedil_event_nr_' . $i . '">' . json_encode( $event['data'] ) . '</div>';
			echo '</div>';
		}
		echo '<small>Version of plugin used for order: ' . krokedil_get_order_version() . '</small>';
	}
}
