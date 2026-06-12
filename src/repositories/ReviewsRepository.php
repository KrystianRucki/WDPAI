<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class ReviewsRepository extends Repository
{
    public function feed(int $limit = 20): array
    {
        $query = $this->connection()->prepare('SELECT * FROM v_review_feed ORDER BY created_at DESC LIMIT :limit');
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    public function comment(int $reviewId, int $userId, string $content): int
    {
        $query = $this->connection()->prepare(
            'INSERT INTO review_comments (review_id, user_id, content) VALUES (:review_id, :user_id, :content) RETURNING id'
        );
        $query->execute([
            'review_id' => $reviewId,
            'user_id' => $userId,
            'content' => $content,
        ]);
        return (int) $query->fetchColumn();
    }

    public function toggleLike(int $reviewId, int $userId): bool
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $exists = $pdo->prepare('SELECT 1 FROM review_likes WHERE review_id = :review_id AND user_id = :user_id');
            $exists->execute(['review_id' => $reviewId, 'user_id' => $userId]);

            if ($exists->fetchColumn()) {
                $delete = $pdo->prepare('DELETE FROM review_likes WHERE review_id = :review_id AND user_id = :user_id');
                $delete->execute(['review_id' => $reviewId, 'user_id' => $userId]);
                $pdo->commit();
                return false;
            }

            $insert = $pdo->prepare('INSERT INTO review_likes (review_id, user_id) VALUES (:review_id, :user_id)');
            $insert->execute(['review_id' => $reviewId, 'user_id' => $userId]);
            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function countUserReviews(int $userId): int
    {
        $query = $this->connection()->prepare(
            'SELECT COUNT(*)
             FROM reviews
             WHERE user_id = :user_id'
        );
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function getUserReviews(int $userId, int $limit = 10): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                r.id AS review_id,
                r.title AS review_title,
                r.content,
                r.rating,
                r.created_at,
                r.updated_at,
                f.id AS film_id,
                f.title AS film_title,
                f.release_year,
                f.director,
                f.poster_url,
                f.poster_path,
                latest_log.log_id,
                latest_log.watched_on,
                COALESCE(latest_log.is_rewatch, FALSE) AS is_rewatch,
                COALESCE(COUNT(DISTINCT rl.user_id), 0) AS likes_count,
                COALESCE(COUNT(DISTINCT rc.id), 0) AS comments_count
             FROM reviews r
             JOIN films f ON f.id = r.film_id
             LEFT JOIN LATERAL (
                SELECT
                    de.id AS log_id,
                    de.watched_on,
                    de.is_rewatch
                FROM diary_entries de
                WHERE de.user_id = r.user_id
                  AND de.film_id = r.film_id
                ORDER BY de.watched_on DESC, de.created_at DESC
                LIMIT 1
             ) latest_log ON TRUE
             LEFT JOIN review_likes rl ON rl.review_id = r.id
             LEFT JOIN review_comments rc ON rc.review_id = r.id
             WHERE r.user_id = :user_id
             GROUP BY r.id, f.id, latest_log.log_id, latest_log.watched_on, latest_log.is_rewatch
             ORDER BY r.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }


}
