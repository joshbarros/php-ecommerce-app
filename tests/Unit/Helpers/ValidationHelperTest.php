<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ValidationHelper;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ValidationHelperTest extends TestCase
{
    public function testEmailValidationWithValidEmail(): void
    {
        $email = ValidationHelper::email('test@example.com');
        $this->assertEquals('test@example.com', $email);
    }

    public function testEmailValidationWithInvalidEmail(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email address');
        ValidationHelper::email('invalid-email');
    }

    public function testEmailValidationTrimsWhitespace(): void
    {
        $email = ValidationHelper::email('  test@example.com  ');
        $this->assertEquals('test@example.com', $email);
    }

    public function testPasswordValidationWithValidPassword(): void
    {
        $password = 'StrongP@ss123';
        $validated = ValidationHelper::password($password);
        $this->assertEquals($password, $validated);
    }

    public function testPasswordValidationRejectsTooShort(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');
        ValidationHelper::password('Short1!');
    }

    public function testPasswordValidationRequiresUppercase(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must contain at least one uppercase letter');
        ValidationHelper::password('password123!');
    }

    public function testPasswordValidationRequiresLowercase(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must contain at least one lowercase letter');
        ValidationHelper::password('PASSWORD123!');
    }

    public function testPasswordValidationRequiresNumber(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must contain at least one number');
        ValidationHelper::password('PasswordABC!');
    }

    public function testPasswordValidationRequiresSpecialCharacter(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must contain at least one special character');
        ValidationHelper::password('Password123');
    }

    public function testStringValidationWithValidString(): void
    {
        $result = ValidationHelper::string('Hello World', 1, 50);
        $this->assertEquals('Hello World', $result);
    }

    public function testStringValidationTrimming(): void
    {
        $result = ValidationHelper::string('  Hello  ', 1, 50);
        $this->assertEquals('Hello', $result);
    }

    public function testStringValidationMinLength(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('String must be at least 5 characters');
        ValidationHelper::string('Hi', 5, 50);
    }

    public function testStringValidationMaxLength(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('String must not exceed 5 characters');
        ValidationHelper::string('Too Long String', 1, 5);
    }

    public function testIntegerValidationWithValidInteger(): void
    {
        $result = ValidationHelper::integer('42');
        $this->assertEquals(42, $result);
    }

    public function testIntegerValidationWithInvalidInteger(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid integer value');
        ValidationHelper::integer('not-a-number');
    }

    public function testIntegerValidationWithMinValue(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Integer must be at least 10');
        ValidationHelper::integer('5', 10);
    }

    public function testIntegerValidationWithMaxValue(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Integer must not exceed 10');
        ValidationHelper::integer('15', 0, 10);
    }

    public function testDecimalValidationWithValidDecimal(): void
    {
        $result = ValidationHelper::decimal('19.99');
        $this->assertEquals(19.99, $result);
    }

    public function testDecimalValidationWithInvalidDecimal(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid decimal value');
        ValidationHelper::decimal('not-a-decimal');
    }

    public function testDecimalValidationWithMinValue(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Decimal must be at least 10.00');
        ValidationHelper::decimal('5.00', 10.00);
    }

    public function testSlugValidationWithValidSlug(): void
    {
        $result = ValidationHelper::slug('valid-slug-123');
        $this->assertEquals('valid-slug-123', $result);
    }

    public function testSlugValidationWithInvalidSlug(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid slug format');
        ValidationHelper::slug('Invalid Slug!');
    }

    public function testUuidValidationWithValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $result = ValidationHelper::uuid($uuid);
        $this->assertEquals($uuid, $result);
    }

    public function testUuidValidationWithInvalidUuid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        ValidationHelper::uuid('not-a-uuid');
    }

    public function testSanitizeHtmlRemovesTags(): void
    {
        $html = '<script>alert("XSS")</script><p>Safe content</p>';
        $result = ValidationHelper::sanitizeHtml($html);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Safe content', $result);
    }

    public function testSanitizeHtmlKeepsAllowedTags(): void
    {
        $html = '<p>Paragraph</p><strong>Bold</strong><script>alert()</script>';
        $result = ValidationHelper::sanitizeHtml($html, ['p', 'strong']);
        $this->assertStringContainsString('<p>Paragraph</p>', $result);
        $this->assertStringContainsString('<strong>Bold</strong>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }
}
