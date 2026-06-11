<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class DiaryRepository extends Repository
{
    public function countUserEntries(int $userId): int
    {
        $query = $this->connection()->prepare(
            'SELECT COUNT(*)
             FROM diary_entries
             WHERE user_id = :user_id'
        );
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function getUserEntries(int $userId, int $limit = 30): array
    {
        $query = $this->connection()->prepare(
            "SELECT
                de.id AS log_id,
                de.user_id,
                de.film_id,
                de.watched_on,
                de.rating AS log_rating,
                de.notes,
                de.is_rewatch,
                de.is_public,
                de.created_at AS logged_at,
                f.title AS film_title,
                f.release_year,
                f.director,
                f.description AS film_description,
                f.poster_url,
                f.poster_path,
                f.runtime_minutes,
                COALESCE(string_agg(DISTINCT g.name, ' • ' ORDER BY g.name), '') AS genres_text,
                r.id AS review_id,
                r.title AS review_title,
                r.content AS review_content,
                r.rating AS review_rating,
                r.created_at AS review_created_at,
                CASE
                    WHEN r.id IS NOT NULL THEN '/review-details?id=' || r.id
                    ELSE '/log-details?id=' || de.id
                END AS target_url
             FROM diary_entries de
             JOIN films f ON f.id = de.film_id
             LEFT JOIN reviews r ON r.user_id = de.user_id
                AND r.film_id = de.film_id
                AND r.is_public = TRUE
             LEFT JOIN film_genres fg ON fg.film_id = f.id
             LEFT JOIN genres g ON g.id = fg.genre_id
             WHERE de.user_id = :user_id
             GROUP BY de.id, f.id, r.id
             ORDER BY de.watched_on DESC, de.created_at DESC
             LIMIT :limit"
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getEntryForUser(int $logId, int $userId): ?array
    {
        $query = $this->connection()->prepare(
            "SELECT
                de.id AS log_id,
                de.user_id,
                de.film_id,
                de.watched_on,
                de.rating AS log_rating,
                de.notes,
                de.is_rewatch,
                de.is_public,
                de.created_at AS logged_at,
                u.username,
                u.avatar_url,
                f.title AS film_title,
                f.original_title,
                f.release_year,
                f.director,
                f.description AS film_description,
                f.poster_url,
                f.poster_path,
                f.backdrop_url,
                f.runtime_minutes,
                f.tmdb_vote_average,
                f.tmdb_vote_count,
                COALESCE(string_agg(DISTINCT g.name, ' • ' ORDER BY g.name), '') AS genres_text,
                r.id AS review_id,
                r.title AS review_title,
                r.content AS review_content,
                r.rating AS review_rating,
                r.created_at AS review_created_at
             FROM diary_entries de
             JOIN users u ON u.id = de.user_id
             JOIN films f ON f.id = de.film_id
             LEFT JOIN reviews r ON r.user_id = de.user_id
                AND r.film_id = de.film_id
                AND r.is_public = TRUE
             LEFT JOIN film_genres fg ON fg.film_id = f.id
             LEFT JOIN genres g ON g.id = fg.genre_id
             WHERE de.id = :log_id
               AND de.user_id = :user_id
             GROUP BY de.id, u.id, f.id, r.id
             LIMIT 1"
        );
        $query->bindValue(':log_id', $logId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $entry = $query->fetch();

        return $entry ?: null;
    }

    public function deleteLogForUser(int $logId, int $userId): bool
    {
        $query = $this->connection()->prepare(
            'DELETE FROM diary_entries
             WHERE id = :log_id
               AND user_id = :user_id'
        );
        $query->bindValue(':log_id', $logId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->rowCount() > 0;
    }


}
