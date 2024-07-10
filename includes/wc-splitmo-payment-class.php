<?php

class Splitmo_Payment_Gateway extends WC_Payment_Gateway
{
    protected $test_mode = false;
    private $domain = null;
    private $client = null;
    private $config = null;

    public function __construct()
    {
        $this->id = 'splitmo_payment_gateway';

        $this->domain = 'wcpg-special';
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
        $this->client = new SplitmoClient($this->get_api_public_key(), $this->get_api_secret_key(), $this->get_splitmo_url());

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Getter function for SplitmoApiConfig
     * @return Config | null
     */
    private function get_config()
    {   
        if ($this->config){
            return $this->config;
        }
        if ($this->client){
            $this->config = $this->client->getConfig();
            return $this->config;
        }
        return null;
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
    private function get_payment_options(){
        if (!$this->get_config()){
            wc_add_notice(__('Cannot Fetch Merchant Config', 'splitmo-payment-plugin'), 'error');
            wc_get_logger()->error('[get_payment_options] Cannot Fetch Merchant Config');
            return null;
        }
        $default_schedule_type = $this->get_config()->default_schedule_type;
        $max_installment_terms = $this->get_config()->max_installment_terms;
        $allow_direct_payment = $this->get_config()->allow_direct_payment;

        $payment_options = array();
        if($allow_direct_payment){
            $payment_options['direct'] = __('Straight Payment', $this->domain);
        }
        for ($i=1; $i <= $max_installment_terms; $i++) {
            $payment_label = 'Pay '. $i . ' '. SCHEDULE_TYPES[$default_schedule_type];
            $payment_label = $i == 1 ? 'Buy Now Pay Later (' . SCHEDULE_TYPES[$default_schedule_type] . ')' : $payment_label . ' installments';
            $payment_options[$i] = __($payment_label, $this->domain);
        }
        return $payment_options;
    }

    /**
     * Override process_payment with splitmo checkout process
     * @return array|null
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $repayment_term = $_POST['splitmo_transaction_type'] ?? $order->get_meta('splitmo_transaction_repayment_term');
        $schedule_type = $this->get_config()->default_schedule_type ?? $order->get_meta('splitmo_transaction_schedule_type');
        if ($repayment_term == 'direct'){
            $repayment_term = 1;
            $schedule_type = 'DI';
        }

        $order->update_meta_data('splitmo_transaction_schedule_type', $schedule_type);
        $order->update_meta_data('splitmo_transaction_repayment_term', $repayment_term);
        $order->save_meta_data();

        $checkout_url = $this->client->createTransaction($order, $schedule_type, $repayment_term);
        if (!$checkout_url) {
            return array(
                'result' => 'error'
            );
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


    public function payment_fields(){
        $payment_options = $this->get_payment_options();
        if (!is_array($payment_options)){
            return;
        }
    
        if ( $description = $this->get_description() ) {
            echo wpautop( wptexturize( $description ) );
        }
    
        // Add the necessary CSS to ensure each radio button and label occupy the whole row
        echo '<style>
        .woocommerce-input-wrapper {
            display: flex;
            flex-direction: column;
        }
        .woocommerce-input-wrapper .radio-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .woocommerce-input-wrapper .radio-option input[type="radio"] {
            margin-right: 10px;
        }
        .woocommerce-input-wrapper .error-message {
            color: red;
            margin-top: 10px;
            display: none;
        }
        </style>';
    
        $options = $this->get_payment_options();
        echo '<div class="woocommerce-input-wrapper" id="splitmo_radio_group">';
        foreach ($options as $key => $label) {
            echo '<div class="radio-option">
                <input type="radio" class="input-radio" value="' . esc_attr($key) . '" name="splitmo_transaction_type" id="splitmo_transaction_type_' . esc_attr($key) . '" required>
                <label for="splitmo_transaction_type_' . esc_attr($key) . '" class="radio">' . esc_html($label) . '</label>
            </div>';
        }
        echo '</div>';
        echo '<div class="error-message" id="error-message" style="display:none; color: red;">Please select a payment option.</div>';
    
        echo '<script>
        document.querySelector("form.checkout.woocommerce-checkout").addEventListener("submit", function(event) {
            const radioGroup = document.getElementById("splitmo_radio_group");
            const errorMessage = document.getElementById("error-message");
            const checkedRadio = radioGroup.querySelector("input[type=radio]:checked");
            if (!checkedRadio) {
                errorMessage.style.display = "block";
                event.preventDefault();
                event.stopPropagation();
            } else {
                errorMessage.style.display = "none";
            }
        });
        </script>';
    }
    


    public function save_order_payment_type_meta_data( $order, $data ) {
        $repayment_term = $_POST['splitmo_transaction_type'];
        if ( $data['payment_method'] === $this->id && isset($repayment_term) ){
            $schedule_type = $repayment_term == 'direct' ? 'DI' : $this->get_config()->default_schedule_type;
            $repayment_term = $repayment_term == 'direct' ? 1 : $repayment_term;
            $order->update_meta_data('splitmo_transaction_schedule_type', $schedule_type);
            $order->update_meta_data('splitmo_transaction_repayment_term', $repayment_term);
            $order->save_meta_data();
        }
    }

}
