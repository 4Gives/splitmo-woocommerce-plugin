<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

include_once 'Config.php';

class SplitmoClient {
    private $client;

    public function __construct($pkey, $skey, $splitmo_url){
        $this->client = new Client([
            'base_uri' => $splitmo_url,
            'timeout' => 2.0,
            'auth' => [$pkey, $skey]
        ]);
    }
    /**
     * Creates splitmo transaction and returns false if creation failed, 
     * returns a checkout url if successful
     * 
     * @return bool|string
     */
    public function createTransaction($order, $schedule_type, $repayment_term){
        try{
            $billing_state_code = $order->get_billing_state();
            $billing_country_code = $order->get_billing_country();
            $states = WC()->countries->get_states( $billing_country_code );
            $billing_state_name = isset( $states[ $billing_state_code ] ) ? $states[ $billing_state_code ] : $billing_state_code;

            $payload = array(
                'external_uuid' => $order->get_id(),
                'amount' => $order->get_total(),
                'customer' => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'mobile' => $order->get_billing_phone(),
                ),
                'billing_details' => array(
                    'full_address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'zip_code' => $order->get_billing_postcode(),
                    'region' => $billing_state_name,
                    'country_code' => $order->get_billing_country(),
                ),
                'redirect_urls' => array(
                    'success_url' => $order->get_checkout_order_received_url(),
                    'failure_url' => $order->get_cancel_order_url(),
                    'cancel_url' => $order->get_cancel_order_url(),
                ),
                'source' => 'PL',
                'schedule_type' => $schedule_type,
                'repayment_term' => $repayment_term,
                'currency' => get_woocommerce_currency(),
                'description' => sprintf(__('%s - Order %s', 'woocommerce'), get_bloginfo('name'), $order->get_id()),
            );
            $response = $this->client->post('transactions/', [
                RequestOptions::JSON => $payload
            ]);
            $response_data = $response->getBody();
            $response_data = json_decode($response_data, true);

            $checkout_url = $response_data['checkout_url'];
            if(!$checkout_url){
                return false;
            }
            return $checkout_url;

        }catch (ClientException $e){
            wc_get_logger()->error('[createTransaction]' . wc_print_r($e->getMessage(), true));
            return false;
        }catch (Exception $e){
            wc_get_logger()->error('[createTransaction]' . wc_print_r($e->getMessage(), true));
            return false;
        }
    }
    /**
     * Gets splitmo transaction details
     * 
     * @return array|null
     */
    public function getTransaction($transaction_id){
        try{
            $response = $this->client->get('transactions/'. $transaction_id. '/');
            $response_data = $response->getBody();
            if ($response->getStatusCode() !== 200){
                return null;
            }
            $response_data = json_decode($response_data, true);
            return $response_data;

        }catch (ClientException $e){
            wc_get_logger()->error('[getTransaction]' . wc_print_r($e->getMessage(), true));
            return null;
        }catch (Exception $e){
            wc_get_logger()->error('[getTransaction]' . wc_print_r($e->getMessage(), true));
            return null;
        }
    }

    /**
     * Gets splitmo transaction details
     * 
     * @return Config|null
     */
    public function getConfig(){
        try{
            $response = $this->client->get('merchants/config/');
            $response_data = $response->getBody();
            if ($response->getStatusCode() !== 200){
                return null;
            }
            $response_data = json_decode($response_data, true);
            return new Config($response_data);

        }catch (ClientException $e){
            wc_get_logger()->error('[getConfig]' . wc_print_r($e->getMessage(), true));
            return null;
        }catch (Exception $e){
            wc_get_logger()->error('[getConfig]' . wc_print_r($e->getMessage(), true));
            return null;
        }
    }

}