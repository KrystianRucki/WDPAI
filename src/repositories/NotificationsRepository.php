<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class NotificationsRepository extends Repository
{
    public function forUser(int $userId, int $limit = 50): array
    {
        $query = $this->connection()->prepare(
            "SELECT
                n.id,
                n.user_id,
                n.actor_id,
                n.type,
                n.review_id,
                n.comment_id,
                n.is_read,
                n.created_at,
                actor.username AS actor_username,
                actor.avatar_url AS actor_avatar_url,
                r.title AS review_title,
                f.id AS film_id,
                f.title AS film_title,
                rc.content AS comment_content,
                CASE
                    WHEN n.type = 'new_follower' AND n.actor_id IS NOT NULL
                        THEN '/profile-u-main?id=' || n.actor_id
                    WHEN n.type = 'review_like' AND n.review_id IS NOT NULL
                        THEN '/review-details?id=' || n.review_id
                    WHEN n.type = 'review_comment' AND n.review_id IS NOT NULL
                        THEN '/review-comments?id=' || n.review_id
                    ELSE '/notifications'
                END AS target_url
             FROM notifications n
             LEFT JOIN users actor ON actor.id = n.actor_id
             LEFT JOIN reviews r ON r.id = n.review_id
             LEFT JOIN films f ON f.id = r.film_id
             LEFT JOIN review_comments rc ON rc.id = n.comment_id
             LEFT JOIN user_notification_settings uns ON uns.user_id = n.user_id
             WHERE n.user_id = :user_id
               AND CASE n.type
                    WHEN 'new_follower' THEN COALESCE(uns.new_followers, TRUE)
                    WHEN 'review_like' THEN COALESCE(uns.review_likes, TRUE)
                    WHEN 'review_comment' THEN COALESCE(uns.review_comments, TRUE)
                    ELSE TRUE
               END
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit"
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function statsForUser(int $userId): array
    {
        $query = $this->connection()->prepare(
            "SELECT
                COUNT(*) FILTER (WHERE n.type = 'new_follower') AS followers_count,
                COUNT(*) FILTER (WHERE n.type = 'review_like') AS likes_count,
                COUNT(*) FILTER (WHERE n.type = 'review_comment') AS comments_count,
                COUNT(*) FILTER (
                    WHERE n.is_read = FALSE
                      AND CASE n.type
                            WHEN 'new_follower' THEN COALESCE(uns.new_followers, TRUE)
                            WHEN 'review_like' THEN COALESCE(uns.review_likes, TRUE)
                            WHEN 'review_comment' THEN COALESCE(uns.review_comments, TRUE)
                            ELSE TRUE
                          END
                ) AS unread_count
             FROM notifications n
             LEFT JOIN user_notification_settings uns ON uns.user_id = n.user_id
             WHERE n.user_id = :user_id"
        );
        $query->execute(['user_id' => $userId]);
        $stats = $query->fetch() ?: [];

        return [
            'followers_count' => (int) ($stats['followers_count'] ?? 0),
            'likes_count' => (int) ($stats['likes_count'] ?? 0),
            'comments_count' => (int) ($stats['comments_count'] ?? 0),
            'unread_count' => (int) ($stats['unread_count'] ?? 0),
        ];
    }

    public function markRead(int $userId, ?int $notificationId = null): int
    {
        if ($notificationId === null) {
            $query = $this->connection()->prepare(
                'UPDATE notifications
                 SET is_read = TRUE
                 WHERE user_id = :user_id
                   AND is_read = FALSE'
            );
            $query->execute(['user_id' => $userId]);

            return $query->rowCount();
        }

        $query = $this->connection()->prepare(
            'UPDATE notifications
             SET is_read = TRUE
             WHERE user_id = :user_id
               AND id = :id
               AND is_read = FALSE'
        );
        $query->execute([
            'user_id' => $userId,
            'id' => $notificationId,
        ]);

        return $query->rowCount();
    }
}
