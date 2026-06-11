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
            'UPDATE users SET username = :username, bio = :bio, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );

        return $query->execute([
            'id' => $id,
            'username' => $username,
            'bio' => $bio,
        ]);
    }

    public function deleteUser(int $id): bool
    {
        return $this->setBlocked($id, true);
    }

    public function getFollowStats(int $userId): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM followers WHERE followed_id = :user_id) AS followers_count,
                (SELECT COUNT(*) FROM followers WHERE follower_id = :user_id) AS following_count'
        );
        $query->execute(['user_id' => $userId]);
        $stats = $query->fetch() ?: [];

        return [
            'followers_count' => (int) ($stats['followers_count'] ?? 0),
            'following_count' => (int) ($stats['following_count'] ?? 0),
        ];
    }

    public function countFollowers(int $userId): int
    {
        $query = $this->connection()->prepare('SELECT COUNT(*) FROM followers WHERE followed_id = :user_id');
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function countFollowing(int $userId): int
    {
        $query = $this->connection()->prepare('SELECT COUNT(*) FROM followers WHERE follower_id = :user_id');
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function getFollowers(int $userId, int $currentUserId, int $limit = 10, int $offset = 0): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.avatar_url,
                u.bio,
                f.created_at AS followed_at,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM followers mine
                    WHERE mine.follower_id = :current_user_id
                      AND mine.followed_id = u.id
                ) THEN 1 ELSE 0 END AS is_following
             FROM followers f
             JOIN users u ON u.id = f.follower_id
             WHERE f.followed_id = :user_id
               AND u.is_active = TRUE
             ORDER BY f.created_at DESC, u.username ASC
             LIMIT :limit OFFSET :offset'
        );

        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getFollowing(int $userId, int $currentUserId, int $limit = 10, int $offset = 0): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.avatar_url,
                u.bio,
                f.created_at AS followed_at,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM followers mine
                    WHERE mine.follower_id = :current_user_id
                      AND mine.followed_id = u.id
                ) THEN 1 ELSE 0 END AS is_following
             FROM followers f
             JOIN users u ON u.id = f.followed_id
             WHERE f.follower_id = :user_id
               AND u.is_active = TRUE
             ORDER BY f.created_at DESC, u.username ASC
             LIMIT :limit OFFSET :offset'
        );

        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function isFollowing(int $followerId, int $followedId): bool
    {
        $query = $this->connection()->prepare(
            'SELECT EXISTS (
                SELECT 1 FROM followers
                WHERE follower_id = :follower_id AND followed_id = :followed_id
            )'
        );
        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return (bool) $query->fetchColumn();
    }

    public function followUser(int $followerId, int $followedId): bool
    {
        if ($followerId === $followedId) {
            return false;
        }

        $query = $this->connection()->prepare(
            'INSERT INTO followers (follower_id, followed_id)
             VALUES (:follower_id, :followed_id)
             ON CONFLICT (follower_id, followed_id) DO NOTHING'
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return true;
    }

    public function unfollowUser(int $followerId, int $followedId): bool
    {
        if ($followerId === $followedId) {
            return false;
        }

        $query = $this->connection()->prepare(
            'DELETE FROM followers
             WHERE follower_id = :follower_id AND followed_id = :followed_id'
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return true;
    }


}
