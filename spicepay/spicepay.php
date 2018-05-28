<?php
/*
Plugin Name: SpicePay
Plugin URI:  https://www.spicepay.com/
Description: SpicePay Plugin for WooCommerce
Version: 1.0.1
Author: SpicePay
Author URI: https://www.spicepay.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_spicepay', 0);
function woocommerce_spicepay(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_SPICEPAY'))
        return;
class WC_SPICEPAY extends WC_Payment_Gateway{
    public function __construct(){

    $plugin_dir = plugin_dir_url(__FILE__);

    global $woocommerce;

    $this->id = 'spicepay';
    $this->icon = apply_filters('woocommerce_spicepay_icon', ''.$plugin_dir.'spicepay.png');
    $this->has_fields = false;

    // Load the settings
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->public_key = $this->get_option('public_key');
    $this->secret_key = $this->get_option('secret_key');
    $this->select_currency = $this->get_option('select_currency');
    $this->title = 'Cryptocurrency payments';
    $this->description = 'Pay with Bitcoin or Litecoin or Bitcoin Cash or other cryptocurrencies via SpicePay';

    // Actions
    add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

    // Save options
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

    // Payment listener/API hook
    add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));


}

public function admin_options() {
?>
<h3><?php _e('spicepay', 'woocommerce'); ?></h3>
<p><?php _e('Setup Spicepay plugin.', 'woocommerce'); ?></p>

    <table class="form-table">

        <?php
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        ?>
    </table><!--/.form-table-->

    <?php
} // End admin_options()

function init_form_fields(){
    $this->form_fields = array(
        'enabled' => array(
            'title' => __('On/Off', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('On', 'woocommerce'),
            'default' => 'yes'
        ),
        'public_key' => array(
            'title' => __('Spicepay Site ID', 'woocommerce'),
            'type' => 'text',
            'description' => __('Copy Spicepay Site ID from spicepay.com/tools.php', 'woocommerce'),
            'default' => ''
        ),
        'secret_key' => array(
            'title' => __('Spicepay Callback Secret', 'woocommerce'),
            'type' => 'text',
            'description' => __('Copy SECRET KEY from spicepay.com/tools.php', 'woocommerce'),
            'default' => ''
        ),
        'select_currency' => array(
            'title' => __('Currency', 'woocommerce'),
            'type' => 'select',
            'description' => __('Select currency', 'woocommerce'),
            'default' => '',
            'options' => array(
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP'
            ) 
        )
        
    );
}

/**
 * Generate form
 **/
public function generate_form($order_id){
    $order = wc_get_order( $order_id );
    // print_r($order);
    // exit();
    $firstname = $order->get_billing_first_name();
	$lastname = $order->get_billing_last_name();
// exit();
    $sum = number_format($order->get_total(), 2, '.', '');
    $account = $order_id;

    $code = '<form id="spicepaypaymentmethod" name="spicepaypaymentmethod" action="https://www.spicepay.com/p.php" method="POST">'
    . '<input type="hidden" name="amount" value="' . $sum . '" />'
    . '<input type="hidden" name="currency" value="' . $this->select_currency . '" />'
    . '<input type="hidden" name="orderId" value="' . $order_id . '"/>'
    . '<input type="hidden" name="siteId" value="' . $this->public_key . '"/>'
    . '<input type="hidden" name="clientName" value="' . $firstname . ' ' . $lastname . '"/>'
    . '<input type="hidden" name="language" value="en"/>'
    . '<input type="submit" value="'.__('Pay', 'woocommerce').'"/>'
    . '</form>'
    . '<script>document.getElementById("spicepaypaymentmethod").submit();</script>'
	;

    return $code;
}

/**
 * Process the payment and return the result
 **/
function process_payment($order_id){
    $order =  wc_get_order($order_id);

    return array(
        'result' => 'success',
        'redirect'	=> add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))))
    );
}

function receipt_page($order){
    echo '<p>'.__('Thank you for your order, clease click the button below to pay.', 'woocommerce').'</p>';
    echo $this->generate_form($order);
}

function callback(){
   write_log('IPN Call'.json_encode($_POST));

    if (isset($_POST['paymentId']) && isset($_POST['orderId']) && isset($_POST['hash']) 
&& isset($_POST['paymentCryptoAmount']) && isset($_POST['paymentAmountUSD']) 
&& isset($_POST['receivedCryptoAmount']) && isset($_POST['receivedAmountUSD'])) {
        
		$paymentId = addslashes(filter_input(INPUT_POST, 'paymentId', FILTER_SANITIZE_STRING));
        $orderId = addslashes(filter_input(INPUT_POST, 'orderId', FILTER_SANITIZE_STRING));
        $hash = addslashes(filter_input(INPUT_POST, 'hash', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));    
        $clientId = addslashes(filter_input(INPUT_POST, 'clientId', FILTER_SANITIZE_STRING));
        $paymentAmountBTC = addslashes(filter_input(INPUT_POST, 'paymentAmountBTC', FILTER_SANITIZE_NUMBER_INT));
        $paymentAmountUSD = addslashes(filter_input(INPUT_POST, 'paymentAmountUSD', FILTER_SANITIZE_STRING));
        $receivedAmountBTC = addslashes(filter_input(INPUT_POST, 'receivedAmountBTC', FILTER_SANITIZE_NUMBER_INT));
        $receivedAmountUSD = addslashes(filter_input(INPUT_POST, 'receivedAmountUSD', FILTER_SANITIZE_STRING));
        $status = addslashes(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));
        
        if(isset($_POST['paymentCryptoAmount']) && isset($_POST['receivedCryptoAmount'])) {
            $paymentCryptoAmount = addslashes(filter_input(INPUT_POST, 'paymentCryptoAmount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            $receivedCryptoAmount = addslashes(filter_input(INPUT_POST, 'receivedCryptoAmount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }
        else {
            $paymentCryptoAmount = addslashes(filter_input(INPUT_POST, 'paymentAmountBTC', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            $receivedCryptoAmount = addslashes(filter_input(INPUT_POST, 'receivedAmountBTC', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }
		
		$secretCode = $this->secret_key;
		$order = wc_get_order( $orderId );
		$hashString = $secretCode . $paymentId . $orderId . $clientId . $paymentCryptoAmount . $paymentAmountUSD . $receivedCryptoAmount . $receivedAmountUSD . $status;
        write_log('Order ID '.$order->get_id());
        if (!empty($order->get_id())) {
            write_log('Hash compare '.md5($hashString).' - '.$hash);
							
			if (0 == strcmp(md5($hashString), $hash)) {
				
			
				$sum = number_format($order->get_total(), 2, '.', ''); 
				  if ((float)$sum != $receivedAmountUSD) {
							echo  'bad amount';
							write_log('bad amount');
				  } else {
						$order->payment_complete();
					  echo 'OK';
					  write_log('OK');
				  
				  }
				
			}
			
        }else{
           echo 'fail'; 
           write_log('fail');
        }
        
        
    } else {
        echo 'fail';
        write_log('fail');
    }


    die();
}



  

}


if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}

/**
 * Add the gateway to WooCommerce
 **/
function add_spicepay_gateway($methods){
    $methods[] = 'WC_SPICEPAY';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_spicepay_gateway');
}