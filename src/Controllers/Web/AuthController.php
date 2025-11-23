<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Helpers\CsrfHelper;
use App\Helpers\SessionHelper;
use App\Services\AuthService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Show login form
     */
    public function showLogin(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $html = $this->render('auth/login', [
            'title' => 'Login',
            'error' => SessionHelper::getFlash('error'),
            'success' => SessionHelper::getFlash('success'),
            'csrf_token' => CsrfHelper::getTokenField()
        ]);

        return new HtmlResponse($html);
    }

    /**
     * Process login
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $data = $request->getParsedBody();

        try {
            // Validate CSRF token
            if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
                throw new AuthenticationException('Invalid request');
            }

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $remember = isset($data['remember']);

            // Attempt login
            $user = $this->authService->login($email, $password, $remember);

            // Regenerate CSRF token after successful login
            CsrfHelper::regenerateToken();

            // Redirect to intended URL or home
            $redirectUrl = SessionHelper::get('intended_url', '/');
            SessionHelper::remove('intended_url');

            SessionHelper::flash('success', 'Welcome back, ' . $user['first_name'] . '!');

            return new RedirectResponse($redirectUrl);

        } catch (AuthenticationException | ValidationException $e) {
            SessionHelper::flash('error', $e->getMessage());
            return new RedirectResponse('/login');
        }
    }

    /**
     * Show registration form
     */
    public function showRegister(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $html = $this->render('auth/register', [
            'title' => 'Register',
            'error' => SessionHelper::getFlash('error'),
            'success' => SessionHelper::getFlash('success'),
            'csrf_token' => CsrfHelper::getTokenField()
        ]);

        return new HtmlResponse($html);
    }

    /**
     * Process registration
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $data = $request->getParsedBody();

        try {
            // Validate CSRF token
            if (!CsrfHelper::validateToken($data['csrf_token'] ?? null)) {
                throw new ValidationException('Invalid request');
            }

            // Register user
            $userId = $this->authService->register([
                'email' => $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'password_confirmation' => $data['password_confirmation'] ?? '',
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'phone' => $data['phone'] ?? null
            ]);

            // Auto-login after registration
            $user = $this->authService->login($data['email'], $data['password']);

            SessionHelper::flash('success', 'Welcome! Your account has been created.');

            return new RedirectResponse('/');

        } catch (ValidationException $e) {
            SessionHelper::flash('error', $e->getMessage());
            return new RedirectResponse('/register');
        }
    }

    /**
     * Logout user
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $this->authService->logout();

        SessionHelper::flash('success', 'You have been logged out');

        return new RedirectResponse('/');
    }

    /**
     * Render template
     */
    private function render(string $template, array $data = []): string
    {
        extract($data);
        ob_start();

        $templatePath = ROOT_PATH . "/templates/{$template}.php";

        if (!file_exists($templatePath)) {
            return $this->renderPlaceholder($template, $data);
        }

        require $templatePath;
        return ob_get_clean();
    }

    /**
     * Render placeholder for missing templates
     */
    private function renderPlaceholder(string $template, array $data): string
    {
        $title = $data['title'] ?? 'Authentication';
        $error = $data['error'] ?? null;
        $success = $data['success'] ?? null;

        $alertHtml = '';
        if ($error) {
            $alertHtml = '<div class="alert error">' . htmlspecialchars($error) . '</div>';
        }
        if ($success) {
            $alertHtml = '<div class="alert success">' . htmlspecialchars($success) . '</div>';
        }

        if ($template === 'auth/login') {
            return $this->renderLoginForm($title, $alertHtml, $data);
        }

        if ($template === 'auth/register') {
            return $this->renderRegisterForm($title, $alertHtml, $data);
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 400px; margin: 50px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 30px; color: #333; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; }
        .alert.error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert.success { background: #efe; color: #3c3; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$title}</h1>
        {$alertHtml}
        <p>Template will be created soon...</p>
    </div>
</body>
</html>
HTML;
    }

    private function renderLoginForm(string $title, string $alertHtml, array $data): string
    {
        $csrfToken = $data['csrf_token'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { max-width: 420px; width: 100%; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { margin-bottom: 10px; color: #333; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; }
        .alert.error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert.success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 15px; transition: all 0.2s; }
        input[type="email"]:focus, input[type="password"]:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .checkbox-group { display: flex; align-items: center; margin-bottom: 20px; }
        .checkbox-group input { margin-right: 8px; }
        .checkbox-group label { margin: 0; font-weight: normal; font-size: 14px; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); }
        button:active { transform: translateY(0); }
        .links { text-align: center; margin-top: 24px; }
        .links a { color: #667eea; text-decoration: none; font-size: 14px; }
        .links a:hover { text-decoration: underline; }
        .divider { margin: 20px 0; text-align: center; color: #999; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome Back</h1>
        <p class="subtitle">Login to your account</p>

        {$alertHtml}

        <form method="POST" action="/login">
            {$csrfToken}

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit">Sign In</button>
        </form>

        <div class="links">
            <a href="/register">Don't have an account? Sign up</a>
        </div>

        <div class="divider">or</div>

        <div class="links">
            <a href="/">← Back to Home</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderRegisterForm(string $title, string $alertHtml, array $data): string
    {
        $csrfToken = $data['csrf_token'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { max-width: 480px; width: 100%; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { margin-bottom: 10px; color: #333; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; }
        .alert.error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert.success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 14px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="tel"] { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 15px; transition: all 0.2s; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); }
        button:active { transform: translateY(0); }
        .links { text-align: center; margin-top: 24px; }
        .links a { color: #667eea; text-decoration: none; font-size: 14px; }
        .links a:hover { text-decoration: underline; }
        .password-requirements { font-size: 12px; color: #666; margin-top: 5px; line-height: 1.5; }
        .divider { margin: 20px 0; text-align: center; color: #999; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        <p class="subtitle">Join us today!</p>

        {$alertHtml}

        <form method="POST" action="/register">
            {$csrfToken}

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required placeholder="John">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required placeholder="Doe">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="••••••••">
                <div class="password-requirements">
                    Must be at least 8 characters with uppercase, lowercase, and numbers
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="links">
            <a href="/login">Already have an account? Sign in</a>
        </div>

        <div class="divider">or</div>

        <div class="links">
            <a href="/">← Back to Home</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
