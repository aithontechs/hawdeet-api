<?php



    return [
        'api_key'            => env('PAYMOB_API_KEY'),
        'card_integration'   => env('PAYMOB_CARD_INTEGRATION_ID'),
        'wallet_integration' => env('PAYMOB_WALLET_INTEGRATION_ID'),
        'iframe_id'          => env('PAYMOB_IFRAME_ID'),
        'hmac_secret'        => env('PAYMOB_HMAC_SECRET'),
        'base_url'           => 'https://accept.paymob.com/api',
    ];
