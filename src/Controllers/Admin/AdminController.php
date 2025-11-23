<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\CsrfHelper;
use App\Helpers\SessionHelper;
use App\Services\ProductService;
use App\Services\CategoryService;
use App\Services\CheckoutService;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class AdminController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly CheckoutService $checkoutService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Admin dashboard
     */
    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        // Get statistics
        $stats = [
            'total_orders' => $this->getTotalOrders(),
            'pending_orders' => $this->getPendingOrders(),
            'total_revenue' => $this->getTotalRevenue(),
            'total_products' => $this->productRepository->countAll(),
        ];

        $recentOrders = $this->orderRepository->getRecentOrders(10);

        return new HtmlResponse($this->renderDashboard($stats, $recentOrders));
    }

    /**
     * List all products
     */
    public function products(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));

        $productsData = $this->productService->getAllProducts($page, 20);
        $categories = $this->categoryService->getActiveCategories();

        return new HtmlResponse($this->renderProducts($productsData, $categories));
    }

    /**
     * Show create product form
     */
    public function createProduct(ServerRequestInterface $request): ResponseInterface
    {
        $categories = $this->categoryService->getActiveCategories();
        return new HtmlResponse($this->renderProductForm(null, $categories));
    }

    /**
     * Store new product
     */
    public function storeProduct(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            SessionHelper::flash('error', 'Invalid request');
            return new RedirectResponse('/admin/products');
        }

        try {
            $productId = $this->productRepository->create([
                'sku' => $data['sku'],
                'name' => $data['name'],
                'slug' => $this->generateSlug($data['name']),
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'price' => (float) $data['price'],
                'compare_price' => !empty($data['compare_price']) ? (float) $data['compare_price'] : null,
                'cost' => !empty($data['cost']) ? (float) $data['cost'] : null,
                'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
                'is_active' => isset($data['is_active']),
                'is_featured' => isset($data['is_featured'])
            ]);

            CsrfHelper::regenerateToken();
            SessionHelper::flash('success', 'Product created successfully!');

            return new RedirectResponse('/admin/products');

        } catch (\Exception $e) {
            $this->logger->error('Failed to create product', [
                'error' => $e->getMessage()
            ]);

            SessionHelper::flash('error', 'Failed to create product. Please try again.');
            return new RedirectResponse('/admin/products/create');
        }
    }

    /**
     * Show edit product form
     */
    public function editProduct(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $product = $this->productRepository->findById($id);

        if (!$product) {
            SessionHelper::flash('error', 'Product not found');
            return new RedirectResponse('/admin/products');
        }

        $categories = $this->categoryService->getActiveCategories();
        return new HtmlResponse($this->renderProductForm($product, $categories));
    }

    /**
     * Update product
     */
    public function updateProduct(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            SessionHelper::flash('error', 'Invalid request');
            return new RedirectResponse('/admin/products');
        }

        try {
            $this->productRepository->update($id, [
                'sku' => $data['sku'],
                'name' => $data['name'],
                'slug' => $this->generateSlug($data['name']),
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'price' => (float) $data['price'],
                'compare_price' => !empty($data['compare_price']) ? (float) $data['compare_price'] : null,
                'cost' => !empty($data['cost']) ? (float) $data['cost'] : null,
                'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
                'is_active' => isset($data['is_active']),
                'is_featured' => isset($data['is_featured'])
            ]);

            CsrfHelper::regenerateToken();
            SessionHelper::flash('success', 'Product updated successfully!');

            return new RedirectResponse('/admin/products');

        } catch (\Exception $e) {
            $this->logger->error('Failed to update product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            SessionHelper::flash('error', 'Failed to update product. Please try again.');
            return new RedirectResponse('/admin/products/' . $id . '/edit');
        }
    }

    /**
     * Delete product
     */
    public function deleteProduct(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request'], 403);
        }

        try {
            $this->productRepository->delete($id);
            CsrfHelper::regenerateToken();

            return new JsonResponse(['success' => true, 'message' => 'Product deleted successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Failed to delete product'], 500);
        }
    }

    /**
     * List all orders
     */
    public function orders(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $status = $queryParams['status'] ?? null;

        if ($status) {
            $orders = $this->orderRepository->findByStatus($status, 50);
        } else {
            $orders = $this->orderRepository->getRecentOrders(50);
        }

        return new HtmlResponse($this->renderOrders($orders, $status));
    }

    /**
     * View single order
     */
    public function viewOrder(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            SessionHelper::flash('error', 'Order not found');
            return new RedirectResponse('/admin/orders');
        }

        $order['items'] = $this->orderRepository->getOrderItems($id);

        return new HtmlResponse($this->renderOrderDetail($order));
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $data = $request->getParsedBody();

        if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request'], 403);
        }

        try {
            $this->checkoutService->updateOrderStatus($id, $data['status']);
            CsrfHelper::regenerateToken();

            return new JsonResponse(['success' => true, 'message' => 'Order status updated']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update order status', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Failed to update order status'], 500);
        }
    }

    /**
     * Helper: Get total orders count
     */
    private function getTotalOrders(): int
    {
        // Simple count query
        return count($this->orderRepository->getRecentOrders(999999));
    }

    /**
     * Helper: Get pending orders count
     */
    private function getPendingOrders(): int
    {
        return count($this->orderRepository->findByStatus('pending', 999999));
    }

    /**
     * Helper: Get total revenue
     */
    private function getTotalRevenue(): float
    {
        $orders = $this->orderRepository->getRecentOrders(999999);
        $total = 0;

        foreach ($orders as $order) {
            if (in_array($order['status'], ['processing', 'shipped', 'delivered'])) {
                $total += $order['total'];
            }
        }

        return $total;
    }

    /**
     * Helper: Generate slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        return $slug . '-' . substr(md5(uniqid()), 0, 6);
    }

    /**
     * Render dashboard
     */
    private function renderDashboard(array $stats, array $recentOrders): string
    {
        $user = SessionHelper::get('user');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PHP E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
        }
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        .stat-value.revenue { color: #48bb78; }
        .stat-value.orders { color: #667eea; }
        .stat-value.pending { color: #ed8936; }
        .stat-value.products { color: #9f7aea; }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card h3 { margin-bottom: 20px; color: #333; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
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
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🛍️ Admin Panel</h2>
            <ul class="sidebar-menu">
                <li><a href="/admin" class="active">📊 Dashboard</a></li>
                <li><a href="/admin/products">📦 Products</a></li>
                <li><a href="/admin/orders">🛒 Orders</a></li>
                <li><a href="/">← Back to Store</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div>Welcome, <?= htmlspecialchars($user['first_name']) ?>!</div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value revenue">$<?= number_format($stats['total_revenue'], 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value orders"><?= $stats['total_orders'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Orders</div>
                    <div class="stat-value pending"><?= $stats['pending_orders'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value products"><?= $stats['total_products'] ?></div>
                </div>
            </div>

            <div class="card">
                <h3>Recent Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['shipping_email']) ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td>
                                    <a href="/admin/orders/<?= $order['id'] ?>" class="btn btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render products list
     */
    private function renderProducts(array $productsData, array $categories): string
    {
        $flashSuccess = SessionHelper::getFlash('success');
        $flashError = SessionHelper::getFlash('error');
        $csrfToken = CsrfHelper::getToken();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
        }
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🛍️ Admin Panel</h2>
            <ul class="sidebar-menu">
                <li><a href="/admin">📊 Dashboard</a></li>
                <li><a href="/admin/products" class="active">📦 Products</a></li>
                <li><a href="/admin/orders">🛒 Orders</a></li>
                <li><a href="/">← Back to Store</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Products</h1>
                <a href="/admin/products/create" class="btn btn-primary">+ Add New Product</a>
            </div>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productsData['products'] as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['sku']) ?></td>
                                <td>$<?= number_format($product['price'], 2) ?></td>
                                <td><?= $product['stock_quantity'] ?></td>
                                <td><?= $product['is_active'] ? 'Active' : 'Inactive' ?></td>
                                <td>
                                    <a href="/admin/products/<?= $product['id'] ?>/edit" class="btn btn-secondary">Edit</a>
                                    <button onclick="deleteProduct(<?= $product['id'] ?>)" class="btn btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function deleteProduct(id) {
            if (!confirm('Are you sure you want to delete this product?')) {
                return;
            }

            try {
                const response = await fetch('/admin/products/' + id + '/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Failed to delete product');
            }
        }
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render product form (create/edit)
     */
    private function renderProductForm(?array $product, array $categories): string
    {
        $isEdit = $product !== null;
        $csrfToken = CsrfHelper::getToken();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Create' ?> Product - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
        }
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #555;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🛍️ Admin Panel</h2>
            <ul class="sidebar-menu">
                <li><a href="/admin">📊 Dashboard</a></li>
                <li><a href="/admin/products" class="active">📦 Products</a></li>
                <li><a href="/admin/orders">🛒 Orders</a></li>
                <li><a href="/">← Back to Store</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1><?= $isEdit ? 'Edit Product' : 'Create New Product' ?></h1>
            </div>

            <div class="card">
                <form method="POST" action="<?= $isEdit ? '/admin/products/' . $product['id'] . '/update' : '/admin/products/store' ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" value="<?= $isEdit ? htmlspecialchars($product['name']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="sku">SKU *</label>
                        <input type="text" id="sku" name="sku" value="<?= $isEdit ? htmlspecialchars($product['sku']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="short_description">Short Description</label>
                        <textarea id="short_description" name="short_description"><?= $isEdit ? htmlspecialchars($product['short_description'] ?? '') : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="description">Full Description</label>
                        <textarea id="description" name="description"><?= $isEdit ? htmlspecialchars($product['description'] ?? '') : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price *</label>
                        <input type="number" id="price" name="price" step="0.01" value="<?= $isEdit ? $product['price'] : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="compare_price">Compare Price (Optional)</label>
                        <input type="number" id="compare_price" name="compare_price" step="0.01" value="<?= $isEdit ? ($product['compare_price'] ?? '') : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="cost">Cost (Optional)</label>
                        <input type="number" id="cost" name="cost" step="0.01" value="<?= $isEdit ? ($product['cost'] ?? '') : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" value="<?= $isEdit ? $product['stock_quantity'] : 0 ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">-- No Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= ($isEdit && $product['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" <?= ($isEdit && $product['is_active']) || !$isEdit ? 'checked' : '' ?>>
                        <label for="is_active" style="margin: 0;">Active</label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_featured" name="is_featured" <?= ($isEdit && $product['is_featured']) ? 'checked' : '' ?>>
                        <label for="is_featured" style="margin: 0;">Featured</label>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Product' : 'Create Product' ?></button>
                        <a href="/admin/products" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render orders list
     */
    private function renderOrders(array $orders, ?string $statusFilter): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
        }
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
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
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .filter-group {
            margin-bottom: 20px;
        }
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🛍️ Admin Panel</h2>
            <ul class="sidebar-menu">
                <li><a href="/admin">📊 Dashboard</a></li>
                <li><a href="/admin/products">📦 Products</a></li>
                <li><a href="/admin/orders" class="active">🛒 Orders</a></li>
                <li><a href="/">← Back to Store</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Orders</h1>
            </div>

            <div class="card">
                <div class="filter-group">
                    <label>Filter by status:</label>
                    <select onchange="window.location.href = '/admin/orders?status=' + this.value">
                        <option value="">All Orders</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['shipping_email']) ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($order['payment_status']) ?></td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td>
                                    <a href="/admin/orders/<?= $order['id'] ?>" class="btn btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
    private function renderOrderDetail(array $order): string
    {
        $csrfToken = CsrfHelper::getToken();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
        }
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 10px; }
        .sidebar-menu a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            color: #666;
            font-size: 14px;
        }
        .detail-value {
            color: #333;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef5e7; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            color: #666;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>🛍️ Admin Panel</h2>
            <ul class="sidebar-menu">
                <li><a href="/admin">📊 Dashboard</a></li>
                <li><a href="/admin/products">📦 Products</a></li>
                <li><a href="/admin/orders" class="active">🛒 Orders</a></li>
                <li><a href="/">← Back to Store</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Order #<?= htmlspecialchars($order['order_number']) ?></h1>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 20px;">Order Information</h3>
                <div class="detail-grid">
                    <div>
                        <div class="detail-row">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value"><?= htmlspecialchars($order['payment_method'] ?? 'Not specified') ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Payment Status</div>
                            <div class="detail-value"><?= htmlspecialchars($order['payment_status']) ?></div>
                        </div>
                    </div>
                    <div>
                        <div class="detail-row">
                            <div class="detail-label">Shipping Address</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                                <?= htmlspecialchars($order['shipping_address_line1']) ?><br>
                                <?php if ($order['shipping_address_line2']): ?>
                                    <?= htmlspecialchars($order['shipping_address_line2']) ?><br>
                                <?php endif; ?>
                                <?= htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <label>Update Order Status:</label>
                    <select id="status-select">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button onclick="updateStatus()" class="btn btn-primary">Update Status</button>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 20px;">Order Items</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['product_sku']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>$<?= number_format($item['price'], 2) ?></td>
                                <td>$<?= number_format($item['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px; text-align: right;">
                    <div style="font-size: 16px; margin-bottom: 5px;">
                        <strong>Subtotal:</strong> $<?= number_format($order['subtotal'], 2) ?>
                    </div>
                    <div style="font-size: 16px; margin-bottom: 5px;">
                        <strong>Shipping:</strong> $<?= number_format($order['shipping'], 2) ?>
                    </div>
                    <div style="font-size: 16px; margin-bottom: 5px;">
                        <strong>Tax:</strong> $<?= number_format($order['tax'], 2) ?>
                    </div>
                    <div style="font-size: 24px; color: #667eea; margin-top: 10px;">
                        <strong>Total:</strong> $<?= number_format($order['total'], 2) ?>
                    </div>
                </div>
            </div>

            <a href="/admin/orders" class="btn btn-secondary">← Back to Orders</a>
        </div>
    </div>

    <script>
        async function updateStatus() {
            const status = document.getElementById('status-select').value;

            try {
                const response = await fetch('/admin/orders/<?= $order['id'] ?>/status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        status: status
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Failed to update status');
            }
        }
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
