<?php
/*
Plugin Name: WooCommerce PayTomorrow
Plugin URI: http://www.paytomorrow.com/developer
Description: WooCommerce PayTomorrow plugin
Author: Paytomorrow Corp
Author URI: http://www.paytomorrow.com
Version: 1.0.0

	Copyright: © 2012 Paytomorrow Corp (email : developer@paytomorrow.com)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if ( ! class_exists( 'WC_Paytomorrow' ) ) {

        function paytomorrow_log_me($message) {
            if (WP_DEBUG === true) {
                if (is_array($message) || is_object($message)) {
                    error_log(print_r($message, true));
                } else {
                    error_log($message);
                }
            }
        }
		/**
		 * Localisation
		 **/
		load_plugin_textdomain( 'wc_paytomorrow', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

		class WC_Paytomorrow {
			public function __construct() {
				// called only after woocommerce has finished loading
				// add_action( 'woocommerce_init', array( &$this, 'woocommerce_loaded' ) );

				// called after all plugins have loaded
				add_action( 'plugins_loaded', array( &$this, 'init_gateways' ) );

				// called just before the woocommerce template functions are included
				// add_action( 'init', array( &$this, 'include_template_functions' ), 20 );

                // add our scripts
        		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

				// indicates we are running the admin
				if ( is_admin() ) {
					// ...
				}

				// indicates we are being served over ssl
				if ( is_ssl() ) {
					// ...
				}

				// take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
			}

			/**
			 * Take care of anything that needs woocommerce to be loaded.
			 * For instance, if you need access to the $woocommerce global
			 */
			public function woocommerce_loaded() {
				// ...
			}

			/**
			 * Take care of anything that needs all plugins to be loaded
			 */
			public function plugins_loaded() {
				// ...
			}

			/**
			 * Override any of the template functions from woocommerce/woocommerce-template.php
			 * with our own template functions file
			 */
			public function include_template_functions() {
				// ...
			}

            /**
             * Initialize the gateway. Called very early - in the context of the plugins_loaded action
             *
             * @since 1.0.0
             */
            public function init_gateways() {
                if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                    return;
                }

                require_once( plugin_basename( 'classes/class-wc-gateway-paytomorrow.php' ) );

                add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
            }

            /**
        	 * Add the gateways to WooCommerce
        	 *
        	 * @since 1.0.0
        	 */
        	public function add_gateways( $methods ) {
    			$methods[] = 'WC_Gateway_Paytomorrow';

        		return $methods;
        	}

            /**
             * enqueue_scripts
             *
             * Loads front side scripts when checkout pages
             *
             * @since 1.0.0
             */
            function enqueue_scripts() {
                if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_cart' ) ) {
                    return;
                }

                if ( ! is_checkout() && ! is_cart() ) {
                    return;
                }

                // Make sure our gateways are enabled before we do anything
                if ( ! $this->are_our_gateways_enabled() ) {
                    return;
                }

                // Always enqueue styles for simplicity's sake (because not all styles are related to JavaScript manipulated elements)
                if ( is_checkout() ) {
                    wp_register_style( 'paytomorrow_styles', plugins_url( 'assets/css/checkout.css', __FILE__ ) );
                } else { // cart
                }

                wp_enqueue_style( 'paytomorrow_styles' );

            }

            /**
        	 * Returns true if our gateways are enabled, false otherwise
        	 *
        	 * @since 1.0.0
        	 */
        	public function are_our_gateways_enabled() {

        		// It doesn't matter which gateway we check, since setting changes are cloned between them
        		$gateway_settings = get_option( 'woocommerce_paytomorrow_settings', array() );

        		if ( empty( $gateway_settings ) ) {
        			return false;
        		}

        		return ( "yes" === $gateway_settings['enabled'] );

        	}

		}

		// finally instantiate our plugin class and add it to the set of globals
		$GLOBALS['wc_paytomorrow'] = new WC_Paytomorrow();
	}
}