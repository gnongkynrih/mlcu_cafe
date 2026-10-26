<?php

namespace App\Services;

use Razorpay\Api\Api;

/**
 * The only class that talks to Razorpay.
 *
 * The browser is allowed to see the key id (it is public).
 * The secret stays here, on the server, and is used twice:
 * once to create the payment, and once to check the signature.
 */
class RazorpayGateway
{
    /**
     * Ask Razorpay to start a payment and return its order id.
     *
     * Razorpay counts money in paise, not rupees. ₹50.00 must be sent as 5000.
     * The id it returns looks like "order_ABC" and is what the payment window expects.
     */
    public function createOrder(int $amountInPaise, string $receipt): string
    {
        $order = $this->api()->order->create([
            'receipt' => $receipt,
            'amount' => $amountInPaise,
            'currency' => 'INR',
        ]);

        return (string) $order['id'];
    }

    /**
     * Check that Razorpay really collected this money.
     *
     * Razorpay signs "order id|payment id" with the secret. If the signature
     * does not match, this throws SignatureVerificationError and the caller
     * must leave the cafe bill open.
     */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): void
    {
        $this->api()->utility->verifyPaymentSignature([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);
    }

    /**
     * Build the SDK client from config. env() is not used here because
     * a cached config file would not load the .env file.
     */
    protected function api(): Api
    {
        return new Api(
            (string) config('services.razorpay.key'),
            (string) config('services.razorpay.secret'),
        );
    }
}
