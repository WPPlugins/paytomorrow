<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Responses.
 */
abstract class WC_Gateway_Paytomorrow_Response {


	/**
	 * Get the order from the PayTomorrow 'Custom' variable.
	 * @param  string $raw_custom JSON Data passed back by PayTomorrow
	 * @return bool|WC_Order object
	 */
	protected function get_paytomorrow_order( $uuid ) {
		// We have the data in the correct format, so get the order.
//		if ( ( $custom = json_decode( $raw_custom ) ) && is_object( $custom ) ) {
//			$order_id  = $custom->order_id;
//			$order_key = $custom->order_key;
//
//		// Fallback to serialized data if safe. This is @deprecated in 2.3.11
//		} elseif ( preg_match( '/^a:2:{/', $raw_custom ) && ! preg_match( '/[CO]:\+?[0-9]+:"/', $raw_custom ) && ( $custom = maybe_unserialize( $raw_custom ) ) ) {
//			$order_id  = $custom[0];
//			$order_key = $custom[1];
//
//		// Nothing was found.
//		} else {
//			WC_Gateway_Paytomorrow::log( 'Error: Order ID and key were not found in "custom".' );
//			return false;
//		}
//
//		if ( ! $order = wc_get_order( $order_id ) ) {
//			// We have an invalid $order_id, probably because invoice_prefix has changed.
//			$order_id = wc_get_order_id_by_order_key( $order_key );
//			$order    = wc_get_order( $order_id );
//		}

        global $wpdb;

        // Faster than get_posts()
        $order_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'PT-ReferenceID' AND meta_value = %s", $uuid ) );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			WC_Gateway_Paytomorrow::log( 'Error: cannot find order.' );
			return false;
		}

		paytomorrow_log_me(array($order));

		return $order;
	}

	/**
	 * Complete order, add transaction ID and note.
	 * @param  WC_Order $order
	 * @param  string   $txn_id
	 * @param  string   $note
	 */
	protected function payment_complete( $order, $txn_id = '', $note = '' ) {
        paytomorrow_log_me('PAYMENT COMPLETE!!!');
		$order->add_order_note( $note );
		$order->payment_complete( $txn_id );
	}

	/**
	 * Hold order and add note.
	 * @param  WC_Order $order
	 * @param  string   $reason
	 */
	protected function payment_on_hold( $order, $reason = '' ) {
		$order->update_status( 'on-hold', $reason );
		$order->reduce_order_stock();
		WC()->cart->empty_cart();
	}
}
