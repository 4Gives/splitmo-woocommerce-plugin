<?php
/**
 * Core Payment Gateway for Splitmo
 *
 * @category Plugin
 * @package  Splitmo
 * @author   4Gives Developers <developers@4gives.com>
 */

class Splitmo_Payment_Gateway extends WC_Payment_Gateway
{
    public $id = 'splitmo';
    public $form_fields = array();
    public $method_title = 'Splitmo Checkout for WooCommerce';
    public $method_description = 'Accept Payments or Installments with Splitmo';
    public $has_fields = false;

    public function __construct()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->description = $this->get_option('description');
        $this->public_key = get_option('wc_splitmo_public_key');
        $this->secret_key = get_option('wc_splitmo_secret_key');
        $this->environment = get_option('wc_splitmo_environment');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize Plugin Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable Splitmo Payments',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'type' => 'text',
                'title' => 'Title',
                'description' => 'This controls the title that ' .
                'the user sees during checkout.',
                'default' => 'Pay with Splitmo',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description that ' .
                'the user sees during checkout.',
                'default' => 'Pay directly or by installments using splitmo',
            ),
            'wc_splitmo_environment' => array(
                'title' => 'Select Splitmo Environment',
                'type' => 'select',
                'description' => 'Select an option from the dropdown.',
                'options' => array(
                    'production' => '[Production] - v2.4gives.com',
                    'sandbox' => '[Sandbox] - v2-sandbox.4gives.com',
                ),
                'default' => 'production',
                'desc_tip' => true,
            ),
            'wc_splitmo_public_key' => array(
                'title' => 'Public Key',
                'type' => 'text',
                'description' => 'Enter your Splitmo public key',
                'default' => '',
                'desc_tip' => true,
            ),
            'wc_splitmo_secret_key' => array(
                'title' => 'Secret Key',
                'type' => 'text',
                'description' => 'Enter your Splitmo secret key',
                'default' => '',
                'desc_tip' => true,
            ),

        );
    }

    /**
     * Property function to fetch appropriate url for the selected environment
     * @return string
     */
    public function get_splitmo_url()
    {
        $selected_option = $this->get_option('wc_splitmo_environment');
        $url_mapping = array(
            'production' => 'https://v2.4gives.com/api/wc/',
            'sandbox' => 'https://v2-sandbox.4gives.com/api/wc/',
        );

        return isset($url_mapping[$selected_option]) ? $url_mapping[$selected_option] : '';
    }

    /**
     * Get Icon for checkout page
     * @return string
     */
    public function get_icon()
    {
        $icons_str = '<img class="splitmo-payment-method" src="'
            . WC_SPLITMO_PLUGIN_URL
            . '/assets/images/sm-logo-orange.png" alt="splitmo-img" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Override process_payment with splitmo checkout process
     * @return string
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('processing', __('Payment received.', 'splitmo-payment-gateway'));
        WC()->cart->empty_cart();
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }
}
