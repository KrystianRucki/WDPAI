<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class NotificationsRepository extends Repository
{
    public function forUser(int $userId, int $limit = 30): array
    {
        $query = $this->connection()->prepare(
            'SELECT n.*, actor.username AS actor_username, actor.username AS actor_name, f.title AS film_title
             FROM notifications n
             LEFT JOIN users actor ON actor.id = n.actor_id
             LEFT JOIN reviews r ON r.id = n.review_id
             LEFT JOIN films f ON f.id = r.film_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    public function markRead(int $userId, ?int $notificationId = null): void
    {
        if ($notificationId === null) {
            $query = $this->connection()->prepare('UPDATE notifications SET is_read = true WHERE user_id = :user_id');
            $query->execute(['user_id' => $userId]);
            return;
        }

        $query = $this->connection()->prepare('UPDATE notifications SET is_read = true WHERE user_id = :user_id AND id = :id');
        $query->execute(['user_id' => $userId, 'id' => $notificationId]);
    }
}
