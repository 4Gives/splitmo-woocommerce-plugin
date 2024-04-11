<?php
function get_api_urls() {
        $urls = array(
            'production' => 'https://app.splitmo.co/api/v1/',
            'sandbox' => 'https://sandbox.splitmo.co/api/v1/',
        );

        $dev_url = getenv('DEV_URL');
        if ($dev_url !== false) {
            $urls['local'] = $dev_url;
        }
        return $urls;
}

const SPLITMO_TRANSACTION_META = 'splitmo_transaction_reference_id';