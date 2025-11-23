<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;

final class EmailService
{
    private PHPMailer $mailer;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->mailer = new PHPMailer(true);
        $this->configureMail();
    }

    /**
     * Configure PHPMailer with environment settings
     */
    private function configureMail(): void
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'localhost';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = (int) ($_ENV['MAIL_PORT'] ?? 587);

            // Set default from
            $this->mailer->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ecommerce.local',
                $_ENV['MAIL_FROM_NAME'] ?? 'E-Commerce Store'
            );

            // Enable HTML
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';

        } catch (Exception $e) {
            $this->logger->error('Failed to configure mailer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation(array $order, array $orderItems): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($order['shipping_email'], $order['shipping_name']);

            $this->mailer->Subject = "Order Confirmation - #{$order['order_number']}";
            $this->mailer->Body = $this->renderOrderConfirmation($order, $orderItems);
            $this->mailer->AltBody = $this->renderOrderConfirmationPlain($order, $orderItems);

            $this->mailer->send();

            $this->logger->info('Order confirmation email sent', [
                'order_id' => $order['id'],
                'email' => $order['shipping_email']
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send order confirmation', [
                'order_id' => $order['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate(array $order, string $oldStatus, string $newStatus): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($order['shipping_email'], $order['shipping_name']);

            $this->mailer->Subject = "Order Status Update - #{$order['order_number']}";
            $this->mailer->Body = $this->renderOrderStatusUpdate($order, $oldStatus, $newStatus);
            $this->mailer->AltBody = $this->renderOrderStatusUpdatePlain($order, $oldStatus, $newStatus);

            $this->mailer->send();

            $this->logger->info('Order status update email sent', [
                'order_id' => $order['id'],
                'status' => $newStatus
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send order status update', [
                'order_id' => $order['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to new users
     */
    public function sendWelcomeEmail(array $user): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($user['email'], $user['name']);

            $this->mailer->Subject = 'Welcome to ' . ($_ENV['APP_NAME'] ?? 'E-Commerce Store');
            $this->mailer->Body = $this->renderWelcomeEmail($user);
            $this->mailer->AltBody = $this->renderWelcomeEmailPlain($user);

            $this->mailer->send();

            $this->logger->info('Welcome email sent', ['user_id' => $user['id']]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send low inventory alert to admin
     */
    public function sendLowInventoryAlert(array $product): bool
    {
        try {
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@ecommerce.local';

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($adminEmail);

            $this->mailer->Subject = "Low Inventory Alert - {$product['name']}";
            $this->mailer->Body = $this->renderLowInventoryAlert($product);
            $this->mailer->AltBody = $this->renderLowInventoryAlertPlain($product);

            $this->mailer->send();

            $this->logger->info('Low inventory alert sent', ['product_id' => $product['id']]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send low inventory alert', [
                'product_id' => $product['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Render order confirmation HTML email
     */
    private function renderOrderConfirmation(array $order, array $orderItems): string
    {
        $itemsHtml = '';
        foreach ($orderItems as $item) {
            $itemsHtml .= sprintf(
                '<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">%s</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">%d</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">$%.2f</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">$%.2f</td>
                </tr>',
                htmlspecialchars($item['product_name']),
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 28px;">Order Confirmation</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Thank you for your order!</p>
                        </td>
                    </tr>

                    <!-- Order Details -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="font-size: 16px; color: #333; margin: 0 0 20px 0;">
                                Hi <strong>{$order['shipping_name']}</strong>,
                            </p>
                            <p style="font-size: 14px; color: #666; line-height: 1.6; margin: 0 0 20px 0;">
                                We've received your order and will process it shortly. You'll receive another email when your order ships.
                            </p>

                            <table width="100%" style="margin: 20px 0; border: 1px solid #eee; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f9f9f9; border-bottom: 1px solid #eee;">
                                        <strong>Order Number:</strong> #{$order['order_number']}<br>
                                        <strong>Order Date:</strong> {$order['created_at']}<br>
                                        <strong>Status:</strong> <span style="color: #667eea;">{$order['status']}</span>
                                    </td>
                                </tr>
                            </table>

                            <!-- Items Table -->
                            <table width="100%" style="margin: 20px 0; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f9f9f9;">
                                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Item</th>
                                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                                        <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Price</th>
                                        <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" style="padding: 10px; text-align: right;">Subtotal:</td>
                                        <td style="padding: 10px; text-align: right;">$%.2f</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding: 10px; text-align: right;">Tax:</td>
                                        <td style="padding: 10px; text-align: right;">$%.2f</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding: 10px; text-align: right;">Shipping:</td>
                                        <td style="padding: 10px; text-align: right;">$%.2f</td>
                                    </tr>
                                    <tr style="font-size: 18px; font-weight: bold;">
                                        <td colspan="3" style="padding: 10px; text-align: right; border-top: 2px solid #ddd;">Total:</td>
                                        <td style="padding: 10px; text-align: right; border-top: 2px solid #ddd; color: #667eea;">$%.2f</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Shipping Address -->
                            <table width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f9f9f9; border-radius: 4px;">
                                        <strong style="display: block; margin-bottom: 10px;">Shipping Address:</strong>
                                        {$order['shipping_name']}<br>
                                        {$order['shipping_address']}<br>
                                        {$order['shipping_city']}, {$order['shipping_state']} {$order['shipping_zip']}<br>
                                        {$order['shipping_country']}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9f9f9; padding: 20px; text-align: center; border-top: 1px solid #eee;">
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                Questions? Contact us at support@ecommerce.local
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Render order confirmation plain text email
     */
    private function renderOrderConfirmationPlain(array $order, array $orderItems): string
    {
        $itemsText = "ORDER ITEMS:\n";
        $itemsText .= str_repeat('-', 70) . "\n";

        foreach ($orderItems as $item) {
            $itemsText .= sprintf(
                "%s x%d @ $%.2f = $%.2f\n",
                $item['product_name'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            );
        }

        return <<<TEXT
ORDER CONFIRMATION

Hi {$order['shipping_name']},

Thank you for your order! We've received it and will process it shortly.

Order Number: #{$order['order_number']}
Order Date: {$order['created_at']}
Status: {$order['status']}

{$itemsText}
{str_repeat('-', 70)}
Subtotal: \${$order['subtotal']}
Tax: \${$order['tax']}
Shipping: \${$order['shipping']}
TOTAL: \${$order['total']}

SHIPPING ADDRESS:
{$order['shipping_name']}
{$order['shipping_address']}
{$order['shipping_city']}, {$order['shipping_state']} {$order['shipping_zip']}
{$order['shipping_country']}

Questions? Contact us at support@ecommerce.local
TEXT;
    }

    /**
     * Render order status update HTML
     */
    private function renderOrderStatusUpdate(array $order, string $oldStatus, string $newStatus): string
    {
        $statusMessages = [
            'processing' => 'Your order is being processed.',
            'shipped' => 'Great news! Your order has been shipped.',
            'delivered' => 'Your order has been delivered.',
            'cancelled' => 'Your order has been cancelled.'
        ];

        $message = $statusMessages[$newStatus] ?? 'Your order status has been updated.';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;">
                            <h1 style="color: white; margin: 0;">Order Status Update</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p style="font-size: 16px; color: #333;">Hi <strong>{$order['shipping_name']}</strong>,</p>
                            <p style="font-size: 14px; color: #666; line-height: 1.6;">{$message}</p>
                            <table width="100%" style="margin: 20px 0; border: 1px solid #eee; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <strong>Order Number:</strong> #{$order['order_number']}<br>
                                        <strong>New Status:</strong> <span style="color: #667eea; text-transform: uppercase;">{$newStatus}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function renderOrderStatusUpdatePlain(array $order, string $oldStatus, string $newStatus): string
    {
        return "ORDER STATUS UPDATE\n\nOrder #{$order['order_number']} status: {$newStatus}";
    }

    private function renderWelcomeEmail(array $user): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">
    <h1>Welcome to our store, {$user['name']}!</h1>
    <p>Thank you for registering. We're excited to have you!</p>
</body>
</html>
HTML;
    }

    private function renderWelcomeEmailPlain(array $user): string
    {
        return "Welcome {$user['name']}! Thank you for registering.";
    }

    private function renderLowInventoryAlert(array $product): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">
    <h1>Low Inventory Alert</h1>
    <p><strong>{$product['name']}</strong> (SKU: {$product['sku']}) is running low.</p>
    <p>Current stock: <strong>{$product['stock_quantity']}</strong></p>
</body>
</html>
HTML;
    }

    private function renderLowInventoryAlertPlain(array $product): string
    {
        return "LOW INVENTORY: {$product['name']} - Stock: {$product['stock_quantity']}";
    }
}
