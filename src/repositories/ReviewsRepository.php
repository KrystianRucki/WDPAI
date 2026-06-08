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
}
