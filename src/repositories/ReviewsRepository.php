<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class ReviewsRepository extends Repository
{
    public function feed(int $limit = 20, ?int $filmId = null): array
    {
        $this->ensureReviewCommentsSchema();

        $where = ['r.is_public = TRUE'];
        if ($filmId !== null && $filmId > 0) {
            $where[] = 'r.film_id = :film_id';
        }

        $query = $this->connection()->prepare(
            'SELECT
                r.id AS review_id,
                r.title AS review_title,
                r.content,
                r.rating,
                r.created_at,
                r.updated_at,
                u.id AS user_id,
                u.username,
                u.avatar_url,
                f.id AS film_id,
                f.title AS film_title,
                f.release_year,
                f.director,
                f.poster_url,
                f.backdrop_url,
                latest_log.watched_on,
                latest_log.created_at AS logged_at,
                COALESCE(latest_log.is_rewatch, FALSE) AS is_rewatch,
                COALESCE(COUNT(DISTINCT rl.user_id), 0) AS likes_count,
                COALESCE(COUNT(DISTINCT rc.id), 0) AS comments_count
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN films f ON f.id = r.film_id
             LEFT JOIN LATERAL (
                SELECT de.watched_on, de.created_at, de.is_rewatch
                FROM diary_entries de
                WHERE de.user_id = r.user_id
                  AND de.film_id = r.film_id
                ORDER BY de.watched_on DESC, de.created_at DESC
                LIMIT 1
             ) latest_log ON TRUE
             LEFT JOIN review_likes rl ON rl.review_id = r.id
             LEFT JOIN review_comments rc ON rc.review_id = r.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY r.id, u.id, f.id, latest_log.watched_on, latest_log.created_at, latest_log.is_rewatch
             ORDER BY r.created_at DESC, latest_log.watched_on DESC NULLS LAST, latest_log.created_at DESC NULLS LAST
             LIMIT :limit'
        );

        if ($filmId !== null && $filmId > 0) {
            $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getFilmReviews(int $filmId, int $limit = 2): array
    {
        $this->ensureReviewCommentsSchema();

        $query = $this->connection()->prepare(
            'SELECT
                r.id AS review_id,
                r.title AS review_title,
                r.content,
                r.rating,
                r.created_at,
                r.updated_at,
                u.id AS user_id,
                u.username,
                u.avatar_url,
                latest_log.watched_on,
                latest_log.created_at AS logged_at,
                COALESCE(COUNT(DISTINCT rl.user_id), 0) AS likes_count,
                COALESCE(COUNT(DISTINCT rc.id), 0) AS comments_count
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN LATERAL (
                SELECT de.watched_on, de.created_at
                FROM diary_entries de
                WHERE de.user_id = r.user_id
                  AND de.film_id = r.film_id
                ORDER BY de.watched_on DESC, de.created_at DESC
                LIMIT 1
             ) latest_log ON TRUE
             LEFT JOIN review_likes rl ON rl.review_id = r.id
             LEFT JOIN review_comments rc ON rc.review_id = r.id
             WHERE r.film_id = :film_id
               AND r.is_public = TRUE
             GROUP BY r.id, u.id, latest_log.watched_on, latest_log.created_at
             ORDER BY latest_log.watched_on DESC NULLS LAST, latest_log.created_at DESC NULLS LAST, r.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getReviewDetails(int $reviewId, int $currentUserId = 0): ?array
    {
        $this->ensureReviewCommentsSchema();

        $query = $this->connection()->prepare(
            'SELECT
                r.id AS review_id,
                r.title AS review_title,
                r.content,
                r.rating,
                r.created_at,
                r.updated_at,
                r.is_public,
                u.id AS user_id,
                u.username,
                u.avatar_url,
                f.id AS film_id,
                f.title AS film_title,
                f.release_year,
                f.director,
                f.poster_url,
                f.backdrop_url,
                latest_log.watched_on,
                latest_log.created_at AS logged_at,
                COALESCE(latest_log.is_rewatch, FALSE) AS is_rewatch,
                COALESCE(COUNT(DISTINCT rl.user_id), 0) AS likes_count,
                COALESCE(COUNT(DISTINCT rc.id), 0) AS comments_count,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM review_likes mine
                    WHERE mine.review_id = r.id
                      AND mine.user_id = :current_user_id
                ) THEN 1 ELSE 0 END AS liked_by_current_user
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN films f ON f.id = r.film_id
             LEFT JOIN LATERAL (
                SELECT de.watched_on, de.created_at, de.is_rewatch
                FROM diary_entries de
                WHERE de.user_id = r.user_id
                  AND de.film_id = r.film_id
                ORDER BY de.watched_on DESC, de.created_at DESC
                LIMIT 1
             ) latest_log ON TRUE
             LEFT JOIN review_likes rl ON rl.review_id = r.id
             LEFT JOIN review_comments rc ON rc.review_id = r.id
             WHERE r.id = :review_id
               AND r.is_public = TRUE
             GROUP BY r.id, u.id, f.id, latest_log.watched_on, latest_log.created_at, latest_log.is_rewatch'
        );
        $query->bindValue(':review_id', $reviewId, PDO::PARAM_INT);
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->execute();

        $review = $query->fetch();

        return $review ?: null;
    }

    public function getReviewComments(int $reviewId, int $currentUserId = 0): array
    {
        $this->ensureReviewCommentsSchema();

        $query = $this->connection()->prepare(
            'SELECT
                rc.id,
                rc.review_id,
                rc.user_id,
                rc.parent_id,
                rc.content,
                rc.created_at,
                u.username,
                u.avatar_url,
                COALESCE(COUNT(DISTINCT rcl.user_id), 0) AS likes_count,
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM review_comment_likes mine
                    WHERE mine.comment_id = rc.id
                      AND mine.user_id = :current_user_id
                ) THEN 1 ELSE 0 END AS liked_by_current_user
             FROM review_comments rc
             JOIN users u ON u.id = rc.user_id
             LEFT JOIN review_comment_likes rcl ON rcl.comment_id = rc.id
             WHERE rc.review_id = :review_id
             GROUP BY rc.id, u.id
             ORDER BY rc.created_at DESC'
        );
        $query->bindValue(':review_id', $reviewId, PDO::PARAM_INT);
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function comment(int $reviewId, int $userId, string $content, ?int $parentId = null): int
    {
        $this->ensureReviewCommentsSchema();

        $query = $this->connection()->prepare(
            'INSERT INTO review_comments (review_id, user_id, parent_id, content)
             VALUES (:review_id, :user_id, :parent_id, :content)
             RETURNING id'
        );
        $query->bindValue(':review_id', $reviewId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':parent_id', $parentId ?: null, $parentId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $query->bindValue(':content', $content);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function toggleLike(int $reviewId, int $userId): array
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $exists = $pdo->prepare('SELECT 1 FROM review_likes WHERE review_id = :review_id AND user_id = :user_id');
            $exists->execute(['review_id' => $reviewId, 'user_id' => $userId]);

            if ($exists->fetchColumn()) {
                $delete = $pdo->prepare('DELETE FROM review_likes WHERE review_id = :review_id AND user_id = :user_id');
                $delete->execute(['review_id' => $reviewId, 'user_id' => $userId]);
                $liked = false;
            } else {
                $insert = $pdo->prepare('INSERT INTO review_likes (review_id, user_id) VALUES (:review_id, :user_id)');
                $insert->execute(['review_id' => $reviewId, 'user_id' => $userId]);
                $liked = true;
            }

            $count = $pdo->prepare('SELECT COUNT(*) FROM review_likes WHERE review_id = :review_id');
            $count->execute(['review_id' => $reviewId]);

            $pdo->commit();

            return [
                'liked' => $liked,
                'likes_count' => (int) $count->fetchColumn(),
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function toggleCommentLike(int $commentId, int $userId): array
    {
        $this->ensureReviewCommentsSchema();

        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $exists = $pdo->prepare('SELECT 1 FROM review_comment_likes WHERE comment_id = :comment_id AND user_id = :user_id');
            $exists->execute(['comment_id' => $commentId, 'user_id' => $userId]);

            if ($exists->fetchColumn()) {
                $delete = $pdo->prepare('DELETE FROM review_comment_likes WHERE comment_id = :comment_id AND user_id = :user_id');
                $delete->execute(['comment_id' => $commentId, 'user_id' => $userId]);
                $liked = false;
            } else {
                $insert = $pdo->prepare('INSERT INTO review_comment_likes (comment_id, user_id) VALUES (:comment_id, :user_id)');
                $insert->execute(['comment_id' => $commentId, 'user_id' => $userId]);
                $liked = true;
            }

            $count = $pdo->prepare('SELECT COUNT(*) FROM review_comment_likes WHERE comment_id = :comment_id');
            $count->execute(['comment_id' => $commentId]);

            $pdo->commit();

            return [
                'liked' => $liked,
                'likes_count' => (int) $count->fetchColumn(),
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }


    public function countPublicUserReviews(int $userId): int
    {
        $query = $this->connection()->prepare(
            'SELECT COUNT(*)
             FROM reviews
             WHERE user_id = :user_id
               AND is_public = TRUE'
        );
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function getPublicUserReviews(int $userId, int $limit = 10): array
    {
        $this->ensureReviewCommentsSchema();

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
               AND r.is_public = TRUE
             GROUP BY r.id, f.id, latest_log.log_id, latest_log.watched_on, latest_log.is_rewatch
             ORDER BY r.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
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

    private function ensureReviewCommentsSchema(): void
    {
        $this->connection()->exec(
            'ALTER TABLE review_comments
             ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES review_comments(id) ON DELETE CASCADE'
        );

        $this->connection()->exec(
            'CREATE TABLE IF NOT EXISTS review_comment_likes (
                comment_id INTEGER NOT NULL REFERENCES review_comments(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (comment_id, user_id)
            )'
        );
    }
}
