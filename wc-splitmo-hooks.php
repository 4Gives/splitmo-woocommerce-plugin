<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function splitmo_capture_payment(){
    $settings = get_option('woocommerce_splitmo_payment_gateway_settings');

    $test_mode = $settings['splitmo_enable_test_mode'];
    $pkey = $settings[$test_mode == 'yes' ? 'splitmo_api_test_public_key' : 'splitmo_api_public_key'];
    $skey = $settings[$test_mode == 'yes' ? 'splitmo_api_test_secret_key' : 'splitmo_api_secret_key'];

    $environment = $settings['splitmo_test_environment'];
    $splitmo_url = $test_mode == 'yes' ? get_api_urls()[$environment] : get_api_urls()['production'];
    $client = new SplitmoClient($pkey, $skey, $splitmo_url);
    $genericErrorMessage = 'Something went wrong with the payment. Please try another payment method. If issue persist, contact support.';
    try {
        $order_id = $_GET['order_id'];
        $transaction_id = $_GET['transaction_id'];
        
        $order = wc_get_order($order_id);
        if(!($order->get_payment_method() == 'splitmo_payment_gateway')){
            throw new Exception(__($genericErrorMessage, 'woocommerce'));
        }
        $transaction = $client->getTransaction($transaction_id);
        $external_uuid = $transaction['external_uuid'];
        $status = $transaction['status'];
        wc_get_logger()->debug($external_uuid . $status . $order_id);
        if(!$external_uuid || !$status){
            status_header(400);
            die();
        }

        if($external_uuid != $order_id){
            status_header(400);
            die();
        }

        if($status == 'SUCCESSFUL' || $order->has_status('pending')){
            $existing_transaction = $order->get_meta(SPLITMO_TRANSACTION_META);
            if (isset($existing_transaction) && $existing_transaction !== '') {
                $order->add_meta_data(SPLITMO_TRANSACTION_META . '_old', $existing_transaction);
            }

            $order->payment_complete();
            $order->update_meta_data(SPLITMO_TRANSACTION_META, $transaction_id);
            $order->save_meta_data();
            wc_reduce_stock_levels($order_id);
            wp_redirect($transaction['redirect_urls']['success_url']);
        }
        die();

        
    } catch (Exception $e) {
        wc_get_logger()->error(wc_print_r($e, true));
        status_header(400);
        die();
    }

}
add_action('woocommerce_api_splitmo_capture_payment', 'splitmo_capture_payment');