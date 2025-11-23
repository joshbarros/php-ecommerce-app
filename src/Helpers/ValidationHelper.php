<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\ValidationException;

/**
 * Input Validation Helper
 * Provides common validation methods
 */
final class ValidationHelper
{
    /**
     * Validate and sanitize email
     */
    public static function email(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        return $email;
    }

    /**
     * Validate integer within range
     */
    public static function integer(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $options = ['options' => ['min_range' => $min, 'max_range' => $max]];
        $int = filter_var($value, FILTER_VALIDATE_INT, $options);

        if ($int === false) {
            throw new ValidationException("Invalid integer or out of range [{$min}, {$max}]");
        }

        return $int;
    }

    /**
     * Validate string length
     */
    public static function string(string $value, int $minLength = 0, int $maxLength = 255): string
    {
        $value = trim($value);
        $length = mb_strlen($value);

        if ($length < $minLength || $length > $maxLength) {
            throw new ValidationException(
                "String length must be between {$minLength} and {$maxLength}"
            );
        }

        return $value;
    }

    /**
     * Validate password strength
     */
    public static function password(string $password): string
    {
        $minLength = 8;
        $length = mb_strlen($password);

        if ($length < $minLength) {
            throw new ValidationException("Password must be at least {$minLength} characters");
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            throw new ValidationException('Password must contain at least one uppercase letter');
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            throw new ValidationException('Password must contain at least one lowercase letter');
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            throw new ValidationException('Password must contain at least one number');
        }

        return $password;
    }

    /**
     * Validate URL
     */
    public static function url(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid URL format');
        }

        // Only allow http and https
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new ValidationException('Only HTTP and HTTPS URLs are allowed');
        }

        return $url;
    }

    /**
     * Sanitize HTML output
     */
    public static function sanitizeHtml(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate required field
     */
    public static function required(mixed $value, string $fieldName = 'Field'): mixed
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            throw new ValidationException("{$fieldName} is required");
        }

        return $value;
    }

    /**
     * Validate and sanitize name
     */
    public static function name(string $name, int $minLength = 2, int $maxLength = 100): string
    {
        $name = self::string($name, $minLength, $maxLength);

        // Allow letters, spaces, hyphens, and apostrophes
        if (!preg_match("/^[a-zA-Z\s\-']+$/", $name)) {
            throw new ValidationException('Name contains invalid characters');
        }

        return $name;
    }

    /**
     * Validate phone number
     */
    public static function phone(string $phone): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($cleaned) < 10 || strlen($cleaned) > 15) {
            throw new ValidationException('Invalid phone number');
        }

        return $cleaned;
    }
}
