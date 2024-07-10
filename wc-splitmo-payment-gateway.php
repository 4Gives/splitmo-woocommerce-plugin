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

require 'vendor/autoload.php';

add_action('plugins_loaded', 'init_splitmo_payment_gateway');
function init_splitmo_payment_gateway()
{
    require_once(plugin_dir_path(__FILE__) . 'wc-splitmo-constants.php');
    require_once(plugin_dir_path(__FILE__) . 'includes/SplitmoClient.php');
    require_once(plugin_dir_path(__FILE__) . 'includes/wc-splitmo-payment-class.php');
    add_filter('woocommerce_payment_gateways', function ($gateways) {
        if (class_exists('WC_Payment_Gateway')) {
            include_once 'wc-splitmo-hooks.php';
            $gateways[] = new Splitmo_Payment_Gateway();;
            return $gateways;
        }
    });
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');
function add_action_links($links)
{
    $admin_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=splitmo_payment_gateway');
    $settings_plugin = __('Settings', 'splitmo-payment-plugin');
    return array_merge(array('<a href="' . $admin_url . '">' .  $settings_plugin . '</a>'), $links);
}

