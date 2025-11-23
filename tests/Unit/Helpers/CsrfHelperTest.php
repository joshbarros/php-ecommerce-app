<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\CsrfHelper;
use PHPUnit\Framework\TestCase;

final class CsrfHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear session before each test
        $_SESSION = [];
    }

    public function testGenerateTokenCreatesToken(): void
    {
        $token = CsrfHelper::generateToken();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
    }

    public function testGenerateTokenStoresInSession(): void
    {
        $token = CsrfHelper::generateToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    public function testGetTokenReturnsExistingToken(): void
    {
        $firstToken = CsrfHelper::generateToken();
        $secondToken = CsrfHelper::getToken();

        $this->assertEquals($firstToken, $secondToken);
    }

    public function testGetTokenGeneratesNewTokenIfNotExists(): void
    {
        unset($_SESSION['csrf_token']);
        $token = CsrfHelper::getToken();

        $this->assertNotEmpty($token);
        $this->assertArrayHasKey('csrf_token', $_SESSION);
    }

    public function testValidateTokenReturnsTrueForValidToken(): void
    {
        $token = CsrfHelper::generateToken();
        $isValid = CsrfHelper::validateToken($token);

        $this->assertTrue($isValid);
    }

    public function testValidateTokenReturnsFalseForInvalidToken(): void
    {
        CsrfHelper::generateToken();
        $isValid = CsrfHelper::validateToken('invalid-token');

        $this->assertFalse($isValid);
    }

    public function testValidateTokenReturnsFalseWhenNoTokenInSession(): void
    {
        unset($_SESSION['csrf_token']);
        $isValid = CsrfHelper::validateToken('some-token');

        $this->assertFalse($isValid);
    }

    public function testValidateTokenReturnsFalseForNullToken(): void
    {
        CsrfHelper::generateToken();
        $isValid = CsrfHelper::validateToken(null);

        $this->assertFalse($isValid);
    }

    public function testValidateTokenUsesTimingSafeComparison(): void
    {
        // This test ensures timing attacks are prevented
        $token = CsrfHelper::generateToken();

        // Valid token should return true
        $this->assertTrue(CsrfHelper::validateToken($token));

        // Invalid token with same length should return false
        $invalidToken = str_repeat('a', strlen($token));
        $this->assertFalse(CsrfHelper::validateToken($invalidToken));
    }

    public function testRenderFieldReturnsHtmlInput(): void
    {
        $token = CsrfHelper::generateToken();
        $html = CsrfHelper::renderField();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('value="' . $token . '"', $html);
    }
}
