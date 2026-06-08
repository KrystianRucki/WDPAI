<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class ListsRepository extends Repository
{
    public function create(int $userId, string $title, ?string $description, bool $isPublic, bool $isRanked): int
    {
        $query = $this->connection()->prepare(
            'INSERT INTO lists (user_id, title, description, is_public, is_ranked) VALUES (:user_id, :title, :description, :is_public, :is_ranked) RETURNING id'
        );
        $query->execute([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'is_public' => $isPublic,
            'is_ranked' => $isRanked,
        ]);
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
            $query->execute([
                'list_id' => $listId,
                'film_id' => $filmId,
                'position' => $position,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
