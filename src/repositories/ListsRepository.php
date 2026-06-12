<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class ListsRepository extends Repository
{
    public function create(int $userId, string $title, ?string $description = null, bool $isPublic = true, bool $isRanked = false): int
    {
        $query = $this->connection()->prepare(
            'INSERT INTO lists (user_id, title, description, is_public, is_ranked)
             VALUES (:user_id, :title, :description, :is_public, :is_ranked)
             RETURNING id'
        );

        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':title', $title);
        $query->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->bindValue(':is_public', $isPublic, PDO::PARAM_BOOL);
        $query->bindValue(':is_ranked', $isRanked, PDO::PARAM_BOOL);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function addFilm(int $listId, int $filmId, ?int $position = null): void
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            if ($position === null) {
                $positionQuery = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM list_items WHERE list_id = :list_id');
                $positionQuery->execute(['list_id' => $listId]);
                $position = (int) $positionQuery->fetchColumn();
            }

            $query = $pdo->prepare(
                'INSERT INTO list_items (list_id, film_id, position) VALUES (:list_id, :film_id, :position)
                 ON CONFLICT (list_id, film_id) DO UPDATE SET position = EXCLUDED.position'
            );
            $query->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
            $query->bindValue(':position', $position, PDO::PARAM_INT);
            $query->execute();

            $update = $pdo->prepare('UPDATE lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :list_id');
            $update->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $update->execute();

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function countUserLists(int $userId): int
    {
        $query = $this->connection()->prepare(
            'SELECT COUNT(*)
             FROM lists
             WHERE user_id = :user_id'
        );
        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function getUserLists(int $userId, int $limit = 10): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                l.id,
                l.user_id,
                l.title,
                l.description,
                l.is_public,
                l.is_ranked,
                l.created_at,
                l.updated_at,
                COUNT(li.film_id) AS films_count,
                p1.poster_url AS poster_1,
                p2.poster_url AS poster_2,
                p3.poster_url AS poster_3
             FROM lists l
             LEFT JOIN list_items li ON li.list_id = l.id
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li1
                JOIN films f ON f.id = li1.film_id
                WHERE li1.list_id = l.id
                ORDER BY li1.position ASC, li1.added_at ASC
                LIMIT 1 OFFSET 0
             ) p1 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li2
                JOIN films f ON f.id = li2.film_id
                WHERE li2.list_id = l.id
                ORDER BY li2.position ASC, li2.added_at ASC
                LIMIT 1 OFFSET 1
             ) p2 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li3
                JOIN films f ON f.id = li3.film_id
                WHERE li3.list_id = l.id
                ORDER BY li3.position ASC, li3.added_at ASC
                LIMIT 1 OFFSET 2
             ) p3 ON TRUE
             WHERE l.user_id = :user_id
             GROUP BY l.id, p1.poster_url, p2.poster_url, p3.poster_url
             ORDER BY l.updated_at DESC, l.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }



    public function getListDetails(int $listId, int $currentUserId): ?array
    {
        $query = $this->connection()->prepare(
            'SELECT
                l.id,
                l.user_id,
                l.title,
                l.description,
                l.is_public,
                l.is_ranked,
                l.created_at,
                l.updated_at,
                u.username,
                u.avatar_url,
                COUNT(li.film_id) AS films_count
             FROM lists l
             JOIN users u ON u.id = l.user_id
             LEFT JOIN list_items li ON li.list_id = l.id
             WHERE l.id = :list_id
               AND (l.is_public = TRUE OR l.user_id = :current_user_id)
             GROUP BY l.id, u.id
             LIMIT 1'
        );
        $query->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->execute();

        $list = $query->fetch();

        return $list ?: null;
    }

    public function getListItems(int $listId, int $currentUserId): array
    {
        $query = $this->connection()->prepare(
            "SELECT
                li.position,
                li.note,
                li.added_at,
                f.id AS film_id,
                f.tmdb_id,
                f.title,
                f.original_title,
                f.release_year,
                f.director,
                f.description,
                f.poster_url,
                f.poster_path,
                f.runtime_minutes,
                f.tmdb_vote_average,
                COALESCE(string_agg(DISTINCT g.name, ' • ' ORDER BY g.name), '') AS genres_text
             FROM list_items li
             JOIN lists l ON l.id = li.list_id
             JOIN films f ON f.id = li.film_id
             LEFT JOIN film_genres fg ON fg.film_id = f.id
             LEFT JOIN genres g ON g.id = fg.genre_id
             WHERE li.list_id = :list_id
               AND (l.is_public = TRUE OR l.user_id = :current_user_id)
             GROUP BY li.list_id, li.film_id, li.position, li.note, li.added_at, f.id
             ORDER BY li.position ASC, li.added_at ASC"
        );
        $query->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }



    public function getUserListsForFilm(int $userId, int $filmId): array
    {
        $query = $this->connection()->prepare(
            'SELECT
                l.id,
                l.user_id,
                l.title,
                l.description,
                l.is_public,
                l.is_ranked,
                l.created_at,
                l.updated_at,
                COUNT(li.film_id) AS films_count,
                EXISTS (
                    SELECT 1
                    FROM list_items selected_li
                    WHERE selected_li.list_id = l.id
                      AND selected_li.film_id = :film_id
                ) AS contains_film,
                p1.poster_url AS poster_1,
                p2.poster_url AS poster_2,
                p3.poster_url AS poster_3
             FROM lists l
             LEFT JOIN list_items li ON li.list_id = l.id
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li1
                JOIN films f ON f.id = li1.film_id
                WHERE li1.list_id = l.id
                ORDER BY li1.position ASC, li1.added_at ASC
                LIMIT 1 OFFSET 0
             ) p1 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li2
                JOIN films f ON f.id = li2.film_id
                WHERE li2.list_id = l.id
                ORDER BY li2.position ASC, li2.added_at ASC
                LIMIT 1 OFFSET 1
             ) p2 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li3
                JOIN films f ON f.id = li3.film_id
                WHERE li3.list_id = l.id
                ORDER BY li3.position ASC, li3.added_at ASC
                LIMIT 1 OFFSET 2
             ) p3 ON TRUE
             WHERE l.user_id = :user_id
             GROUP BY l.id, p1.poster_url, p2.poster_url, p3.poster_url
             ORDER BY contains_film DESC, l.updated_at DESC, l.created_at DESC'
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function addFilmToUserLists(int $userId, int $filmId, array $listIds): int
    {
        $listIds = array_values(array_unique(array_filter(array_map('intval', $listIds), fn (int $id): bool => $id > 0)));

        if (!$listIds) {
            return 0;
        }

        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $added = 0;

            foreach ($listIds as $listId) {
                $ownerQuery = $pdo->prepare('SELECT id FROM lists WHERE id = :list_id AND user_id = :user_id');
                $ownerQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $ownerQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $ownerQuery->execute();

                if (!$ownerQuery->fetchColumn()) {
                    continue;
                }

                $positionQuery = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM list_items WHERE list_id = :list_id');
                $positionQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $positionQuery->execute();
                $position = (int) $positionQuery->fetchColumn();

                $insert = $pdo->prepare(
                    'INSERT INTO list_items (list_id, film_id, position)
                     VALUES (:list_id, :film_id, :position)
                     ON CONFLICT (list_id, film_id) DO NOTHING'
                );
                $insert->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $insert->bindValue(':film_id', $filmId, PDO::PARAM_INT);
                $insert->bindValue(':position', $position, PDO::PARAM_INT);
                $insert->execute();

                if ($insert->rowCount() > 0) {
                    $added++;
                }

                $update = $pdo->prepare('UPDATE lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :list_id');
                $update->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $update->execute();
            }

            $pdo->commit();

            return $added;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }



    public function syncFilmUserLists(int $userId, int $filmId, array $selectedListIds): array
    {
        $selectedListIds = array_values(array_unique(array_filter(array_map('intval', $selectedListIds), fn (int $id): bool => $id > 0)));

        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $currentQuery = $pdo->prepare(
                'SELECT l.id
                 FROM lists l
                 JOIN list_items li ON li.list_id = l.id
                 WHERE l.user_id = :user_id
                   AND li.film_id = :film_id'
            );
            $currentQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $currentQuery->bindValue(':film_id', $filmId, PDO::PARAM_INT);
            $currentQuery->execute();

            $currentListIds = array_map('intval', array_column($currentQuery->fetchAll(), 'id'));
            $toRemove = array_values(array_diff($currentListIds, $selectedListIds));
            $toAdd = array_values(array_diff($selectedListIds, $currentListIds));
            $touchedListIds = [];

            foreach ($toRemove as $listId) {
                $delete = $pdo->prepare(
                    'DELETE FROM list_items
                     WHERE list_id = :list_id
                       AND film_id = :film_id
                       AND EXISTS (
                           SELECT 1 FROM lists
                           WHERE lists.id = :list_id
                             AND lists.user_id = :user_id
                       )'
                );
                $delete->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $delete->bindValue(':film_id', $filmId, PDO::PARAM_INT);
                $delete->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $delete->execute();

                if ($delete->rowCount() > 0) {
                    $touchedListIds[] = $listId;
                }
            }

            $added = 0;

            foreach ($toAdd as $listId) {
                $ownerQuery = $pdo->prepare('SELECT id FROM lists WHERE id = :list_id AND user_id = :user_id');
                $ownerQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $ownerQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $ownerQuery->execute();

                if (!$ownerQuery->fetchColumn()) {
                    continue;
                }

                $positionQuery = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM list_items WHERE list_id = :list_id');
                $positionQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $positionQuery->execute();
                $position = (int) $positionQuery->fetchColumn();

                $insert = $pdo->prepare(
                    'INSERT INTO list_items (list_id, film_id, position)
                     VALUES (:list_id, :film_id, :position)
                     ON CONFLICT (list_id, film_id) DO NOTHING'
                );
                $insert->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $insert->bindValue(':film_id', $filmId, PDO::PARAM_INT);
                $insert->bindValue(':position', $position, PDO::PARAM_INT);
                $insert->execute();

                if ($insert->rowCount() > 0) {
                    $added++;
                    $touchedListIds[] = $listId;
                }
            }

            foreach (array_unique($touchedListIds) as $listId) {
                $this->normalizeListPositions($pdo, (int) $listId);

                $update = $pdo->prepare('UPDATE lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :list_id AND user_id = :user_id');
                $update->bindValue(':list_id', (int) $listId, PDO::PARAM_INT);
                $update->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $update->execute();
            }

            $pdo->commit();

            return [
                'added_count' => $added,
                'removed_count' => count($toRemove),
                'selected_count' => count($selectedListIds),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function reorderRankedList(int $userId, int $listId, array $filmIds): int
    {
        $filmIds = array_values(array_unique(array_filter(array_map('intval', $filmIds), fn (int $id): bool => $id > 0)));

        if (!$filmIds) {
            return 0;
        }

        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $listQuery = $pdo->prepare('SELECT id FROM lists WHERE id = :list_id AND user_id = :user_id AND is_ranked = TRUE');
            $listQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $listQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $listQuery->execute();

            if (!$listQuery->fetchColumn()) {
                $pdo->rollBack();
                return 0;
            }

            $existingQuery = $pdo->prepare(
                'SELECT film_id
                 FROM list_items
                 WHERE list_id = :list_id
                 ORDER BY position ASC, added_at ASC'
            );
            $existingQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $existingQuery->execute();

            $existingIds = array_map('intval', array_column($existingQuery->fetchAll(), 'film_id'));

            sort($existingIds);
            $sortedSubmitted = $filmIds;
            sort($sortedSubmitted);

            if ($existingIds !== $sortedSubmitted) {
                $pdo->rollBack();
                return 0;
            }

            /*
             * list_items has:
             * - UNIQUE(list_id, position)
             * - CHECK(position > 0)
             *
             * Directly swapping positions can temporarily create duplicates.
             * Negative temporary positions are not allowed by the CHECK constraint.
             * So first move all items to high positive temporary positions,
             * then write final positions 1..N.
             */
            $maxPositionQuery = $pdo->prepare(
                'SELECT COALESCE(MAX(position), 0)
                 FROM list_items
                 WHERE list_id = :list_id'
            );
            $maxPositionQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $maxPositionQuery->execute();

            $temporaryBase = (int) $maxPositionQuery->fetchColumn() + count($filmIds) + 1000;

            $temporaryUpdate = $pdo->prepare(
                'UPDATE list_items
                 SET position = :position
                 WHERE list_id = :list_id
                   AND film_id = :film_id'
            );

            foreach ($filmIds as $index => $filmId) {
                $temporaryUpdate->bindValue(':position', $temporaryBase + $index + 1, PDO::PARAM_INT);
                $temporaryUpdate->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $temporaryUpdate->bindValue(':film_id', $filmId, PDO::PARAM_INT);
                $temporaryUpdate->execute();
            }

            $finalUpdate = $pdo->prepare(
                'UPDATE list_items
                 SET position = :position
                 WHERE list_id = :list_id
                   AND film_id = :film_id'
            );

            foreach ($filmIds as $index => $filmId) {
                $finalUpdate->bindValue(':position', $index + 1, PDO::PARAM_INT);
                $finalUpdate->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $finalUpdate->bindValue(':film_id', $filmId, PDO::PARAM_INT);
                $finalUpdate->execute();
            }

            $touch = $pdo->prepare('UPDATE lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :list_id AND user_id = :user_id');
            $touch->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $touch->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $touch->execute();

            $pdo->commit();

            return count($filmIds);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }


    public function removeFilmFromUserList(int $userId, int $listId, int $filmId): bool
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare(
                'DELETE FROM list_items
                 WHERE list_id = :list_id
                   AND film_id = :film_id
                   AND EXISTS (
                       SELECT 1 FROM lists
                       WHERE lists.id = :list_id
                         AND lists.user_id = :user_id
                   )'
            );
            $delete->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $delete->bindValue(':film_id', $filmId, PDO::PARAM_INT);
            $delete->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $delete->execute();

            $removed = $delete->rowCount() > 0;

            if ($removed) {
                $this->normalizeListPositions($pdo, $listId);

                $touch = $pdo->prepare('UPDATE lists SET updated_at = CURRENT_TIMESTAMP WHERE id = :list_id AND user_id = :user_id');
                $touch->bindValue(':list_id', $listId, PDO::PARAM_INT);
                $touch->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $touch->execute();
            }

            $pdo->commit();

            return $removed;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function normalizeListPositions(PDO $pdo, int $listId): void
    {
        $itemsQuery = $pdo->prepare(
            'SELECT film_id
             FROM list_items
             WHERE list_id = :list_id
             ORDER BY position ASC, added_at ASC'
        );
        $itemsQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $itemsQuery->execute();

        $filmIds = array_map('intval', array_column($itemsQuery->fetchAll(), 'film_id'));

        if (!$filmIds) {
            return;
        }

        $maxPositionQuery = $pdo->prepare(
            'SELECT COALESCE(MAX(position), 0)
             FROM list_items
             WHERE list_id = :list_id'
        );
        $maxPositionQuery->bindValue(':list_id', $listId, PDO::PARAM_INT);
        $maxPositionQuery->execute();

        $temporaryBase = (int) $maxPositionQuery->fetchColumn() + count($filmIds) + 1000;

        $temporaryUpdate = $pdo->prepare(
            'UPDATE list_items
             SET position = :position
             WHERE list_id = :list_id
               AND film_id = :film_id'
        );

        foreach ($filmIds as $index => $filmId) {
            $temporaryUpdate->bindValue(':position', $temporaryBase + $index + 1, PDO::PARAM_INT);
            $temporaryUpdate->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $temporaryUpdate->bindValue(':film_id', $filmId, PDO::PARAM_INT);
            $temporaryUpdate->execute();
        }

        $finalUpdate = $pdo->prepare(
            'UPDATE list_items
             SET position = :position
             WHERE list_id = :list_id
               AND film_id = :film_id'
        );

        foreach ($filmIds as $index => $filmId) {
            $finalUpdate->bindValue(':position', $index + 1, PDO::PARAM_INT);
            $finalUpdate->bindValue(':list_id', $listId, PDO::PARAM_INT);
            $finalUpdate->bindValue(':film_id', $filmId, PDO::PARAM_INT);
            $finalUpdate->execute();
        }
    }



    public function searchVisibleLists(string $term, int $currentUserId, int $limit = 20): array
    {
        $term = trim($term);
        $like = '%' . $term . '%';

        $query = $this->connection()->prepare(
            'SELECT
                l.id,
                l.user_id,
                l.title,
                l.description,
                l.is_public,
                l.is_ranked,
                l.created_at,
                l.updated_at,
                u.username,
                COUNT(li.film_id) AS films_count,
                p1.poster_url AS poster_1,
                p2.poster_url AS poster_2,
                p3.poster_url AS poster_3
             FROM lists l
             JOIN users u ON u.id = l.user_id
             LEFT JOIN list_items li ON li.list_id = l.id
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li1
                JOIN films f ON f.id = li1.film_id
                WHERE li1.list_id = l.id
                ORDER BY li1.position ASC, li1.added_at ASC
                LIMIT 1 OFFSET 0
             ) p1 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li2
                JOIN films f ON f.id = li2.film_id
                WHERE li2.list_id = l.id
                ORDER BY li2.position ASC, li2.added_at ASC
                LIMIT 1 OFFSET 1
             ) p2 ON TRUE
             LEFT JOIN LATERAL (
                SELECT f.poster_url
                FROM list_items li3
                JOIN films f ON f.id = li3.film_id
                WHERE li3.list_id = l.id
                ORDER BY li3.position ASC, li3.added_at ASC
                LIMIT 1 OFFSET 2
             ) p3 ON TRUE
             WHERE (l.is_public = TRUE OR l.user_id = :current_user_id)
               AND (
                    :term_empty = TRUE
                    OR l.title ILIKE :like_title
                    OR l.description ILIKE :like_description
                    OR u.username ILIKE :like_username
               )
             GROUP BY l.id, u.id, p1.poster_url, p2.poster_url, p3.poster_url
             ORDER BY l.updated_at DESC, l.created_at DESC, l.title ASC
             LIMIT :limit'
        );
        $query->bindValue(':current_user_id', $currentUserId, PDO::PARAM_INT);
        $query->bindValue(':term_empty', $term === '', PDO::PARAM_BOOL);
        $query->bindValue(':like_title', $like);
        $query->bindValue(':like_description', $like);
        $query->bindValue(':like_username', $like);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }


}
