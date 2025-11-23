<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Helpers\SessionHelper;
use App\Helpers\ValidationHelper;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Register a new user
     */
    public function register(array $data): int
    {
        try {
            // Validate input
            $email = ValidationHelper::email($data['email']);
            $password = ValidationHelper::password($data['password']);
            $firstName = ValidationHelper::name($data['first_name'] ?? '');
            $lastName = ValidationHelper::name($data['last_name'] ?? '');

            // Check if email already exists
            if ($this->userRepository->emailExists($email)) {
                throw new ValidationException('Email already registered');
            }

            // Verify password confirmation
            if ($password !== ($data['password_confirmation'] ?? '')) {
                throw new ValidationException('Passwords do not match');
            }

            // Hash password using Argon2ID (most secure as of 2025)
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ]);

            // Create user
            $userId = $this->userRepository->create([
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $data['phone'] ?? null,
                'role' => 'customer'
            ]);

            $this->logger->info('New user registered', [
                'user_id' => $userId,
                'email' => $email
            ]);

            return $userId;

        } catch (ValidationException $e) {
            $this->logger->warning('Registration validation failed', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Registration failed', [
                'error' => $e->getMessage()
            ]);
            throw new ValidationException('Registration failed. Please try again.');
        }
    }

    /**
     * Authenticate user with email and password
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        try {
            // Find user by email
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                throw new AuthenticationException('Invalid credentials');
            }

            // Check if account is active
            if (!$user['is_active']) {
                throw new AuthenticationException('Account is deactivated');
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logger->warning('Failed login attempt', [
                    'email' => $email
                ]);
                throw new AuthenticationException('Invalid credentials');
            }

            // Check if password needs rehashing (algorithm upgraded)
            if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
                $newHash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'threads' => 1
                ]);
                $this->userRepository->update($user['id'], ['password_hash' => $newHash]);
            }

            // Regenerate session ID to prevent session fixation
            SessionHelper::regenerate();

            // Set session data
            SessionHelper::setUser($user);

            // Update last login timestamp
            $this->userRepository->updateLastLogin($user['id']);

            $this->logger->info('User logged in', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            // Remove sensitive data before returning
            unset($user['password_hash']);

            return $user;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Login error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new AuthenticationException('Login failed. Please try again.');
        }
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $userId = SessionHelper::getUserId();

        if ($userId) {
            $this->logger->info('User logged out', ['user_id' => $userId]);
        }

        SessionHelper::clearUser();
        SessionHelper::destroy();
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return SessionHelper::isLoggedIn();
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array
    {
        $userId = SessionHelper::getUserId();

        if (!$userId) {
            return null;
        }

        $user = $this->userRepository->findById($userId);

        if ($user) {
            unset($user['password_hash']);
        }

        return $user;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        $user = SessionHelper::getUser();
        return $user && $user['role'] === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
