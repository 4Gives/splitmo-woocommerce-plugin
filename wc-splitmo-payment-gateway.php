<?php
/*
Plugin Name: Splitmo Checkout for WooCommerce
Description: Accept payments or installments in the Philippines with Splitmo. Seamlessly integrated into WooCommerce.
Version: 1.0.0
Author: Splitmo
Author URI: https://splitmo.co/
 */

defined('ABSPATH') || exit;

define('WC_SPLITMO_PG_VERSION', '1.0.0');
define('WC_SPLITMO_PG_ABSPATH', __DIR__ . '/');

add_action('plugins_loaded', function () {
    include_once ('includes/class-splitmo-plugin-core.php');
});

add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = "Splitmo_Payment_Gateway";
    return $gateways;
});

