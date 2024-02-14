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

require_once plugin_dir_path(__FILE__) . 'includes/class-splitmo-payment-gateway.php';

function init_splitmo_payment_plugin()
{
    if (class_exists('WC_Payment_Gateway')) {
        function add_splitmo_payment_gateway($methods)
        {
            $methods[] = 'Splitmo_Payment_Gateway';
            return $methods;
        }
        add_filter('woocommerce_payment_gateways', 'add_splitmo_payment_gateway');
    }
}
add_action('plugins_loaded', 'init_splitmo_payment_plugin');


function add_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=splitmo_payment_gateway') . '">' . __('Settings', 'splitmo-payment-plugin') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');
