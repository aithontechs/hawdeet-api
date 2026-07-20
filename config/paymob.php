<?php



    return [
        'api_key'            => env('PAYMOB_API_KEY'),
        'multi_currency_supported' => env('PAYMOB_MULTI_CURRENCY', false),
        'card_integration'   => env('PAYMOB_CARD_INTEGRATION_ID'),
        'wallet_integration' => env('PAYMOB_WALLET_INTEGRATION_ID'),
        'card_integration_usd'   => env('PAYMOB_CARD_INTEGRATION_USD'),
        'wallet_integration_usd' => env('PAYMOB_WALLET_INTEGRATION_USD'),
        'checkout_id' => env('PAYMOB_CHECKOUT_ID', '5692093'),
        'iframe_id'          => env('PAYMOB_IFRAME_ID'),
        'hmac_secret'        => env('PAYMOB_HMAC_SECRET'),
        'public_key'         => env('PAYMOB_PUBLIC_KEY'),
        'base_url'           => 'https://accept.paymob.com/api',
        'secret_key' => env('PAYMOB_SECRET_KEY'),
    ];
