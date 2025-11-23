<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

final class CheckoutService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create order from cart
     */
    public function createOrderFromCart(
        int $cartId,
        ?int $userId,
        array $shippingAddress,
        array $billingAddress,
        ?string $notes = null
    ): array {
        // Validate cart
        $cartItems = $this->cartRepository->getItems($cartId);

        if (empty($cartItems)) {
            throw new ValidationException('Cart is empty');
        }

        // Validate stock for all items
        $this->validateCartStock($cartItems);

        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['quantity'] * $item['price'];
        }

        $tax = $this->calculateTax($subtotal, $shippingAddress['state'] ?? 'CA');
        $shipping = $this->calculateShipping($cartItems);
        $total = $subtotal + $tax + $shipping;

        // Generate order number
        $orderNumber = $this->generateOrderNumber();

        // Create order
        $orderId = $this->orderRepository->create([
            'user_id' => $userId,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'currency' => 'USD',
            'payment_method' => null,
            'payment_status' => 'pending',
            'shipping_first_name' => $shippingAddress['first_name'],
            'shipping_last_name' => $shippingAddress['last_name'],
            'shipping_email' => $shippingAddress['email'],
            'shipping_phone' => $shippingAddress['phone'] ?? null,
            'shipping_address_line1' => $shippingAddress['address_line1'],
            'shipping_address_line2' => $shippingAddress['address_line2'] ?? null,
            'shipping_city' => $shippingAddress['city'],
            'shipping_state' => $shippingAddress['state'],
            'shipping_zip' => $shippingAddress['zip'],
            'shipping_country' => $shippingAddress['country'] ?? 'US',
            'billing_first_name' => $billingAddress['first_name'],
            'billing_last_name' => $billingAddress['last_name'],
            'billing_email' => $billingAddress['email'],
            'billing_phone' => $billingAddress['phone'] ?? null,
            'billing_address_line1' => $billingAddress['address_line1'],
            'billing_address_line2' => $billingAddress['address_line2'] ?? null,
            'billing_city' => $billingAddress['city'],
            'billing_state' => $billingAddress['state'],
            'billing_zip' => $billingAddress['zip'],
            'billing_country' => $billingAddress['country'] ?? 'US',
            'notes' => $notes
        ]);

        // Add order items and decrease stock
        foreach ($cartItems as $item) {
            $itemTotal = $item['quantity'] * $item['price'];

            $this->orderRepository->addItem($orderId, [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $itemTotal
            ]);

            // Decrease product stock
            $this->productRepository->decreaseStock($item['product_id'], $item['quantity']);
        }

        // Clear cart
        $this->cartRepository->clearItems($cartId);

        $this->logger->info('Order created from cart', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'total' => $total
        ]);

        // Get created order
        $order = $this->orderRepository->findById($orderId);
        $order['items'] = $this->orderRepository->getOrderItems($orderId);

        return $order;
    }

    /**
     * Validate cart stock availability
     */
    private function validateCartStock(array $cartItems): void
    {
        foreach ($cartItems as $item) {
            $product = $this->productRepository->findById($item['product_id']);

            if (!$product || !$product['is_active']) {
                throw new ValidationException(
                    $item['product_name'] . ' is no longer available'
                );
            }

            if ($product['stock_quantity'] < $item['quantity']) {
                throw new ValidationException(
                    $item['product_name'] . ' only has ' . $product['stock_quantity'] . ' items in stock'
                );
            }
        }
    }

    /**
     * Calculate tax (simplified - would normally use tax service)
     */
    private function calculateTax(float $subtotal, string $state): float
    {
        // Simplified tax calculation - in real app would use tax API
        $taxRates = [
            'CA' => 0.0725,  // California
            'NY' => 0.08,    // New York
            'TX' => 0.0625,  // Texas
            'FL' => 0.06,    // Florida
        ];

        $taxRate = $taxRates[$state] ?? 0;
        return round($subtotal * $taxRate, 2);
    }

    /**
     * Calculate shipping (simplified - would normally use shipping service)
     */
    private function calculateShipping(array $cartItems): float
    {
        // Simplified shipping calculation
        $itemCount = array_sum(array_column($cartItems, 'quantity'));

        if ($itemCount >= 10) {
            return 0; // Free shipping for 10+ items
        }

        return 9.99; // Flat rate shipping
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid()) . '-' . random_int(1000, 9999);
    }

    /**
     * Get order by ID
     */
    public function getOrder(int $orderId): ?array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order) {
            $order['items'] = $this->orderRepository->getOrderItems($orderId);
        }

        return $order;
    }

    /**
     * Get order by UUID
     */
    public function getOrderByUuid(string $uuid): ?array
    {
        $order = $this->orderRepository->findByUuid($uuid);

        if ($order) {
            $order['items'] = $this->orderRepository->getOrderItems($order['id']);
        }

        return $order;
    }

    /**
     * Get user orders
     */
    public function getUserOrders(int $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $orders = $this->orderRepository->findByUserId($userId, $perPage, $offset);
        $total = $this->orderRepository->countByUserId($userId);

        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage)
        ];
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            throw new ValidationException('Invalid order status');
        }

        $result = $this->orderRepository->updateStatus($orderId, $status);

        if ($result) {
            $this->logger->info('Order status updated', [
                'order_id' => $orderId,
                'status' => $status
            ]);
        }

        return $result;
    }

    /**
     * Validate shipping address
     */
    public function validateShippingAddress(array $address): array
    {
        $errors = [];

        if (empty($address['first_name'])) {
            $errors[] = 'First name is required';
        }

        if (empty($address['last_name'])) {
            $errors[] = 'Last name is required';
        }

        if (empty($address['email']) || !filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }

        if (empty($address['address_line1'])) {
            $errors[] = 'Address is required';
        }

        if (empty($address['city'])) {
            $errors[] = 'City is required';
        }

        if (empty($address['state'])) {
            $errors[] = 'State is required';
        }

        if (empty($address['zip'])) {
            $errors[] = 'ZIP code is required';
        }

        return $errors;
    }
}
