<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class ActivityRepository extends Repository
{
    public function getFollowingActivity(int $userId, int $limit = 30): array
    {
        return $this->activity($userId, true, $limit);
    }

    public function getUserActivity(int $userId, int $limit = 30): array
    {
        return $this->activity($userId, false, $limit);
    }

    private function activity(int $userId, bool $followingOnly, int $limit): array
    {
        $limit = max(1, min(100, $limit));

        $diaryScope = $followingOnly
            ? 'EXISTS (
                    SELECT 1
                    FROM followers fl
                    WHERE fl.follower_id = :user_id
                      AND fl.followed_id = de.user_id
                )
               AND de.is_public = TRUE'
            : 'de.user_id = :user_id';

        $listsScope = $followingOnly
            ? 'EXISTS (
                    SELECT 1
                    FROM followers fl
                    WHERE fl.follower_id = :user_id
                      AND fl.followed_id = l.user_id
                )
               AND l.is_public = TRUE'
            : 'l.user_id = :user_id';

        $reviewsScope = $followingOnly
            ? 'EXISTS (
                    SELECT 1
                    FROM followers fl
                    WHERE fl.follower_id = :user_id
                      AND fl.followed_id = r.user_id
                )
               AND r.is_public = TRUE'
            : 'r.user_id = :user_id';

        $query = $this->connection()->prepare(
            "SELECT *
             FROM (
                SELECT
                    de.id AS activity_id,
                    'logged'::TEXT AS activity_type,
                    de.created_at AS activity_at,
                    de.user_id,
                    u.username,
                    u.avatar_url,
                    f.id AS film_id,
                    f.title AS film_title,
                    f.release_year,
                    f.poster_url,
                    f.poster_path,
                    de.rating AS rating,
                    de.is_rewatch,
                    de.watched_on,
                    NULL::INTEGER AS list_id,
                    NULL::TEXT AS list_title,
                    NULL::TEXT AS list_description,
                    FALSE AS is_ranked,
                    0::INTEGER AS films_count,
                    NULL::INTEGER AS review_id,
                    NULL::TEXT AS review_title,
                    NULL::TEXT AS review_content,
                    NULL::TEXT AS poster_1,
                    NULL::TEXT AS poster_2,
                    NULL::TEXT AS poster_3,
                    ('/log-details?id=' || de.id)::TEXT AS target_url
                 FROM diary_entries de
                 JOIN users u ON u.id = de.user_id
                 JOIN films f ON f.id = de.film_id
                 WHERE {$diaryScope}
                   AND u.is_active = TRUE

                UNION ALL

                SELECT
                    l.id AS activity_id,
                    'list_created'::TEXT AS activity_type,
                    l.created_at AS activity_at,
                    l.user_id,
                    u.username,
                    u.avatar_url,
                    NULL::INTEGER AS film_id,
                    NULL::TEXT AS film_title,
                    NULL::SMALLINT AS release_year,
                    NULL::TEXT AS poster_url,
                    NULL::TEXT AS poster_path,
                    NULL::NUMERIC(2,1) AS rating,
                    FALSE AS is_rewatch,
                    NULL::DATE AS watched_on,
                    l.id AS list_id,
                    l.title AS list_title,
                    l.description AS list_description,
                    l.is_ranked,
                    (
                        SELECT COUNT(*)
                        FROM list_items li_count
                        WHERE li_count.list_id = l.id
                    )::INTEGER AS films_count,
                    NULL::INTEGER AS review_id,
                    NULL::TEXT AS review_title,
                    NULL::TEXT AS review_content,
                    (
                        SELECT f1.poster_url
                        FROM list_items li1
                        JOIN films f1 ON f1.id = li1.film_id
                        WHERE li1.list_id = l.id
                        ORDER BY li1.position ASC, li1.added_at ASC
                        LIMIT 1 OFFSET 0
                    ) AS poster_1,
                    (
                        SELECT f2.poster_url
                        FROM list_items li2
                        JOIN films f2 ON f2.id = li2.film_id
                        WHERE li2.list_id = l.id
                        ORDER BY li2.position ASC, li2.added_at ASC
                        LIMIT 1 OFFSET 1
                    ) AS poster_2,
                    (
                        SELECT f3.poster_url
                        FROM list_items li3
                        JOIN films f3 ON f3.id = li3.film_id
                        WHERE li3.list_id = l.id
                        ORDER BY li3.position ASC, li3.added_at ASC
                        LIMIT 1 OFFSET 2
                    ) AS poster_3,
                    ('/list-details?id=' || l.id)::TEXT AS target_url
                 FROM lists l
                 JOIN users u ON u.id = l.user_id
                 WHERE {$listsScope}
                   AND u.is_active = TRUE

                UNION ALL

                SELECT
                    r.id AS activity_id,
                    'reviewed'::TEXT AS activity_type,
                    r.created_at AS activity_at,
                    r.user_id,
                    u.username,
                    u.avatar_url,
                    f.id AS film_id,
                    f.title AS film_title,
                    f.release_year,
                    f.poster_url,
                    f.poster_path,
                    r.rating,
                    FALSE AS is_rewatch,
                    NULL::DATE AS watched_on,
                    NULL::INTEGER AS list_id,
                    NULL::TEXT AS list_title,
                    NULL::TEXT AS list_description,
                    FALSE AS is_ranked,
                    0::INTEGER AS films_count,
                    r.id AS review_id,
                    r.title AS review_title,
                    r.content AS review_content,
                    NULL::TEXT AS poster_1,
                    NULL::TEXT AS poster_2,
                    NULL::TEXT AS poster_3,
                    ('/review-details?id=' || r.id)::TEXT AS target_url
                 FROM reviews r
                 JOIN users u ON u.id = r.user_id
                 JOIN films f ON f.id = r.film_id
                 WHERE {$reviewsScope}
                   AND u.is_active = TRUE
             ) activities
             ORDER BY activity_at DESC, activity_id DESC
             LIMIT :limit"
        );

        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }
}
