<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\UserRepositoryInterface;
use PDO;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, uuid, email, password_hash, first_name, last_name,
                    phone, role, email_verified_at, is_active, created_at,
                    updated_at, last_login_at
             FROM users
             WHERE id = :id AND is_active = true'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, uuid, email, password_hash, first_name, last_name,
                    phone, role, email_verified_at, is_active, created_at,
                    updated_at, last_login_at
             FROM users
             WHERE email = :email'
        );

        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, uuid, email, password_hash, first_name, last_name,
                    phone, role, email_verified_at, is_active, created_at,
                    updated_at, last_login_at
             FROM users
             WHERE uuid = :uuid AND is_active = true'
        );

        $stmt->execute(['uuid' => $uuid]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (
                email, password_hash, first_name, last_name, phone, role
            ) VALUES (
                :email, :password_hash, :first_name, :last_name, :phone, :role
            ) RETURNING id'
        );

        $stmt->execute([
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? 'customer'
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->db->prepare('UPDATE users SET is_active = false WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }
}
