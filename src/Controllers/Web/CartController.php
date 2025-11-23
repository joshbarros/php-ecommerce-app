<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Helpers\CsrfHelper;
use App\Helpers\SessionHelper;
use App\Services\CartService;
use App\Exceptions\ValidationException;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CartController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    /**
     * Display shopping cart
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $userId = SessionHelper::get('user_id');
        $sessionId = session_id();

        $cartData = $this->cartService->getCart($userId, $sessionId);

        return new HtmlResponse($this->renderCart($cartData));
    }

    /**
     * Add item to cart
     */
    public function add(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request'
            ], 403);
        }

        try {
            $productId = (int) ($data['product_id'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 1);

            if ($productId <= 0) {
                throw new ValidationException('Invalid product');
            }

            $userId = SessionHelper::get('user_id');
            $sessionId = session_id();

            $cartData = $this->cartService->addItem($userId, $sessionId, $productId, $quantity);

            CsrfHelper::regenerateToken();

            return new JsonResponse([
                'success' => true,
                'message' => 'Item added to cart',
                'cart_count' => $cartData['item_count']
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to add item to cart'
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request'
            ], 403);
        }

        try {
            $productId = (int) ($data['product_id'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 0);

            if ($productId <= 0) {
                throw new ValidationException('Invalid product');
            }

            $userId = SessionHelper::get('user_id');
            $sessionId = session_id();

            $cartData = $this->cartService->updateItemQuantity($userId, $sessionId, $productId, $quantity);

            CsrfHelper::regenerateToken();

            return new JsonResponse([
                'success' => true,
                'message' => $quantity === 0 ? 'Item removed from cart' : 'Cart updated',
                'cart' => [
                    'subtotal' => number_format($cartData['subtotal'], 2),
                    'item_count' => $cartData['item_count']
                ]
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update cart'
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function remove(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request'
            ], 403);
        }

        try {
            $productId = (int) ($data['product_id'] ?? 0);

            if ($productId <= 0) {
                throw new ValidationException('Invalid product');
            }

            $userId = SessionHelper::get('user_id');
            $sessionId = session_id();

            $cartData = $this->cartService->removeItem($userId, $sessionId, $productId);

            CsrfHelper::regenerateToken();
            SessionHelper::flash('success', 'Item removed from cart');

            return new JsonResponse([
                'success' => true,
                'message' => 'Item removed from cart',
                'cart' => [
                    'subtotal' => number_format($cartData['subtotal'], 2),
                    'item_count' => $cartData['item_count']
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to remove item'
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clear(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            SessionHelper::flash('error', 'Invalid request');
            return new RedirectResponse('/cart');
        }

        try {
            $userId = SessionHelper::get('user_id');
            $sessionId = session_id();

            $this->cartService->clearCart($userId, $sessionId);

            CsrfHelper::regenerateToken();
            SessionHelper::flash('success', 'Cart cleared successfully');

            return new RedirectResponse('/cart');
        } catch (\Exception $e) {
            SessionHelper::flash('error', 'Failed to clear cart');
            return new RedirectResponse('/cart');
        }
    }

    /**
     * Get cart count (for AJAX requests)
     */
    public function count(ServerRequestInterface $request): ResponseInterface
    {
        $userId = SessionHelper::get('user_id');
        $sessionId = session_id();

        $count = $this->cartService->getItemCount($userId, $sessionId);

        return new JsonResponse(['count' => $count]);
    }

    /**
     * Render cart page
     */
    private function renderCart(array $cartData): string
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
    <title>Shopping Cart - PHP E-Commerce</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #764ba2;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .cart-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .cart-items {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .cart-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart h2 {
            color: #666;
            margin-bottom: 20px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .item-name a {
            color: #667eea;
            text-decoration: none;
        }

        .item-name a:hover {
            text-decoration: underline;
        }

        .item-sku {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .item-price {
            color: #764ba2;
            font-size: 20px;
            font-weight: 700;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 6px;
        }

        .quantity-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .quantity-btn:hover {
            background: #764ba2;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            font-size: 16px;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .line-total {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row.total {
            border-top: 2px solid #333;
            border-bottom: none;
            margin-top: 10px;
            padding-top: 20px;
            font-size: 24px;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        @media (max-width: 968px) {
            .cart-container {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
            }

            .item-actions {
                grid-column: 1 / 3;
                flex-direction: row;
                justify-content: space-between;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Shopping Cart</h1>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <?php if ($user): ?>
                    <a href="/account">Account</a>
                    <a href="/logout">Logout</a>
                <?php else: ?>
                    <a href="/login">Login</a>
                <?php endif; ?>
            </nav>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?php if (empty($cartData['items'])): ?>
            <div class="cart-items">
                <div class="empty-cart">
                    <h2>Your cart is empty</h2>
                    <p style="color: #666; margin-bottom: 30px;">Start shopping and add items to your cart!</p>
                    <a href="/products" class="btn btn-primary" style="width: auto; padding: 14px 40px;">Continue Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="cart-items">
                    <h2 style="margin-bottom: 20px; color: #333;">Cart Items (<?= $cartData['item_count'] ?>)</h2>

                    <?php foreach ($cartData['items'] as $item): ?>
                        <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
                            <?php if ($item['product_image']): ?>
                                <img src="<?= htmlspecialchars($item['product_image']) ?>"
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-image"></div>
                            <?php endif; ?>

                            <div class="item-details">
                                <div>
                                    <div class="item-name">
                                        <a href="/products/<?= htmlspecialchars($item['product_slug']) ?>">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </a>
                                    </div>
                                    <div class="item-sku">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                </div>
                                <div class="item-price">$<?= number_format($item['price'], 2) ?> each</div>
                            </div>

                            <div class="item-actions">
                                <div class="line-total">$<?= number_format($item['line_total'], 2) ?></div>
                                <div class="quantity-controls">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
                                    <input type="number"
                                           class="quantity-input"
                                           value="<?= $item['quantity'] ?>"
                                           min="1"
                                           max="<?= $item['stock_quantity'] ?>"
                                           onchange="updateQuantity(<?= $item['product_id'] ?>, this.value)">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                                </div>
                                <button class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 20px;">
                        <form method="POST" action="/cart/clear" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear your cart?')">
                                Clear Cart
                            </button>
                        </form>
                    </div>
                </div>

                <div class="cart-summary">
                    <h2 style="margin-bottom: 20px; color: #333;">Order Summary</h2>

                    <div class="summary-row">
                        <span>Subtotal (<?= $cartData['item_count'] ?> items):</span>
                        <span id="cart-subtotal">$<?= number_format($cartData['subtotal'], 2) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>Calculated at checkout</span>
                    </div>

                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="cart-total">$<?= number_format($cartData['subtotal'], 2) ?></span>
                    </div>

                    <div style="margin-top: 30px;">
                        <a href="/checkout" class="btn btn-primary">Proceed to Checkout</a>
                        <a href="/products" class="btn btn-secondary">Continue Shopping</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = '<?= $csrfToken ?>';

        async function updateQuantity(productId, quantity) {
            quantity = parseInt(quantity);

            if (quantity < 0) {
                return;
            }

            try {
                const response = await fetch('/cart/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        product_id: productId,
                        quantity: quantity
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (quantity === 0) {
                        location.reload();
                    } else {
                        document.getElementById('cart-subtotal').textContent = '$' + data.cart.subtotal;
                        document.getElementById('cart-total').textContent = '$' + data.cart.subtotal;
                        location.reload();
                    }
                } else {
                    alert(data.message);
                    location.reload();
                }
            } catch (error) {
                alert('Failed to update cart. Please try again.');
                location.reload();
            }
        }

        async function removeItem(productId) {
            if (!confirm('Remove this item from your cart?')) {
                return;
            }

            try {
                const response = await fetch('/cart/remove', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        product_id: productId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Failed to remove item. Please try again.');
            }
        }
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
