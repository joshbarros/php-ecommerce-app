<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AuthService;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for complete authentication flows
 * Tests user registration, login, and logout workflows
 */
final class AuthenticationFlowTest extends TestCase
{
    private PDO $pdo;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test database connection
        $this->pdo = createTestDatabase();

        // Clean database before each test
        cleanupTestDatabase($this->pdo);

        // Create a mock logger
        $logger = new class {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        // Initialize service
        $userRepository = new UserRepository($this->pdo);
        $this->authService = new AuthService($userRepository, $logger);

        // Clear session
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        cleanupTestDatabase($this->pdo);
        parent::tearDown();
    }

    public function testCompleteRegistrationAndLoginFlow(): void
    {
        // Step 1: Register a new user
        $email = 'newuser@example.com';
        $password = 'SecureP@ss123';
        $name = 'New User';

        $user = $this->authService->register($email, $password, $name);

        // Verify user was created
        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($name, $user['name']);
        $this->assertEquals('customer', $user['role']);
        $this->assertArrayHasKey('id', $user);

        // Step 2: Logout to clear session
        $this->authService->logout();
        $this->assertFalse($this->authService->isLoggedIn());

        // Step 3: Login with registered credentials
        $loginResult = $this->authService->login($email, $password);

        // Verify login succeeded
        $this->assertTrue($loginResult);
        $this->assertTrue($this->authService->isLoggedIn());

        // Step 4: Verify current user
        $currentUser = $this->authService->getCurrentUser();
        $this->assertNotNull($currentUser);
        $this->assertEquals($email, $currentUser['email']);
        $this->assertEquals($name, $currentUser['name']);

        // Step 5: Logout
        $this->authService->logout();
        $this->assertFalse($this->authService->isLoggedIn());
        $this->assertNull($this->authService->getCurrentUser());
    }

    public function testRegistrationValidation(): void
    {
        // Test 1: Email already exists
        $this->authService->register('duplicate@example.com', 'Password123!', 'User One');

        $this->expectException(\App\Exceptions\AuthException::class);
        $this->expectExceptionMessage('Email already registered');

        $this->authService->register('duplicate@example.com', 'Password123!', 'User Two');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        // Register a user
        $this->authService->register('test@example.com', 'CorrectPassword123!', 'Test User');

        // Logout
        $this->authService->logout();

        // Try to login with wrong password
        $this->expectException(\App\Exceptions\AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authService->login('test@example.com', 'WrongPassword123!');
    }

    public function testLoginWithNonexistentUser(): void
    {
        $this->expectException(\App\Exceptions\AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authService->login('nonexistent@example.com', 'Password123!');
    }

    public function testSessionPersistenceAcrossRequests(): void
    {
        // Register and login
        $email = 'session@example.com';
        $password = 'SessionTest123!';

        $this->authService->register($email, $password, 'Session User');

        // Verify user is logged in
        $this->assertTrue($this->authService->isLoggedIn());
        $user1 = $this->authService->getCurrentUser();

        // Simulate a new request (user data should persist in session)
        $user2 = $this->authService->getCurrentUser();

        $this->assertEquals($user1, $user2);
        $this->assertEquals($email, $user2['email']);
    }

    public function testPasswordHashing(): void
    {
        // Register a user
        $password = 'TestPassword123!';
        $this->authService->register('hash@example.com', $password, 'Hash Test');

        // Fetch user directly from database
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => 'hash@example.com']);
        $result = $stmt->fetch();

        // Verify password is hashed
        $this->assertNotEquals($password, $result['password_hash']);

        // Verify hash is Argon2ID
        $passwordInfo = password_get_info($result['password_hash']);
        $this->assertEquals('argon2id', $passwordInfo['algoName']);

        // Verify password verification works
        $this->assertTrue(password_verify($password, $result['password_hash']));
    }

    public function testRoleAssignment(): void
    {
        // Default role should be 'customer'
        $user = $this->authService->register('customer@example.com', 'Password123!', 'Customer');
        $this->assertEquals('customer', $user['role']);

        // Manually create an admin user (this would be done via database seeder in production)
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, name, role)
             VALUES (:email, :password_hash, :name, :role)
             RETURNING id, email, name, role'
        );

        $stmt->execute([
            'email' => 'admin@example.com',
            'password_hash' => password_hash('AdminPass123!', PASSWORD_ARGON2ID),
            'name' => 'Admin User',
            'role' => 'admin'
        ]);

        // Logout current user
        $this->authService->logout();

        // Login as admin
        $this->authService->login('admin@example.com', 'AdminPass123!');

        // Verify admin role
        $adminUser = $this->authService->getCurrentUser();
        $this->assertEquals('admin', $adminUser['role']);
    }

    public function testMultipleSessionsIsolation(): void
    {
        // Register two users
        $user1 = $this->authService->register('user1@example.com', 'Password123!', 'User One');

        // Store user1 session data
        $session1 = $_SESSION;

        // Logout
        $this->authService->logout();

        // Register and login as user2
        $user2 = $this->authService->register('user2@example.com', 'Password123!', 'User Two');

        // Verify user2 is logged in
        $currentUser = $this->authService->getCurrentUser();
        $this->assertEquals('user2@example.com', $currentUser['email']);
        $this->assertNotEquals('user1@example.com', $currentUser['email']);
    }

    public function testLogoutClearsAllSessionData(): void
    {
        // Register and login
        $this->authService->register('logout@example.com', 'Password123!', 'Logout Test');

        // Add some extra session data
        $_SESSION['cart_id'] = 'test-cart-123';
        $_SESSION['custom_data'] = 'some value';

        // Verify session has data
        $this->assertNotEmpty($_SESSION);

        // Logout
        $this->authService->logout();

        // Verify all session data is cleared
        $this->assertEmpty($_SESSION);
    }
}
