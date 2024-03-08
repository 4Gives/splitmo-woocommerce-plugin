<?php

class Splitmo_Payment_Gateway extends WC_Payment_Gateway
{
    private const API_URLS = array(
        'production' => 'https://app.splitmo.co/api/v1/transactions/',
        'sandbox' => 'https://sandbox.splitmo.co/api/v1/transactions/',
    );

    public function __construct()
    {
        $this->id = 'splitmo_payment_gateway';
        $this->icon =  plugin_dir_url(dirname(__FILE__)) . 'assets/images/splitmo-logo.png';
        $this->form_fields = array();
        $this->has_fields = false;

        $this->method_title = __('Splitmo Checkout for WooCommerce', 'splitmo-payment-plugin');
        $this->method_description = __('Pay securely using Splitmo Checkout.', 'splitmo-payment-plugin');
        $this->supports = array('products');

        $this->init_form_fields();

        $this->title = $this->get_option('splitmo_title');
        $this->description = $this->get_option('splitmo_description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Getter function for API Environment
     * @return string
     */
    private function get_environment()
    {
        return $this->get_option('splitmo_environment');
    }

    /**
     * Getter function for API Public Key
     * @return string
     */
    private function get_api_public_key()
    {
        return $this->get_option('splitmo_api_public_key');
    }

    /**
     * Getter function for API Private Key
     * @return string
     */
    private function get_api_private_key()
    {
        return $this->get_option('splitmo_api_private_key');
    }

    /**
     * Generate the authorization header for Splitmo API
     * @return string Authorization header
     */
    private function generate_authorization_header()
    {
        return 'Basic ' . base64_encode($this->get_api_public_key() . ':' . $this->get_api_private_key());
    }

    /**
     * Property function to fetch appropriate url for the selected environment
     * @return string
     */
    private function get_splitmo_url()
    {
        return isset(self::API_URLS[$this->get_environment()]) ? self::API_URLS[$this->get_environment()] : '';
    }

    /**
     * Initialize Plugin Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'splitmo_enabled' => array(
                'title' => __('Enable/Disable', 'splitmo-payment-plugin'),
                'type' => 'checkbox',
                'label' => __('Enable Splitmo Payment Gateway', 'splitmo-payment-plugin'),
                'default' => 'yes',
            ),
            'splitmo_title' => array(
                'title' => __('Title', 'splitmo-payment-plugin'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'splitmo-payment-plugin'),
                'default' => __('Pay with ease using Splitmo', 'splitmo-payment-plugin'),
                'desc_tip' => true,
            ),
            'splitmo_description' => array(
                'title' => __('Description', 'splitmo-payment-plugin'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'splitmo-payment-plugin'),
                'default' => __('Pay securely using Splitmo Payment Gateway.', 'splitmo-payment-plugin'),
            ),
            'splitmo_api_public_key' => array(
                'title' => __('API Public Key', 'splitmo-payment-plugin'),
                'type' => 'text',
                'description' => __('Enter your API public key.', 'splitmo-payment-plugin'),
                'default' => '',
                'desc_tip' => true,
            ),
            'splitmo_api_secret_key' => array(
                'title' => __('API Secret Key', 'splitmo-payment-plugin'),
                'type' => 'text',
                'description' => __('Enter your API secret key.', 'splitmo-payment-plugin'),
                'default' => '',
                'desc_tip' => true,
            ),
            'splitmo_environment' => array(
                'title' => __('Environment', 'splitmo-payment-plugin'),
                'type' => 'select',
                'description' => __('Select the environment.', 'splitmo-payment-plugin'),
                'default' => 'sandbox',
                'options' => array(
                    'sandbox' => __('Sandbox', 'splitmo-payment-plugin'),
                    'production' => __('Production', 'splitmo-payment-plugin'),
                ),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Override process_payment with splitmo checkout process
     * @return string
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $payload = array(
            'external_uuid' => $order_id,
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
                'region' => $order->get_billing_state(),
                'country_code' => $order->get_billing_country(),
            ),
            'redirect_urls' => array(
                'success_url' => $this->get_return_url($order),
                'failure_url' => $order->get_cancel_order_url(),
                'cancel_url' => $order->get_cancel_order_url(),
            ),
            'currency' => get_woocommerce_currency(),
            'schedule_type' => 'DI',
            'repayment_term' => 1,
            'description' => sprintf(__('Order %s', 'woocommerce'), $order_id),
        );

        $base_url = $this->get_splitmo_url();
        $authorization_header = $this->generate_authorization_header();
        $checkout_link_response = wp_remote_post($base_url, array(
            'body' => json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $authorization_header,
            ),
        ));

        if (is_wp_error($checkout_link_response)) {
            wc_get_logger()->error('[process-payment] ' . $checkout_link_response->get_error_message(), array('source' => 'splitmo-payment-plugin'));
            wc_add_notice(__('An error occurred while processing your payment. Please try again later.', 'splitmo-payment-plugin'), 'error');
            return;
        }

        $response_body = wp_remote_retrieve_body($checkout_link_response);
        $response_data = json_decode($response_body, true);

        wc_get_logger()->info('[process-payment]' . $response_data);
        if (isset($response_data['redirect_urls']) && isset($response_data['redirect_urls']['success_url'])) {
            return array(
                'result' => 'success',
                'redirect' => $response_data['redirect_urls']['success_url'],
            );
        } else {
            wc_get_logger()->error('[process-payment] Redirect urls are not set');
            wc_add_notice(__('An error occurred while processing your payment. Please try again later.', 'splitmo-payment-plugin'), 'error');
            return;
        }
    }

    /**
     * Custom Payment Scripts to validate checkout transactions
     * @return null
     */
    public function payment_scripts()
    {
        if (empty($this->get_api_public_key()) || empty($this->get_api_private_key())) {
            wc_add_notice(__('Invalid API Credentials, Please check your provided keys', 'splitmo-payment-plugin'), 'error');
            return;
        }
    }

    // public function validate_fields() {}

    // public function webhook() {}
}
