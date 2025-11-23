<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Helpers\SessionHelper;
use App\Services\CheckoutService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AccountController
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {
    }

    /**
     * Show account overview
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = SessionHelper::get('user');

        if (!$user) {
            SessionHelper::flash('error', 'Please login to view your account');
            return new RedirectResponse('/login');
        }

        return new HtmlResponse($this->renderAccountOverview($user));
    }

    /**
     * Show order history
     */
    public function orders(ServerRequestInterface $request): ResponseInterface
    {
        $user = SessionHelper::get('user');

        if (!$user) {
            SessionHelper::flash('error', 'Please login to view your orders');
            return new RedirectResponse('/login');
        }

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));

        $userId = SessionHelper::get('user_id');
        $ordersData = $this->checkoutService->getUserOrders($userId, $page, 10);

        return new HtmlResponse($this->renderOrderHistory($user, $ordersData));
    }

    /**
     * Show single order details
     */
    public function orderDetail(ServerRequestInterface $request): ResponseInterface
    {
        $user = SessionHelper::get('user');

        if (!$user) {
            SessionHelper::flash('error', 'Please login to view your orders');
            return new RedirectResponse('/login');
        }

        $uuid = $request->getAttribute('uuid');
        $order = $this->checkoutService->getOrderByUuid($uuid);

        if (!$order) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/account/orders');
        }

        // Verify order belongs to current user
        $userId = SessionHelper::get('user_id');
        if ($order['user_id'] != $userId) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/account/orders');
        }

        return new HtmlResponse($this->renderOrderDetail($user, $order));
    }

    /**
     * Render account overview
     */
    private function renderAccountOverview(array $user): string
    {
        $userId = SessionHelper::get('user_id');
        $recentOrders = $this->checkoutService->getUserOrders($userId, 1, 5);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - PHP E-Commerce</title>
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
        .account-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        .sidebar {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .sidebar h2 { color: #333; margin-bottom: 20px; font-size: 20px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #f7fafc;
            color: #667eea;
        }
        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h2 { color: #333; margin-bottom: 20px; }
        .profile-card {
            background: #f7fafc;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .profile-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .profile-row:last-child { border-bottom: none; }
        .profile-label { color: #666; min-width: 150px; font-weight: 500; }
        .profile-value { color: #333; font-weight: 600; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
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
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .order-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        .order-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .order-table a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .order-table a:hover { text-decoration: underline; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef5e7; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        @media (max-width: 968px) {
            .account-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👤 My Account</h1>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <a href="/cart">Cart</a>
                <a href="/logout">Logout</a>
            </nav>
        </div>

        <div class="account-container">
            <div class="sidebar">
                <h2>Account Menu</h2>
                <ul class="sidebar-menu">
                    <li><a href="/account" class="active">Overview</a></li>
                    <li><a href="/account/orders">Order History</a></li>
                </ul>
            </div>

            <div class="main-content">
                <h2>Account Overview</h2>

                <div class="profile-card">
                    <h3 style="margin-bottom: 20px; color: #667eea;">Profile Information</h3>
                    <div class="profile-row">
                        <div class="profile-label">Name:</div>
                        <div class="profile-value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    </div>
                    <div class="profile-row">
                        <div class="profile-label">Email:</div>
                        <div class="profile-value"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <?php if ($user['phone']): ?>
                        <div class="profile-row">
                            <div class="profile-label">Phone:</div>
                            <div class="profile-value"><?= htmlspecialchars($user['phone']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="profile-row">
                        <div class="profile-label">Account Type:</div>
                        <div class="profile-value" style="text-transform: capitalize;"><?= htmlspecialchars($user['role']) ?></div>
                    </div>
                    <div class="profile-row">
                        <div class="profile-label">Member Since:</div>
                        <div class="profile-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px;">Recent Orders</h3>
                <?php if (empty($recentOrders['orders'])): ?>
                    <p style="color: #666;">No orders yet. <a href="/products" style="color: #667eea;">Start shopping!</a></p>
                <?php else: ?>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders['orders'] as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($order['total'], 2) ?></td>
                                    <td><a href="/account/orders/<?= htmlspecialchars($order['uuid']) ?>">View Details</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px;">
                        <a href="/account/orders" class="btn btn-primary">View All Orders</a>
                    </div>
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
     * Render order history
     */
    private function renderOrderHistory(array $user, array $ordersData): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - PHP E-Commerce</title>
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
        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h2 { color: #333; margin-bottom: 20px; }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .order-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        .order-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .order-table a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .order-table a:hover { text-decoration: underline; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef5e7; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        .pagination a {
            padding: 8px 16px;
            background: #f7fafc;
            color: #667eea;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination a.active { background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Order History</h1>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/products">Products</a>
                <a href="/cart">Cart</a>
                <a href="/account">Account</a>
            </nav>
        </div>

        <div class="main-content">
            <h2>Your Orders</h2>

            <?php if (empty($ordersData['orders'])): ?>
                <p style="color: #666; margin-top: 20px;">No orders yet. <a href="/products" style="color: #667eea;">Start shopping!</a></p>
            <?php else: ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordersData['orders'] as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td>View details</td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td><a href="/account/orders/<?= htmlspecialchars($order['uuid']) ?>">View Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($ordersData['totalPages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $ordersData['totalPages']; $i++): ?>
                            <a href="/account/orders?page=<?= $i ?>" class="<?= $i === $ordersData['page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render order detail
     */
    private function renderOrderDetail(array $user, array $order): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - PHP E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
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
        .header h1 { color: #333; font-size: 24px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #764ba2; }
        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .order-number { font-size: 24px; color: #667eea; font-weight: 700; }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef5e7; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .detail-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
        }
        .detail-card h3 { color: #667eea; margin-bottom: 15px; font-size: 16px; }
        .detail-row { margin-bottom: 10px; color: #333; }
        .detail-label { color: #666; font-size: 14px; }
        .order-items {
            margin-top: 30px;
        }
        .order-items h3 { margin-bottom: 20px; color: #333; }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-weight: 600; color: #333; }
        .item-meta { color: #666; font-size: 14px; margin-top: 5px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #764ba2;
            border-top: 2px solid #333;
            padding-top: 15px;
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Details</h1>
            <nav class="nav-links">
                <a href="/account">Account</a>
                <a href="/account/orders">All Orders</a>
            </nav>
        </div>

        <div class="main-content">
            <div class="order-header">
                <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                    <?= htmlspecialchars($order['status']) ?>
                </span>
            </div>

            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Order Information</h3>
                    <div class="detail-row">
                        <div class="detail-label">Order Date</div>
                        <div><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Payment Method</div>
                        <div><?= $order['payment_method'] ? htmlspecialchars($order['payment_method']) : 'Not specified' ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Payment Status</div>
                        <div style="text-transform: capitalize;"><?= htmlspecialchars($order['payment_status']) ?></div>
                    </div>
                </div>

                <div class="detail-card">
                    <h3>Shipping Address</h3>
                    <div class="detail-row">
                        <?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                        <?= htmlspecialchars($order['shipping_address_line1']) ?><br>
                        <?php if ($order['shipping_address_line2']): ?>
                            <?= htmlspecialchars($order['shipping_address_line2']) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip']) ?><br>
                        <?= htmlspecialchars($order['shipping_country']) ?>
                    </div>
                    <?php if ($order['shipping_phone']): ?>
                        <div class="detail-row" style="margin-top: 10px;">
                            <div class="detail-label">Phone</div>
                            <div><?= htmlspecialchars($order['shipping_phone']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-items">
                <h3>Items Ordered</h3>
                <?php foreach ($order['items'] as $item): ?>
                    <div class="item-row">
                        <div>
                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="item-meta">
                                SKU: <?= htmlspecialchars($item['product_sku']) ?> |
                                Quantity: <?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?>
                            </div>
                        </div>
                        <div style="font-weight: 700; color: #333;">
                            $<?= number_format($item['total'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>$<?= number_format($order['shipping'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span>$<?= number_format($order['tax'], 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>$<?= number_format($order['total'], 2) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($order['notes']): ?>
                <div style="margin-top: 30px; padding: 20px; background: #f7fafc; border-radius: 8px;">
                    <h4 style="color: #667eea; margin-bottom: 10px;">Order Notes</h4>
                    <p style="color: #666;"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                </div>
            <?php endif; ?>

            <a href="/account/orders" class="btn btn-primary">← Back to Orders</a>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
