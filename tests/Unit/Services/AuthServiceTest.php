<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AuthService;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Exceptions\AuthException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

final class AuthServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private LoggerInterface $logger;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->authService = new AuthService($this->userRepository, $this->logger);

        // Clear session
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRegisterCreatesNewUser(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'StrongP@ss123',
            'name' => 'Test User'
        ];

        $expectedUser = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'customer'
        ];

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedUser);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $user = $this->authService->register(
            $userData['email'],
            $userData['password'],
            $userData['name']
        );

        $this->assertEquals($expectedUser, $user);
    }

    public function testRegisterThrowsExceptionWhenEmailExists(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn(['id' => 1, 'email' => 'existing@example.com']);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Email already registered');

        $this->authService->register('existing@example.com', 'Password123!', 'Test User');
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $hashedPassword = password_hash('Password123!', PASSWORD_ARGON2ID);

        $user = [
            'id' => 1,
            'email' => 'test@example.com',
            'password_hash' => $hashedPassword,
            'name' => 'Test User',
            'role' => 'customer'
        ];

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn($user);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $result = $this->authService->login('test@example.com', 'Password123!');

        $this->assertTrue($result);
        $this->assertArrayHasKey('user', $_SESSION);
        $this->assertEquals('test@example.com', $_SESSION['user']['email']);
    }

    public function testLoginFailsWithInvalidEmail(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('nonexistent@example.com')
            ->once()
            ->andReturn(null);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authService->login('nonexistent@example.com', 'Password123!');
    }

    public function testLoginFailsWithInvalidPassword(): void
    {
        $hashedPassword = password_hash('CorrectPassword123!', PASSWORD_ARGON2ID);

        $user = [
            'id' => 1,
            'email' => 'test@example.com',
            'password_hash' => $hashedPassword,
            'name' => 'Test User'
        ];

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn($user);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authService->login('test@example.com', 'WrongPassword123!');
    }

    public function testLogoutClearsSession(): void
    {
        $_SESSION['user'] = ['id' => 1, 'email' => 'test@example.com'];
        $_SESSION['other_data'] = 'should be removed';

        $this->logger
            ->shouldReceive('info')
            ->once();

        $this->authService->logout();

        $this->assertEmpty($_SESSION);
    }

    public function testGetCurrentUserReturnsUserWhenLoggedIn(): void
    {
        $userData = ['id' => 1, 'email' => 'test@example.com'];
        $_SESSION['user'] = $userData;

        $user = $this->authService->getCurrentUser();

        $this->assertEquals($userData, $user);
    }

    public function testGetCurrentUserReturnsNullWhenNotLoggedIn(): void
    {
        $user = $this->authService->getCurrentUser();

        $this->assertNull($user);
    }

    public function testIsLoggedInReturnsTrueWhenUserInSession(): void
    {
        $_SESSION['user'] = ['id' => 1];

        $this->assertTrue($this->authService->isLoggedIn());
    }

    public function testIsLoggedInReturnsFalseWhenNoUserInSession(): void
    {
        $this->assertFalse($this->authService->isLoggedIn());
    }

    public function testPasswordIsHashedWithArgon2ID(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Verify password is hashed with Argon2ID
                $passwordInfo = password_get_info($data['password_hash']);
                return $passwordInfo['algo'] === PASSWORD_ARGON2ID;
            }))
            ->andReturn(['id' => 1]);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $this->authService->register('test@example.com', 'Password123!', 'Test User');
    }
}
