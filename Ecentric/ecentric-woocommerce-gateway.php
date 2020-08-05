<?php

/*
Plugin Name: Ecentric WooCommerce Payment Gateway
Plugin URI: https://www.ecentric.co.za
Description: WooCommerce custom payment gateway integration on Ecentric.
Version: 1.8
Text Domain: ecentric
*/


// AJAX request handler
function my_action() {
    $new = new WC_Ecentric_Gateway();
    $trans_id = $_POST['TransactionID'];
    $merch_ref = $_POST['MerchantReference'];
    $result = $_POST['Result'];
    $fail_message = $_POST['FailureMessage'];
    $amount = $_POST['Amount'];
    $checksum = $_POST['Checksum'];

    $new->validate($trans_id, $merch_ref, $result, $fail_message, $amount, $checksum);
}
add_action( 'wp_ajax_my_action', 'my_action' );
add_action( 'wp_ajax_nopriv_my_action', 'my_action' );

add_filter( 'woocommerce_payment_gateways', 'ecentric_add_gateway_class' );

function ecentric_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Ecentric_Gateway';
    return $gateways;
}

function add_fake_error($posted) {
    if ($_POST['confirm-order-flag'] == "1") {
        wc_add_notice( __( "Error", 'fake_error' ), 'error');
    }else{
        wc_clear_notices();
    }
}

add_action('woocommerce_after_checkout_validation', 'add_fake_error');


add_filter( 'woocommerce_review_order_after_shipping', 'ecentric_update_shipping' );

function ecentric_update_shipping() {
    ( new WC_Ecentric_Gateway() )->update_cost_total();

}
wp_enqueue_script( 'ajax-script', plugins_url( 'payment-request.js', __FILE__ ), array('jquery') );
// in JavaScript, object properties are accessed as ajax_object.ajax_url
wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));

//wp_enqueue_script( 'ecentric', 'https://sandbox.ecentric.co.za/HPP/API/js', array( 'jquery' ) );

add_action( 'plugins_loaded', 'ecentric_init_gateway_class' );

function ecentric_init_gateway_class() {
    class WC_Ecentric_Gateway extends WC_Payment_Gateway {
        public function update_cost_total() {
            //            $cost = WC()->cart->get_cart_contents_total();
            //            $shipping = WC()->cart->get_shipping_total();
            add_action( 'wp_ajax_validate_checksum', 'validate_checksum' );
            $cost_total = WC_Payment_Gateway::get_order_total();

            //            $last_order_id = ( int )$this->get_last_order_id() + 1;
            $last_order_id = time();
            //            $Amount = ( $cost + $shipping ) * 100;
            $Amount = $cost_total * 100;
            $Currency = get_woocommerce_currency();
            $MerchantReference = 'WP'.$last_order_id;
            $checksum = hash( 'sha256', ''.$this->secretkey.'|'.$this->merchantid.'|Payment|'.$Amount.'|'.$Currency.'|'.$MerchantReference.'' );

            $vars = array(
                'MerchantID' 		=> $this->merchantid,
                'TransactionType' 	=> 'Payment',
                'Amount' 			=> $Amount,
                'Currency' 			=> $Currency,
                'MerchantReference' => $MerchantReference,
                'Checksum' 			=> $checksum,
                'EndpointURL'       => $this->endpoint
            );
            ?>

            <script>
                var params = <?php echo json_encode( $vars ); ?>
            </script>

            <?php
        }

        public function validate($trans_id, $merch_ref, $result, $fail_message, $amount, $checksum){
            $compare = hash( 'sha256', ''.$this->secretkey.'|'.$trans_id.'|'.$merch_ref.'|'.$result. '|'.$fail_message. '|'.$amount);
            if($checksum === strtoupper($compare))
                echo "Success";
            else
                echo "Error";
            wp_die();
        }

        public function __construct() {
            $this->id = 'ecentric';
            $this->icon = '';
            // URL of the icon that will be displayed on checkout page near your gateway name
            $this->method_title = 'Ecentric Payment Gateway';
            $this->method_description = 'This is the Ecentric Woocommerce payment gateway plugin';

            $this->supports = array( 'products' );

            $this->init_form_fields();

            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->merchantid = $this->get_option( 'merchantid' );
            $this->secretkey = $this->get_option( 'secretkey' );
            $this->endpoint = $this->get_option( 'endpoint' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'load_script_data' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Ecentric Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text'
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay using the Ecentric Payment Gateway.',
                ),
                //                'mode' => array(
                //                    'title'       => 'Mode',
                //                    'label'       => 'Enable Test Mode',
                //                    'type'        => 'select',
                //                    'description' => 'Select the environment the plugin will be run in.',
                //                    'default'     => 'first',
                //                    'options'     => array(
                //                        'first' => 'first',
                //                        'second' => 'second'
                // )
                // ),
                'merchantid' => array(
                    'title'       => 'Merchant ID',
                    'type'        => 'text'
                ),
                'secretkey' => array(
                    'title'       => 'Secret Key',
                    'type'        => 'text'
                ),
                'endpoint' => array(
                    'title'       => 'Endpoint URL',
                    'type'        => 'text'
                )
            );
        }

        public function get_last_order_id() {
            global $wpdb;
            $statuses = implode( "','", array_keys( wc_get_order_statuses() ) );

            $results = $wpdb->get_col( "
                                        SELECT MAX(ID) FROM {$wpdb->prefix}posts
                                        WHERE post_type LIKE 'shop_order'
                                        AND post_status IN ('$statuses')
                                    " );
            return reset( $results );
        }

        public function load_script_data() {
            $this->update_cost_total();
            wp_enqueue_script( 'ecentric', $this->endpoint, array( 'jquery' ) );
            wp_enqueue_script( 'payment-request', plugins_url( 'payment-request.js', __FILE__ ), array( 'jquery' ) );
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $_POST['Result'] == 'Success' ) {
                $order->payment_complete();
                //var_dump($this->get_return_url( $order ));
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            } else {
                wc_add_notice( __('Payment error:', 'woothemes') . $_POST['Result'] , 'error' );
                return false;
            }
        }

        function debug_to_console($data) {
            $output = $data;
            if (is_array($output))
                $output = implode(',', $output);

            echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
        }
    }
}