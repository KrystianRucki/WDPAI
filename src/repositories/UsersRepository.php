<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class UsersRepository extends Repository
{
    public function getUserById(int $id): ?array
    {
        $query = $this->connection()->prepare('SELECT * FROM users WHERE id = :id');
        $query->execute(['id' => $id]);
        $user = $query->fetch();
        return $user ?: null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $query = $this->connection()->prepare('SELECT * FROM users WHERE email = :email');
        $query->execute(['email' => $email]);
        $user = $query->fetch();
        return $user ?: null;
    }

    public function getUserByUsername(string $username): ?array
    {
        $query = $this->connection()->prepare('SELECT * FROM users WHERE username = :username');
        $query->execute(['username' => $username]);
        $user = $query->fetch();
        return $user ?: null;
    }

    public function createUser(string $username, string $email, string $hashedPassword, string $role = 'user'): int
    {
        $query = $this->connection()->prepare(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (:username, :email, :password, :role, true) RETURNING id'
        );

        $query->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role,
        ]);

        return (int) $query->fetchColumn();
    }

    public function searchUsers(string $searchTerm): array
    {
        $query = $this->connection()->prepare(
            'SELECT id, username, email, role, is_active, created_at FROM users
             WHERE username ILIKE :search OR email ILIKE :search
             ORDER BY created_at DESC'
        );

        $query->execute(['search' => '%' . $searchTerm . '%']);
        return $query->fetchAll();
    }

    public function listPaginated(string $search, int $limit, int $offset): array
    {
        $query = $this->connection()->prepare(
            'SELECT id, username, email, role, is_active, created_at
             FROM users
             WHERE username ILIKE :search OR email ILIKE :search
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset'
        );

        $query->bindValue(':search', '%' . $search . '%');
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    public function countUsers(string $search = ''): int
    {
        $query = $this->connection()->prepare(
            'SELECT COUNT(*) FROM users
             WHERE username ILIKE :search OR email ILIKE :search'
        );
        $query->execute(['search' => '%' . $search . '%']);
        return (int) $query->fetchColumn();
    }

    public function countActiveUsers(): int
    {
        return (int) $this->connection()->query('SELECT COUNT(*) FROM users WHERE is_active = true')->fetchColumn();
    }

    public function countBlockedUsers(): int
    {
        return (int) $this->connection()->query('SELECT COUNT(*) FROM users WHERE is_active = false')->fetchColumn();
    }

    public function setBlocked(int $id, bool $blocked): bool
    {
        $query = $this->connection()->prepare('UPDATE users SET is_active = :active WHERE id = :id');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':active', !$blocked, PDO::PARAM_BOOL);
        $query->execute();

        return $query->rowCount() > 0;
    }

    public function updateProfile(int $id, string $username, string $bio): bool
    {
        $query = $this->connection()->prepare(
            'UPDATE users SET username = :username, bio = :bio WHERE id = :id'
        );
        $query->execute([
            'id' => $id,
            'username' => $username,
            'bio' => $bio,
        ]);

        return $query->rowCount() > 0;
    }

    public function deleteUser(int $id): bool
    {
        return $this->setBlocked($id, true);
    }
}
