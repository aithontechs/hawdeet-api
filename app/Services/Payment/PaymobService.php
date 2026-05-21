<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Exception;

class PaymobService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('paymob.base_url');
        $this->apiKey  = config('paymob.api_key');
    }

    private function getAuthToken(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new Exception('Paymob auth failed: ' . $response->body());
        }

        return $response->json('token');
    }

    private function createOrder(string $token, int $amountCents, string $merchantOrderId): int
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token'        => $token,
            'delivery_needed'   => false,
            'amount_cents'      => $amountCents,
            'currency'          => 'EGP',
            'merchant_order_id' => $merchantOrderId,
            'items'             => [],
        ]);

        if ($response->failed()) {
            throw new Exception('Paymob create order failed: ' . $response->body());
        }

        return $response->json('id');
    }

    private function getPaymentKey(
        string $token,
        int    $amountCents,
        int    $orderId,
        int    $integrationId,
        array  $billingData
    ): string {
        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token'     => $token,
            'amount_cents'   => $amountCents,
            'expiration'     => 3600,
            'order_id'       => $orderId,
            'currency'       => 'EGP',
            'integration_id' => $integrationId,
            'billing_data'   => $billingData,
        ]);

        if ($response->failed()) {
            throw new Exception('Paymob payment key failed: ' . $response->body());
        }

        return $response->json('token');
    }

    public function createCardPayment(int $amountCents, array $billingData, string $merchantOrderId): array
    {
        $token      = $this->getAuthToken();
        $orderId    = $this->createOrder($token, $amountCents, $merchantOrderId);
        $paymentKey = $this->getPaymentKey(
            $token, $amountCents, $orderId,
            config('paymob.card_integration'),
            $billingData
        );

        $iframeId  = config('paymob.iframe_id');
        $iframeUrl = "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}";

        return [
            'payment_key' => $paymentKey,
            'iframe_url'  => $iframeUrl,
            'order_id'    => $orderId,
        ];
    }

    public function createWalletPayment(int $amountCents, array $billingData, string $merchantOrderId, string $phoneNumber): array
    {
        $token      = $this->getAuthToken();
        $orderId    = $this->createOrder($token, $amountCents, $merchantOrderId);
        $paymentKey = $this->getPaymentKey(
            $token, $amountCents, $orderId,
            config('paymob.wallet_integration'),
            $billingData
        );

        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $phoneNumber,
                'subtype'    => 'WALLET',
            ],
            'payment_token' => $paymentKey,
        ]);

        if ($response->failed()) {
            throw new Exception('Paymob wallet pay failed: ' . $response->body());
        }

        $responseData = $response->json();

        $errorMessage = $responseData['data']['message'] ?? null;
        $realErrors   = [
            'Receiver is not registered',
            'Invalid phone number',
            'Insufficient funds',
            'Account is blocked',
            'Transaction limit exceeded',
        ];

        if ($errorMessage && in_array($errorMessage, $realErrors)) {
            throw new Exception("Wallet payment failed: {$errorMessage}");
        }

        $redirectUrl = !empty($responseData['iframe_redirection_url'])
            ? $responseData['iframe_redirection_url']
            : (!empty($responseData['redirect_url'])
                ? $responseData['redirect_url']
                : null);

        if (!$redirectUrl) {
            throw new Exception('Wallet redirect_url not found. Response: ' . json_encode($responseData));
        }

        return [
            'redirect_url' => $redirectUrl,
            'order_id'     => $orderId,
        ];
    }

    public function verifyHmac(array $data, string $receivedHmac): bool
    {
        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured',
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
            'is_voided', 'order', 'owner', 'pending',
            'source_data_pan', 'source_data_sub_type', 'source_data_type', 'success',
        ];

        $hashString = '';
        foreach ($fields as $field) {
            $hashString .= $data[$field] ?? '';
        }

        $computed = hash_hmac('sha512', $hashString, config('paymob.hmac_secret'));

        return hash_equals($computed, $receivedHmac);
    }


    public function getPaymentKeyForExistingOrder(
    int    $amountCents,
    int    $paymobOrderId,
    array  $billingData,
    string $method
    ): string {
        $token         = $this->getAuthToken();
        $integrationId = $method === 'card'
            ? config('paymob.card_integration')
            : config('paymob.wallet_integration');

        return $this->getPaymentKey(
            $token,
            $amountCents,
            $paymobOrderId,
            $integrationId,
            $billingData
        );
    }

    public function payWithWalletKey(string $paymentKey, string $phoneNumber): string
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $phoneNumber,
                'subtype'    => 'WALLET',
            ],
            'payment_token' => $paymentKey,
        ]);

        if ($response->failed()) {
            throw new Exception('Paymob wallet pay failed: ' . $response->body());
        }

        $responseData = $response->json();
        $redirectUrl  = $responseData['redirect_url']
                    ?? $responseData['iframe_redirection_url']
                    ?? null;

        if (!$redirectUrl) {
            throw new Exception('Wallet redirect_url not found. Response: ' . json_encode($responseData));
        }

        return $redirectUrl;
}

}
