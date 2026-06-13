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


    public function updateAvatar(int $id, string $avatarUrl): bool
    {
        $query = $this->connection()->prepare(
            'UPDATE users
             SET avatar_url = :avatar_url,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $query->execute([
            'id' => $id,
            'avatar_url' => $avatarUrl,
        ]);
    }


    public function getNotificationSettings(int $userId): array
    {
        $this->ensureNotificationSettingsSchema();

        $query = $this->connection()->prepare(
            'SELECT new_followers, review_likes, review_comments
             FROM user_notification_settings
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $query->execute(['user_id' => $userId]);
        $settings = $query->fetch();

        if (!$settings) {
            return [
                'new_followers' => true,
                'review_likes' => true,
                'review_comments' => true,
            ];
        }

        return [
            'new_followers' => (bool) $settings['new_followers'],
            'review_likes' => (bool) $settings['review_likes'],
            'review_comments' => (bool) $settings['review_comments'],
        ];
    }

    public function updateNotificationSettings(
        int $userId,
        bool $newFollowers,
        bool $reviewLikes,
        bool $reviewComments
    ): array {
        $this->ensureNotificationSettingsSchema();

        $query = $this->connection()->prepare(
            'INSERT INTO user_notification_settings (user_id, new_followers, review_likes, review_comments, updated_at)
             VALUES (:user_id, :new_followers, :review_likes, :review_comments, CURRENT_TIMESTAMP)
             ON CONFLICT (user_id) DO UPDATE
             SET new_followers = EXCLUDED.new_followers,
                 review_likes = EXCLUDED.review_likes,
                 review_comments = EXCLUDED.review_comments,
                 updated_at = CURRENT_TIMESTAMP'
        );

        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':new_followers', $newFollowers, PDO::PARAM_BOOL);
        $query->bindValue(':review_likes', $reviewLikes, PDO::PARAM_BOOL);
        $query->bindValue(':review_comments', $reviewComments, PDO::PARAM_BOOL);
        $query->execute();

        return $this->getNotificationSettings($userId);
    }

    private function ensureNotificationSettingsSchema(): void
    {
        $connection = $this->connection();

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS user_notification_settings (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                new_followers BOOLEAN NOT NULL DEFAULT TRUE,
                review_likes BOOLEAN NOT NULL DEFAULT TRUE,
                review_comments BOOLEAN NOT NULL DEFAULT TRUE,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $connection->exec(
            "CREATE OR REPLACE FUNCTION notify_review_interaction()
             RETURNS TRIGGER AS $$
             DECLARE
                 recipient_id INTEGER;
                 should_notify BOOLEAN;
             BEGIN
                 SELECT user_id INTO recipient_id
                 FROM reviews
                 WHERE id = NEW.review_id;

                 IF recipient_id IS NOT NULL AND recipient_id <> NEW.user_id THEN
                     IF TG_TABLE_NAME = 'review_comments' THEN
                         SELECT COALESCE((
                             SELECT review_comments
                             FROM user_notification_settings
                             WHERE user_id = recipient_id
                         ), TRUE) INTO should_notify;

                         IF should_notify THEN
                             INSERT INTO notifications(user_id, actor_id, type, review_id, comment_id)
                             VALUES (
                                 recipient_id,
                                 NEW.user_id,
                                 'review_comment',
                                 NEW.review_id,
                                 NEW.id
                             );
                         END IF;
                     ELSE
                         SELECT COALESCE((
                             SELECT review_likes
                             FROM user_notification_settings
                             WHERE user_id = recipient_id
                         ), TRUE) INTO should_notify;

                         IF should_notify THEN
                             INSERT INTO notifications(user_id, actor_id, type, review_id, comment_id)
                             VALUES (
                                 recipient_id,
                                 NEW.user_id,
                                 'review_like',
                                 NEW.review_id,
                                 NULL
                             );
                         END IF;
                     END IF;
                 END IF;

                 RETURN NEW;
             END;
             $$ LANGUAGE plpgsql"
        );
    }

    public function deleteUser(int $id): bool
    {
        return $this->setBlocked($id, true);
    }

    public function getFollowStats(int $userId): array
    {
        $this->ensureUserFilmsTable();

        $query = $this->connection()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM followers WHERE followed_id = :user_id) AS followers_count,
                (SELECT COUNT(*) FROM followers WHERE follower_id = :user_id) AS following_count,
                (
                    SELECT COUNT(*)
                    FROM (
                        SELECT film_id FROM user_films WHERE user_id = :user_id
                        UNION
                        SELECT film_id FROM diary_entries WHERE user_id = :user_id
                    ) watched_films
                ) AS films_count,
                (SELECT COUNT(*) FROM lists WHERE user_id = :user_id) AS lists_count,
                (SELECT COUNT(*) FROM reviews WHERE user_id = :user_id) AS reviews_count,
                (SELECT COUNT(*) FROM watchlist WHERE user_id = :user_id) AS watchlist_count'
        );
        $query->execute(['user_id' => $userId]);
        $stats = $query->fetch() ?: [];

        return [
            'followers_count' => (int) ($stats['followers_count'] ?? 0),
            'following_count' => (int) ($stats['following_count'] ?? 0),
            'films_count' => (int) ($stats['films_count'] ?? 0),
            'lists_count' => (int) ($stats['lists_count'] ?? 0),
            'reviews_count' => (int) ($stats['reviews_count'] ?? 0),
            'watchlist_count' => (int) ($stats['watchlist_count'] ?? 0),
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

        $this->ensureNotificationSettingsSchema();

        $connection = $this->connection();
        $query = $connection->prepare(
            'INSERT INTO followers (follower_id, followed_id)
             VALUES (:follower_id, :followed_id)
             ON CONFLICT (follower_id, followed_id) DO NOTHING
             RETURNING 1'
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        $inserted = (bool) $query->fetchColumn();

        if ($inserted) {
            $notification = $connection->prepare(
                "INSERT INTO notifications (user_id, actor_id, type)
                 SELECT :user_id, :actor_id, 'new_follower'
                 WHERE COALESCE((
                    SELECT new_followers
                    FROM user_notification_settings
                    WHERE user_id = :user_id
                 ), TRUE)"
            );
            $notification->execute([
                'user_id' => $followedId,
                'actor_id' => $followerId,
            ]);
        }

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



    public function searchPublicUsers(string $term, int $currentUserId, int $limit = 20): array
    {
        $this->ensureUserFilmsTable();

        $term = trim($term);
        $like = '%' . $term . '%';

        $query = $this->connection()->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.avatar_url,
                u.bio,
                u.role,
                u.is_active,
                u.created_at,
                (SELECT COUNT(*) FROM followers WHERE followed_id = u.id) AS followers_count,
                (SELECT COUNT(*) FROM followers WHERE follower_id = u.id) AS following_count,
                (
                    SELECT COUNT(*)
                    FROM (
                        SELECT film_id FROM user_films WHERE user_id = u.id
                        UNION
                        SELECT film_id FROM diary_entries WHERE user_id = u.id
                    ) watched_films
                ) AS films_count,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM followers mine
                    WHERE mine.follower_id = :current_user_id
                      AND mine.followed_id = u.id
                ) THEN 1 ELSE 0 END AS is_following
             FROM users u
             WHERE u.is_active = TRUE
               AND (
                    :term_empty = TRUE
                    OR u.username ILIKE :like_username
                    OR u.email ILIKE :like_email
                    OR u.bio ILIKE :like_bio
               )
             ORDER BY followers_count DESC, u.created_at DESC, u.username ASC
             LIMIT :limit'
        );
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->bindValue(':term_empty', $term === '', PDO::PARAM_BOOL);
        $query->bindValue(':like_username', $like);
        $query->bindValue(':like_email', $like);
        $query->bindValue(':like_bio', $like);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }


    private function ensureUserFilmsTable(): void
    {
        $this->connection()->exec(
            'CREATE TABLE IF NOT EXISTS user_films (
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                film_id INTEGER NOT NULL REFERENCES films(id) ON DELETE CASCADE,
                added_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, film_id)
            )'
        );
    }


}
