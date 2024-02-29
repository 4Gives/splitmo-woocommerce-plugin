<?php

/**
 * Plugin Name: Splitmo Payment Gateway
 * Plugin URI: https://splitmo.co/
 * Description: Integration with Splitmo Payment API for WooCommerce.
 * Version: 1.0.0
 * Author: Splitmo Developers
 * Author URI: https://splitmo.co/
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_payment_gateways', 'add_splitmo_payment_gateway');
function add_splitmo_payment_gateway($methods)
{
    if (class_exists('WC_Payment_Gateway')) {
        $methods[] = 'Splitmo_Payment_Gateway';
        return $methods;
    }
}


add_action('plugins_loaded', 'init_splitmo_payment_gateway');
function init_splitmo_payment_gateway()
{
    class Splitmo_Payment_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'splitmo_payment_gateway';
            $this->icon = plugin_dir_url( __FILE__ ) . 'assets/images/sm-logo-hl.png';
            $this->form_fields = array();
            $this->has_fields = false;

            $this->method_title = __('Splitmo Checkout for WooCommerce', 'splitmo-payment-plugin');
            $this->method_description = __('Pay securely using Splitmo Checkout.', 'splitmo-payment-plugin');

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $this->environment = $this->get_option('environment');
            $this->api_secret_key = $this->get_option('api_secret_key');
            $this->api_public_key = $this->get_option('api_public_key');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        /**
         * Generate the authorization header for Splitmo API
         * @return string Authorization header
         */
        private function __generate_authorization_header()
        {
            $api_public_key = $this->get_option('api_public_key');
            $api_secret_key = $this->get_option('api_secret_key');
            return 'Basic ' . base64_encode($api_public_key . ':' . $api_secret_key);
        }

        /**
         * Property function to fetch appropriate url for the selected environment
         * @return string
         */
        private function __get_splitmo_url()
        {
            $selected_option = $this->get_option('environment');
            $url_mapping = array(
                'production' => 'https://app.splitmo.co/api/wc/',
                'sandbox' => 'https://sandbox.splitmo.co/api/wc/',
            );

            return isset($url_mapping[$selected_option]) ? $url_mapping[$selected_option] : '';
        }

        /**
         * Initialize Plugin Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'splitmo-payment-plugin'),
                    'type' => 'checkbox',
                    'label' => __('Enable Splitmo Payment Gateway', 'splitmo-payment-plugin'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'splitmo-payment-plugin'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'splitmo-payment-plugin'),
                    'default' => __('Splitmo Payment Gateway', 'splitmo-payment-plugin'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'splitmo-payment-plugin'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'splitmo-payment-plugin'),
                    'default' => __('Pay securely using Splitmo Payment Gateway.', 'splitmo-payment-plugin'),
                ),
                'api_public_key' => array(
                    'title' => __('API Public Key', 'splitmo-payment-plugin'),
                    'type' => 'text',
                    'description' => __('Enter your API public key.', 'splitmo-payment-plugin'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_secret_key' => array(
                    'title' => __('API Secret Key', 'splitmo-payment-plugin'),
                    'type' => 'text',
                    'description' => __('Enter your API secret key.', 'splitmo-payment-plugin'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'environment' => array(
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

            // Get the selected environment and corresponding base URL
            $selected_environment = $this->get_option('environment');
            $base_url = $this->__get_splitmo_url();

            // Check if base URL is valid
            if (empty($base_url)) {
                // Handle error: Invalid base URL
                wc_add_notice(__('Invalid Splitmo API base URL.', 'splitmo-payment-plugin'), 'error');
                return;
            }

            $authorization_header = $this->__generate_authorization_header();
            $checkout_link_response = wp_remote_post($base_url, array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $authorization_header,
                ),
            ));

            if (is_wp_error($checkout_link_response)) {
                $error_message = $checkout_link_response->get_error_message();
                wc_add_notice(__('An error occurred while processing your payment. Please try again later.', 'splitmo-payment-plugin'), 'error');
                return;
            }

            $response_body = wp_remote_retrieve_body($checkout_link_response);
            $response_data = json_decode($response_body, true);

            // Check if the checkout link was successfully generated
            if (isset($response_data['redirect_urls']) && isset($response_data['redirect_urls']['success_url'])) {
                // Redirect the customer to the checkout link
                return array(
                    'result' => 'success',
                    'redirect' => $response_data['redirect_urls']['success_url'],
                );
            } else {
                // Handle error
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
            if ('no' === $this->enabled) {
                return;
            }

            if (empty($this->api_secret_key) || empty($this->api_public_key)) {
                return;
            }
        }

        // public function validate_fields()
        // {
        // }

        // public function webhook()
        // {
        // }
    }
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');
function add_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=splitmo_payment_gateway') . '">' . __('Settings', 'splitmo-payment-plugin') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
