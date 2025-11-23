<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CSRF Token Helper
 * Implements synchronizer token pattern for CSRF protection
 */
final class CsrfHelper
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    /**
     * Generate or retrieve existing CSRF token
     */
    public static function generateToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Validate CSRF token using timing-safe comparison
     */
    public static function validateToken(?string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || $token === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    /**
     * Regenerate CSRF token (use after successful form submission)
     */
    public static function regenerateToken(): string
    {
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Get HTML hidden input field with CSRF token
     */
    public static function getTokenField(): string
    {
        $token = self::generateToken();
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get CSRF token for AJAX requests
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }
}
