<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class HomeController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info('Home page accessed');

        $html = $this->render('pages/home', [
            'title' => 'Welcome to E-Commerce',
            'appName' => $_ENV['APP_NAME'] ?? 'E-Commerce'
        ]);

        return new HtmlResponse($html);
    }

    private function render(string $template, array $data = []): string
    {
        extract($data);
        ob_start();

        $templatePath = ROOT_PATH . "/templates/{$template}.php";

        if (!file_exists($templatePath)) {
            return $this->renderSimplePage($data);
        }

        require $templatePath;
        return ob_get_clean();
    }

    private function renderSimplePage(array $data): string
    {
        $title = $data['title'] ?? 'E-Commerce';
        $appName = $data['appName'] ?? 'E-Commerce App';
        $version = PHP_VERSION;
        $env = $_ENV['APP_ENV'] ?? 'unknown';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            font-size: 3em;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #4bb71b;
            stroke-miterlimit: 10;
            margin: 20px auto;
            box-shadow: inset 0px 0px 0px #4bb71b;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke-miterlimit: 10;
            stroke: #4bb71b;
            fill: #fff;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 30px #4bb71b; }
        }
        p {
            font-size: 1.2em;
            color: #666;
            margin: 20px 0;
        }
        .info {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
            border-radius: 4px;
        }
        .info strong {
            display: block;
            margin-bottom: 10px;
            color: #667eea;
            font-size: 1.1em;
        }
        .info ul {
            list-style: none;
            padding-left: 0;
        }
        .info li {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info li:last-child {
            border-bottom: none;
        }
        .info code {
            background: #2d3748;
            color: #68d391;
            padding: 2px 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            margin: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>

        <h1>🎉 {$appName}</h1>
        <p>Your PHP E-Commerce application is up and running!</p>

        <div class="info">
            <strong>✅ System Status:</strong>
            <ul>
                <li>✓ PHP Version: <code>{$version}</code></li>
                <li>✓ Environment: <code>{$env}</code></li>
                <li>✓ Database: <code>Ready</code></li>
                <li>✓ Router: <code>Configured</code></li>
            </ul>
        </div>

        <div style="margin-top: 30px;">
            <a href="/products" class="btn">Browse Products</a>
            <a href="/login" class="btn">Login</a>
        </div>

        <p style="margin-top: 30px; font-size: 0.9em; color: #999;">
            Built with raw PHP following PSR standards
        </p>
    </div>
</body>
</html>
HTML;
    }
}
