<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\ApiErrorException;

final class PaymentService
{
    private string $stripeSecretKey;
    private string $stripeWebhookSecret;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->stripeWebhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

        if (!empty($this->stripeSecretKey)) {
            Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    /**
     * Create a Stripe Payment Intent
     */
    public function createPaymentIntent(array $order): array
    {
        if (empty($this->stripeSecretKey)) {
            throw new ValidationException('Stripe is not configured. Please set STRIPE_SECRET_KEY in .env');
        }

        try {
            // Convert to cents for Stripe
            $amountInCents = (int) ($order['total'] * 100);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($order['currency']),
                'description' => 'Order #' . $order['order_number'],
                'metadata' => [
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'customer_email' => $order['shipping_email']
                ],
                'receipt_email' => $order['shipping_email'],
                'shipping' => [
                    'name' => $order['shipping_first_name'] . ' ' . $order['shipping_last_name'],
                    'address' => [
                        'line1' => $order['shipping_address_line1'],
                        'line2' => $order['shipping_address_line2'],
                        'city' => $order['shipping_city'],
                        'state' => $order['shipping_state'],
                        'postal_code' => $order['shipping_zip'],
                        'country' => $order['shipping_country']
                    ]
                ]
            ]);

            $this->logger->info('Stripe Payment Intent created', [
                'order_id' => $order['id'],
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amountInCents
            ]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error', [
                'error' => $e->getMessage(),
                'order_id' => $order['id']
            ]);

            throw new ValidationException('Payment processing failed. Please try again.');
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(string $payload, string $signature): bool
    {
        if (empty($this->stripeWebhookSecret)) {
            $this->logger->error('Stripe webhook secret not configured');
            return false;
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->stripeWebhookSecret
            );

            $this->logger->info('Stripe webhook received', [
                'type' => $event->type,
                'event_id' => $event->id
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentIntentCanceled($event->data->object);
                    break;

                default:
                    $this->logger->info('Unhandled webhook event type', ['type' => $event->type]);
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Webhook handling failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            $this->logger->error('Order ID not found in payment intent metadata');
            return;
        }

        $this->orderRepository->update((int) $orderId, [
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'status' => 'processing'
        ]);

        $this->logger->info('Payment succeeded, order updated', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id
        ]);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentIntentFailed($paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            $this->logger->error('Order ID not found in payment intent metadata');
            return;
        }

        $this->orderRepository->update((int) $orderId, [
            'payment_status' => 'failed'
        ]);

        $this->logger->warning('Payment failed', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id
        ]);
    }

    /**
     * Handle canceled payment
     */
    private function handlePaymentIntentCanceled($paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            $this->logger->error('Order ID not found in payment intent metadata');
            return;
        }

        $this->orderRepository->update((int) $orderId, [
            'payment_status' => 'canceled',
            'status' => 'cancelled'
        ]);

        $this->logger->info('Payment canceled', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id
        ]);
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentIntentId): ?string
    {
        if (empty($this->stripeSecretKey)) {
            return null;
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            return $paymentIntent->status;
        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentIntentId, ?int $amountInCents = null): bool
    {
        if (empty($this->stripeSecretKey)) {
            throw new ValidationException('Stripe is not configured');
        }

        try {
            $refundParams = ['payment_intent' => $paymentIntentId];

            if ($amountInCents !== null) {
                $refundParams['amount'] = $amountInCents;
            }

            $refund = \Stripe\Refund::create($refundParams);

            $this->logger->info('Refund created', [
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refund->id,
                'amount' => $amountInCents
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Refund failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);

            throw new ValidationException('Refund processing failed. Please try again.');
        }
    }

    /**
     * Check if Stripe is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->stripeSecretKey);
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): ?string
    {
        return $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null;
    }
}
