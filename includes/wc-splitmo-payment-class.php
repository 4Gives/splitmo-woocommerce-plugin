<?php
class Splitmo_Payment_Gateway extends WC_Payment_Gateway
{   
    protected $test_mode = false;
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
        $this->test_mode = $this->get_option('splitmo_enable_test_mode');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Getter function for API Environment
     * @return string
     */
    private function get_environment()
    {
        return $this->get_option('splitmo_test_environment');
    }

    /**
     * Getter function for API Public Key
     * @return string
     */
    private function get_api_public_key()
    {
        if($this->test_mode == 'yes'){
            return $this->get_option('splitmo_api_test_public_key');
        }
        return $this->get_option('splitmo_api_public_key');
    }

    /**
     * Getter function for API Private Key
     * @return string
     */
    private function get_api_secret_key()
    {
        if($this->test_mode == 'yes'){
            return $this->get_option('splitmo_api_test_secret_key');
        }
        return $this->get_option('splitmo_api_secret_key');
    }

    /**
     * Property function to fetch appropriate url for the selected environment
     * @return string
     */
    private function get_splitmo_url()
    {
        if($this->test_mode == 'yes'){
            return isset(get_api_urls()[$this->get_environment()]) ? get_api_urls()[$this->get_environment()] : '';
        }
        return isset(get_api_urls()['production']) ? get_api_urls()['production'] : '';
    }

    /**
     * Initialize Plugin Form Fields
     */
    public function init_form_fields()
    {   
        $splitmo_environments = array();
        foreach (get_api_urls() as $key => $url) {
            $splitmo_environments[$key] = __($key, 'splitmo-payment-plugin');
        }

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
            'live_environment_title' => array(
                'title' => __('Live Environment Settings', 'splitmo-payment-plugin'),
                'type' => 'title',
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
            'test_environment_title' => array(
                'title' => __('Test Environment Settings', 'splitmo-payment-plugin'),
                'type' => 'title',
            ),
            'splitmo_enable_test_mode' => array(
                'title' => __('Enable Test Mode', 'splitmo-payment-plugin'),
                'type' => 'checkbox',
                'description' => __('Plugin request will be put to test mode', 'splitmo-payment-plugin'),
            ),
            'splitmo_api_test_public_key' => array(
                'title' => __('API Public Key', 'splitmo-payment-plugin'),
                'type' => 'text',
                'description' => __('Enter your API public key.', 'splitmo-payment-plugin'),
                'default' => '',
                'desc_tip' => true,
            ),
            'splitmo_api_test_secret_key' => array(
                'title' => __('API Secret Key', 'splitmo-payment-plugin'),
                'type' => 'text',
                'description' => __('Enter your API secret key.', 'splitmo-payment-plugin'),
                'default' => '',
                'desc_tip' => true,
            ),
        );

        if(count($splitmo_environments) > 2){
            $this-> form_fields['splitmo_test_environment'] = array(
                'title' => __('Test Endpoint', 'splitmo-payment-plugin'),
                'type' => 'select',
                'description' => __('Select the environment.', 'splitmo-payment-plugin'),
                'default' => 'sandbox',
                'options' => array_slice($splitmo_environments, 1),
                'desc_tip' => true,
            );
        }
    }

    /**
     * Override process_payment with splitmo checkout process
     * @return array|null
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $client = new SplitmoClient($this->get_api_public_key(), $this->get_api_secret_key(), $this->get_splitmo_url());

        $checkout_url = $client->createTransaction($order, 4);
        if (!$checkout_url) {
            return;
        }

        return array(
            'result' => 'success',
            'redirect' => $checkout_url,
        );

    }

    /**
     * Custom Payment Scripts to validate checkout transactions
     * @return null
     */
    public function payment_scripts()
    {
        if (empty($this->get_api_public_key()) || empty($this->get_api_secret_key())) {
            wc_add_notice(__('Invalid API Credentials, Please check your provided keys', 'splitmo-payment-plugin'), 'error');
            return;
        }
    }

    // public function validate_fields() {}

    // public function webhook() {}
}
