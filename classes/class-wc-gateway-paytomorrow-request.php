<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates requests to send to PayTomorrow.
 */
class WC_Gateway_Paytomorrow_Request {

	/**
	 * Stores line items to send to PayTomorrow.
	 * @var array
	 */
	protected $line_items = array();

	/**
	 * Pointer to gateway making the request.
	 * @var WC_Gateway_Paytomorrow
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from PayTomorrow.
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Constructor.
	 * @param WC_Gateway_Paytomorrow $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_Paytomorrow' );
	}

	/**
	 * Get the PayTomorrow request URL for an order.
	 * @param  WC_Order $order
	 * @param  bool     $testmode
	 * @return string
	 */
	public function get_request_url( $order, $session, $uuid) {

		$paytomorrow_args = http_build_query( $this->get_paytomorrow_args( $order, $session, $uuid ), '', '&' );

		WC_Gateway_Paytomorrow::log( 'PayTomorrow Request Args for order ' . $order->get_order_number() . ': ' . print_r( $paytomorrow_args, true ) );

        return WC_Gateway_Paytomorrow::$api_url . WC_Gateway_Paytomorrow::$popup_postfix . $paytomorrow_args;
	}

    /**
	 * Get PayTomorrow Args for passing to PP.
	 * @param  WC_Order $order
	 * @return array
	 */
	public function get_paytomorrow_order_body_args( $order ) {
		WC_Gateway_Paytomorrow::log( 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

		return apply_filters( 'woocommerce_paytomorrow_body', array_merge(
			array(
				'cmd'           => '_cart',
				'business'      => $this->gateway->get_option( 'email' ),
                'origin'        => get_bloginfo('name'),
				'no_note'       => 1,
				'currency_code' => get_woocommerce_currency(),
				'charset'       => 'utf-8',
				'rm'            => is_ssl() ? 2 : 1,
				'upload'        => 1,
				// 'return'        => esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->gateway->get_return_url( $order ) ) ),
				// 'cancel_return' => esc_url_raw( $order->get_cancel_order_url_raw() ),
				'invoice'       => $this->gateway->get_option( 'invoice_prefix' ) . $order->get_order_number(),
				'sourceId'      => $this->gateway->get_option( 'order_prefix' ) . $order->get_order_number(),
				'custom'        => json_encode( array( 'order_id' => $order->id, 'order_key' => $order->order_key ) ),
				'notifyUrl'    => $this->notify_url,
				'personalInfo'  => $this->get_billing_args($order),
                'loanAmount'    => $order->get_total()
			),
			$this->get_shipping_args( $order ),
            $this->get_line_item_args( $order ),
            array( 'items'   => $this->get_order_detail()	)
        ), $order );
	}

    /**
	 * Get PayTomorrow Args for passing to PP.
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_paytomorrow_args( $order , $session, $uuid) {

		return apply_filters( 'woocommerce_paytomorrow_args', array(
				'cmd'           => '_cart',
				'sessionId'     => $session,
				'uuid'          => $uuid,
				'currency_code' => get_woocommerce_currency(),
				'return'        => esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->gateway->get_return_url( $order ) ) ),
				'cancel_return' => esc_url_raw( $order->get_cancel_order_url_raw() )
			), $order );
	}


    /**
     * Get shipping args for paytomorrow request.
     * @param  WC_Order $order
     * @return array
     */
    protected function get_billing_args( $order ) {
        $billing_args = array();

            // If we are sending shipping, send shipping address instead of billing
        $billing_args['firstName']       = $order->billing_first_name;
        $billing_args['lastName']        = $order->billing_last_name;
        $billing_args['company']          = $order->billing_company;
        $billing_args['streetName']          = $order->billing_address_1;
        $billing_args['address2']         = $order->billing_address_2;
        $billing_args['city']             = $order->billing_city;
        $billing_args['state']            = $this->get_paytomorrow_state( $order->billing_country, $order->billing_state );
        $billing_args['country']          = $order->billing_country;
        $billing_args['zip']              = $order->billing_postcode;
        $billing_args['email']            = $order->billing_email;


        return $billing_args;
    }

	/**
	 * Get shipping args for paytomorrow request.
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_shipping_args( $order ) {
		$shipping_args = array();

		if ( 'yes' == $this->gateway->get_option( 'send_shipping' ) ) {
			$shipping_args['address_override'] = $this->gateway->get_option( 'address_override' ) === 'yes' ? 1 : 0;
			$shipping_args['no_shipping']      = 0;

			// If we are sending shipping, send shipping address instead of billing
			$shipping_args['first_name']       = $order->shipping_first_name;
			$shipping_args['last_name']        = $order->shipping_last_name;
			$shipping_args['company']          = $order->shipping_company;
			$shipping_args['address']          = $order->shipping_address_1;
			$shipping_args['address2']         = $order->shipping_address_2;
			$shipping_args['city']             = $order->shipping_city;
			$shipping_args['state']            = $this->get_paytomorrow_state( $order->shipping_country, $order->shipping_state );
			$shipping_args['country']          = $order->shipping_country;
			$shipping_args['zip']              = $order->shipping_postcode;
		} else {
			$shipping_args['no_shipping']      = 1;
		}

		return $shipping_args;
	}

	/**
	 * Get line item args for paytomorrow request.
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_line_item_args( $order ) {

		/**
		 * Try passing a line item per product if supported.
		 */
		if ( ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) && $this->prepare_line_items( $order ) ) {

			$line_item_args             = array();
			$line_item_args['taxes'] = $this->number_format( $order->get_total_tax(), $order );

			if ( $order->get_total_discount() > 0 ) {
				$line_item_args['discount'] = $this->number_format( $this->round( $order->get_total_discount(), $order ), $order );
			}

			// Add shipping costs. Paytomorrow ignores anything over 5 digits (999.99 is the max).
			// We also check that shipping is not the **only** cost as PayTomorrow won't allow payment
			// if the items have no cost.
			if ( $order->get_total_shipping() > 0 && $order->get_total_shipping() < 999.99 && $this->number_format( $order->get_total_shipping() + $order->get_shipping_tax(), $order ) !== $this->number_format( $order->get_total(), $order ) ) {
				$line_item_args['shipping'] = $this->number_format( $order->get_total_shipping(), $order );
			} elseif ( $order->get_total_shipping() > 0 ) {
				$this->add_line_item( sprintf( __( 'Shipping via %s', 'wc_paytomorrow' ), $order->get_shipping_method() ), 1, $this->number_format( $order->get_total_shipping(), $order ) );
			}
            // log_me( $this->get_line_items() );
			// $line_item_args = array_merge( $line_item_args, $this->get_line_items() );

		/**
		 * Send order as a single item.
		 *
		 * For shipping, we longer use shipping_1 because paytomorrow ignores it if *any* shipping rules are within paytomorrow, and paytomorrow ignores anything over 5 digits (999.99 is the max).
		 */
		} else {

			$this->delete_line_items();

			$line_item_args = array();
			$all_items_name = $this->get_order_item_names( $order );
			$this->add_line_item( $all_items_name ? $all_items_name : __( 'Order', 'wc_paytomorrow' ), 1, $this->number_format( $order->get_total() - $this->round( $order->get_total_shipping() + $order->get_shipping_tax(), $order ), $order ), $order->get_order_number() );

			// Add shipping costs. Paytomorrow ignores anything over 5 digits (999.99 is the max).
			// We also check that shipping is not the **only** cost as PayTomorrow won't allow payment
			// if the items have no cost.
			if ( $order->get_total_shipping() > 0 && $order->get_total_shipping() < 999.99 && $this->number_format( $order->get_total_shipping() + $order->get_shipping_tax(), $order ) !== $this->number_format( $order->get_total(), $order ) ) {
				$line_item_args['shipping'] = $this->number_format( $order->get_total_shipping() + $order->get_shipping_tax(), $order );
			} elseif ( $order->get_total_shipping() > 0 ) {
				$this->add_line_item( sprintf( __( 'Shipping via %s', 'wc_paytomorrow' ), $order->get_shipping_method() ), 1, $this->number_format( $order->get_total_shipping() + $order->get_shipping_tax(), $order ) );
			}

			// $line_item_args = array_merge( $line_item_args, $this->get_line_items() );
		}

		return $line_item_args;
	}

	/**
	 * Get order item names as a string.
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function get_order_item_names( $order ) {
		$item_names = array();

		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item['name'] . ' x ' . $item['qty'];
		}

		return implode( ', ', $item_names );
	}

	/**
	 * Get order item names as a string.
	 * @param  WC_Order $order
	 * @param  array $item
	 * @return string
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item['name'];
		$item_meta = new WC_Order_Item_Meta( $item );

		if ( $meta = $item_meta->display( true, true ) ) {
			$item_name .= ' ( ' . $meta . ' )';
		}

		return $item_name;
	}

	/**
	 * Return all line items.
	 */
	protected function get_line_items() {
		return $this->line_items;
    }

	protected function get_order_detail() {

        $result = array();
        $line_items = $this->get_line_items();
        paytomorrow_log_me( $line_items );
        $i = 1;
        paytomorrow_log_me(array( 'item_name_1' => $line_items[ 'item_name_1' ] ) );

        while ( isset( $line_items[ 'item_name_' . $i ] ) ) {
            $line_item = array('description' => $line_items[ 'item_name_' . $i ],
                               'quantity' => $line_items[ 'quantity_' . $i ],
                               'price' => $line_items[ 'amount_' . $i ]);
            paytomorrow_log_me(array( 'line_item' . $i => $line_item) );
            array_push( $result, $line_item );
            $i++;
        }

		return $result;
	}

	/**
	 * Remove all line items.
	 */
	protected function delete_line_items() {
		$this->line_items = array();
	}

	/**
	 * Get line items to send to paytomorrow.
	 * @param  WC_Order $order
	 * @return bool
	 */
	protected function prepare_line_items( $order ) {
		$this->delete_line_items();
		$calculated_total = 0;

		// Products
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$item_line_total  = $this->number_format( $item['line_total'], $order );
				$line_item        = $this->add_line_item( $item['name'], 1, $item_line_total );
				$calculated_total += $item_line_total;
			} else {
				$product          = $order->get_product_from_item( $item );
				$sku              = $product ? $product->get_sku() : '';
				$item_line_total  = $this->number_format( $order->get_item_subtotal( $item, false ), $order );
				$line_item        = $this->add_line_item( $this->get_order_item_name( $order, $item ), $item['qty'], $item_line_total, $sku );
				$calculated_total += $item_line_total * $item['qty'];
			}

			if ( ! $line_item ) {
				return false;
			}
		}

		// Check for mismatched totals.
		if ( $this->number_format( $calculated_total + $order->get_total_tax() + $this->round( $order->get_total_shipping(), $order ) - $this->round( $order->get_total_discount(), $order ), $order ) != $this->number_format( $order->get_total(), $order ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add PayTomorrow Line Item.
	 * @param  string  $item_name
	 * @param  int     $quantity
	 * @param  int     $amount
	 * @param  string  $item_number
	 * @return bool successfully added or not
	 */
	protected function add_line_item( $item_name, $quantity = 1, $amount = 0, $item_number = '' ) {
		$index = ( sizeof( $this->line_items ) / 4 ) + 1;

		if ( $amount < 0 || $index > 9 ) {
			return false;
		}

		$this->line_items[ 'item_name_' . $index ]   = html_entity_decode( wc_trim_string( $item_name ? $item_name : __( 'Item', 'wc_paytomorrow' ), 127 ), ENT_NOQUOTES, 'UTF-8' );
		$this->line_items[ 'quantity_' . $index ]    = $quantity;
		$this->line_items[ 'amount_' . $index ]      = $amount;
		$this->line_items[ 'item_number_' . $index ] = $item_number;

		return true;
	}

	/**
	 * Get the state to send to paytomorrow.
	 * @param  string $cc
	 * @param  string $state
	 * @return string
	 */
	protected function get_paytomorrow_state( $cc, $state ) {
		if ( 'US' === $cc ) {
			return $state;
		}

		$states = WC()->countries->get_states( $cc );

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ];
		}

		return $state;
	}

	/**
	 * Check if currency has decimals.
	 * @param  string $currency
	 * @return bool
	 */
	protected function currency_has_decimals( $currency ) {
		if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Round prices.
	 * @param  double $price
	 * @param  WC_Order $order
	 * @return double
	 */
	protected function round( $price, $order ) {
		$precision = 2;

		if ( ! $this->currency_has_decimals( $order->get_order_currency() ) ) {
			$precision = 0;
		}

		return round( $price, $precision );
	}

	/**
	 * Format prices.
	 * @param  float|int $price
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function number_format( $price, $order ) {
		$decimals = 2;

		if ( ! $this->currency_has_decimals( $order->get_order_currency() ) ) {
			$decimals = 0;
		}

		return number_format( $price, $decimals, '.', '' );
	}
}
