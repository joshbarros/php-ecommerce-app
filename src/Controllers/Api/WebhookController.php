<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\PaymentService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class WebhookController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handle Stripe webhook
     */
    public function stripe(ServerRequestInterface $request): ResponseInterface
    {
        $payload = (string) $request->getBody();
        $signature = $request->getHeaderLine('Stripe-Signature');

        if (empty($signature)) {
            $this->logger->warning('Stripe webhook received without signature');
            return new JsonResponse(['error' => 'No signature provided'], 400);
        }

        try {
            $result = $this->paymentService->handleWebhook($payload, $signature);

            if ($result) {
                return new JsonResponse(['status' => 'success']);
            }

            return new JsonResponse(['error' => 'Webhook processing failed'], 400);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse(['error' => 'Internal server error'], 500);
        }
    }
}
