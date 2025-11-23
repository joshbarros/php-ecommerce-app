<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Exceptions\ValidationException;
use App\Helpers\CsrfHelper;
use App\Helpers\SessionHelper;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class CheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CartService $cartService,
        private readonly PaymentService $paymentService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Show checkout page
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $userId = SessionHelper::get('user_id');
        $sessionId = session_id();

        // Get cart
        $cartData = $this->cartService->getCart($userId, $sessionId);

        if (empty($cartData['items'])) {
            SessionHelper::flash('error', 'Your cart is empty');
            return new RedirectResponse('/cart');
        }

        // Validate cart
        $validation = $this->cartService->validateCart($userId, $sessionId);

        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                SessionHelper::flash('error', $error);
            }
            return new RedirectResponse('/cart');
        }

        return new HtmlResponse($this->renderCheckout($cartData));
    }

    /**
     * Process checkout
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            SessionHelper::flash('error', 'Invalid request');
            return new RedirectResponse('/checkout');
        }

        try {
            $userId = SessionHelper::get('user_id');
            $sessionId = session_id();

            // Get cart
            $cartData = $this->cartService->getCart($userId, $sessionId);

            if (empty($cartData['items'])) {
                throw new ValidationException('Your cart is empty');
            }

            // Prepare shipping address
            $shippingAddress = [
                'first_name' => $data['shipping_first_name'] ?? '',
                'last_name' => $data['shipping_last_name'] ?? '',
                'email' => $data['shipping_email'] ?? '',
                'phone' => $data['shipping_phone'] ?? '',
                'address_line1' => $data['shipping_address_line1'] ?? '',
                'address_line2' => $data['shipping_address_line2'] ?? '',
                'city' => $data['shipping_city'] ?? '',
                'state' => $data['shipping_state'] ?? '',
                'zip' => $data['shipping_zip'] ?? '',
                'country' => $data['shipping_country'] ?? 'US'
            ];

            // Validate shipping address
            $errors = $this->checkoutService->validateShippingAddress($shippingAddress);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    SessionHelper::flash('error', $error);
                }
                return new RedirectResponse('/checkout');
            }

            // Use same address for billing if checked
            $billingAddress = $shippingAddress;
            if (!isset($data['same_as_shipping'])) {
                $billingAddress = [
                    'first_name' => $data['billing_first_name'] ?? '',
                    'last_name' => $data['billing_last_name'] ?? '',
                    'email' => $data['billing_email'] ?? '',
                    'phone' => $data['billing_phone'] ?? '',
                    'address_line1' => $data['billing_address_line1'] ?? '',
                    'address_line2' => $data['billing_address_line2'] ?? '',
                    'city' => $data['billing_city'] ?? '',
                    'state' => $data['billing_state'] ?? '',
                    'zip' => $data['billing_zip'] ?? '',
                    'country' => $data['billing_country'] ?? 'US'
                ];

                $billingErrors = $this->checkoutService->validateShippingAddress($billingAddress);
                if (!empty($billingErrors)) {
                    foreach ($billingErrors as $error) {
                        SessionHelper::flash('error', $error);
                    }
                    return new RedirectResponse('/checkout');
                }
            }

            // Create order
            $order = $this->checkoutService->createOrderFromCart(
                $cartData['cart']['id'],
                $userId,
                $shippingAddress,
                $billingAddress,
                $data['notes'] ?? null
            );

            CsrfHelper::regenerateToken();

            // Redirect to payment page
            return new RedirectResponse('/checkout/payment/' . $order['uuid']);

        } catch (ValidationException $e) {
            SessionHelper::flash('error', $e->getMessage());
            return new RedirectResponse('/checkout');
        } catch (\Exception $e) {
            $this->logger->error('Checkout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            SessionHelper::flash('error', 'Failed to process order. Please try again.');
            return new RedirectResponse('/checkout');
        }
    }

    /**
     * Show order confirmation
     */
    public function confirmation(ServerRequestInterface $request): ResponseInterface
    {
        $uuid = $request->getAttribute('uuid');

        if (!$uuid) {
            return new RedirectResponse('/');
        }

        $order = $this->checkoutService->getOrderByUuid($uuid);

        if (!$order) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/');
        }

        // Verify order belongs to current user (if logged in)
        $userId = SessionHelper::get('user_id');
        if ($userId && $order['user_id'] != $userId) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/');
        }

        return new HtmlResponse($this->renderConfirmation($order));
    }

    /**
     * Show payment page
     */
    public function payment(ServerRequestInterface $request): ResponseInterface
    {
        $uuid = $request->getAttribute('uuid');

        if (!$uuid) {
            return new RedirectResponse('/');
        }

        $order = $this->checkoutService->getOrderByUuid($uuid);

        if (!$order) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/');
        }

        // Verify order belongs to current user (if logged in)
        $userId = SessionHelper::get('user_id');
        if ($userId && $order['user_id'] != $userId) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/');
        }

        // Check if payment already completed
        if ($order['payment_status'] === 'paid') {
            return new RedirectResponse('/checkout/confirmation/' . $uuid);
        }

        // Check if Stripe is configured
        if (!$this->paymentService->isConfigured()) {
            // Skip payment if Stripe not configured (for testing)
            $this->logger->warning('Stripe not configured, marking order as paid');

            // Update order to mark as paid
            $this->checkoutService->updateOrderStatus($order['id'], 'processing');

            SessionHelper::flash('success', 'Order placed successfully!');
            return new RedirectResponse('/checkout/confirmation/' . $uuid);
        }

        // Create payment intent
        try {
            $paymentIntent = $this->paymentService->createPaymentIntent($order);

            return new HtmlResponse($this->renderPayment($order, $paymentIntent));
        } catch (\Exception $e) {
            $this->logger->error('Payment intent creation failed', [
                'order_id' => $order['id'],
                'error' => $e->getMessage()
            ]);

            SessionHelper::flash('error', 'Payment processing failed. Please try again.');
            return new RedirectResponse('/account/orders/' . $uuid);
        }
    }

    /**
     * Render checkout page
     */
    private function renderCheckout(array $cartData): string
    {
        $csrfToken = CsrfHelper::getToken();
        $flashSuccess = SessionHelper::getFlash('success');
        $flashError = SessionHelper::getFlash('error');
        $user = SessionHelper::get('user');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - PHP E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #333; font-size: 28px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #764ba2; }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        .checkout-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        h2 { color: #333; margin-bottom: 20px; }
        .form-section { margin-bottom: 30px; }
        .form-section h3 { color: #667eea; margin-bottom: 15px; font-size: 18px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: 1 / 3; }
        label {
            display: block;
            color: #555;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea { resize: vertical; min-height: 80px; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child { border-bottom: none; }
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            background: #f5f5f5;
        }
        .item-details { flex: 1; }
        .item-name { font-weight: 600; color: #333; margin-bottom: 5px; }
        .item-meta { color: #666; font-size: 14px; }
        .item-price { font-weight: 700; color: #764ba2; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .summary-row.total {
            border-top: 2px solid #333;
            border-bottom: none;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 20px;
            font-weight: 700;
            color: #764ba2;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        @media (max-width: 968px) {
            .checkout-container { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛍️ Checkout</h1>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <a href="/cart">Cart</a>
            </nav>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-form">
                <h2>Shipping Information</h2>

                <form method="POST" action="/checkout/process">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-section">
                        <h3>Contact Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_first_name">First Name *</label>
                                <input type="text" id="shipping_first_name" name="shipping_first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_last_name">Last Name *</label>
                                <input type="text" id="shipping_last_name" name="shipping_last_name" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_email">Email *</label>
                                <input type="email" id="shipping_email" name="shipping_email" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_phone">Phone</label>
                                <input type="tel" id="shipping_phone" name="shipping_phone">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Shipping Address</h3>
                        <div class="form-group">
                            <label for="shipping_address_line1">Address Line 1 *</label>
                            <input type="text" id="shipping_address_line1" name="shipping_address_line1" required>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_line2">Address Line 2</label>
                            <input type="text" id="shipping_address_line2" name="shipping_address_line2">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_city">City *</label>
                                <input type="text" id="shipping_city" name="shipping_city" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_state">State *</label>
                                <input type="text" id="shipping_state" name="shipping_state" required maxlength="2" placeholder="CA">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_zip">ZIP Code *</label>
                                <input type="text" id="shipping_zip" name="shipping_zip" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_country">Country *</label>
                                <select id="shipping_country" name="shipping_country" required>
                                    <option value="US" selected>United States</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="checkbox-group">
                            <input type="checkbox" id="same_as_shipping" name="same_as_shipping" checked>
                            <label for="same_as_shipping" style="margin: 0;">Billing address same as shipping</label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Order Notes (Optional)</h3>
                        <div class="form-group">
                            <label for="notes">Special instructions or notes</label>
                            <textarea id="notes" name="notes" placeholder="Any special delivery instructions..."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Place Order</button>
                </form>
            </div>

            <div class="order-summary">
                <h2>Order Summary</h2>

                <div style="margin-bottom: 20px;">
                    <?php foreach ($cartData['items'] as $item): ?>
                        <div class="order-item">
                            <?php if ($item['product_image']): ?>
                                <img src="<?= htmlspecialchars($item['product_image']) ?>"
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-image"></div>
                            <?php endif; ?>

                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="item-meta">Qty: <?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?></div>
                            </div>

                            <div class="item-price">$<?= number_format($item['line_total'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>$<?= number_format($cartData['subtotal'], 2) ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>Calculated at checkout</span>
                </div>

                <div class="summary-row">
                    <span>Tax:</span>
                    <span>Calculated at checkout</span>
                </div>

                <div class="summary-row total">
                    <span>Estimated Total:</span>
                    <span>$<?= number_format($cartData['subtotal'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render order confirmation page
     */
    private function renderConfirmation(array $order): string
    {
        $user = SessionHelper::get('user');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - PHP E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .confirmation-box {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #48bb78;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .order-number {
            font-size: 24px;
            color: #667eea;
            font-weight: 700;
            margin: 20px 0;
        }
        .order-details {
            background: #f7fafc;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #666; font-weight: 500; }
        .detail-value { color: #333; font-weight: 600; }
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .order-item:last-child { border-bottom: none; }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-size: 20px;
            font-weight: 700;
            color: #764ba2;
            border-top: 2px solid #333;
            margin-top: 15px;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            margin: 10px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-box">
            <div class="success-icon">✓</div>
            <h1>Order Confirmed!</h1>
            <p style="color: #666; margin-bottom: 20px;">Thank you for your order. We've sent a confirmation email to <?= htmlspecialchars($order['shipping_email']) ?></p>

            <div class="order-number">
                Order #<?= htmlspecialchars($order['order_number']) ?>
            </div>

            <div class="order-details">
                <h2 style="margin-bottom: 20px; color: #333;">Order Details</h2>

                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="text-transform: capitalize;"><?= htmlspecialchars($order['status']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span class="detail-value"><?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Shipping To:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                        <?= htmlspecialchars($order['shipping_address_line1']) ?><br>
                        <?php if ($order['shipping_address_line2']): ?>
                            <?= htmlspecialchars($order['shipping_address_line2']) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip']) ?>
                    </span>
                </div>
            </div>

            <div class="order-details">
                <h2 style="margin-bottom: 20px; color: #333;">Items Ordered</h2>

                <?php foreach ($order['items'] as $item): ?>
                    <div class="order-item">
                        <span><?= htmlspecialchars($item['product_name']) ?> (×<?= $item['quantity'] ?>)</span>
                        <span>$<?= number_format($item['total'], 2) ?></span>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 20px;">
                    <div class="detail-row">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Shipping:</span>
                        <span>$<?= number_format($order['shipping'], 2) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Tax:</span>
                        <span>$<?= number_format($order['tax'], 2) ?></span>
                    </div>
                    <div class="total-row">
                        <span>Total:</span>
                        <span>$<?= number_format($order['total'], 2) ?></span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 30px;">
                <a href="/products" class="btn btn-primary">Continue Shopping</a>
                <?php if ($user): ?>
                    <a href="/account/orders" class="btn btn-secondary">View Orders</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render payment page
     */
    private function renderPayment(array $order, array $paymentIntent): string
    {
        $publishableKey = $this->paymentService->getPublishableKey();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - PHP E-Commerce</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 600px; margin: 0 auto; }
        .payment-box {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .order-number {
            font-size: 18px;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .amount {
            font-size: 36px;
            font-weight: 700;
            color: #764ba2;
            margin: 20px 0;
            text-align: center;
        }
        #payment-element {
            margin: 30px 0;
        }
        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        #error-message {
            color: #721c24;
            background: #f8d7da;
            padding: 12px;
            border-radius: 6px;
            margin-top: 20px;
            display: none;
        }
        .spinner {
            display: none;
            margin: 0 auto;
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .secure-badge {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-box">
            <h1>Complete Your Payment</h1>
            <div class="order-number">Order #<?= htmlspecialchars($order['order_number']) ?></div>

            <div class="amount">$<?= number_format($order['total'], 2) ?></div>

            <form id="payment-form">
                <div id="payment-element"></div>
                <button id="submit-button" class="btn btn-primary">
                    <span id="button-text">Pay Now</span>
                    <div class="spinner" id="spinner"></div>
                </button>
                <div id="error-message"></div>
            </form>

            <div class="secure-badge">
                🔒 Secure payment powered by Stripe
            </div>
        </div>
    </div>

    <script>
        const stripe = Stripe('<?= $publishableKey ?>');
        const clientSecret = '<?= $paymentIntent['client_secret'] ?>';

        const elements = stripe.elements({ clientSecret });
        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const errorMessage = document.getElementById('error-message');
        const spinner = document.getElementById('spinner');
        const buttonText = document.getElementById('button-text');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            submitButton.disabled = true;
            spinner.style.display = 'block';
            buttonText.style.display = 'none';
            errorMessage.style.display = 'none';

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: window.location.origin + '/checkout/confirmation/<?= $order['uuid'] ?>',
                },
            });

            if (error) {
                errorMessage.textContent = error.message;
                errorMessage.style.display = 'block';
                submitButton.disabled = false;
                spinner.style.display = 'none';
                buttonText.style.display = 'inline';
            }
        });
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
