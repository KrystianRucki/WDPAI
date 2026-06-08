<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';

final class FilmsRepository extends Repository
{
    public function search(string $term, int $limit = 20): array
    {
        $query = $this->connection()->prepare(
            'SELECT f.*, COALESCE(ROUND(AVG(r.rating), 2), 0) AS average_rating
             FROM films f
             LEFT JOIN reviews r ON r.film_id = f.id
             WHERE f.title ILIKE :term OR f.director ILIKE :term
             GROUP BY f.id
             ORDER BY f.title ASC
             LIMIT :limit'
        );
        $query->bindValue(':term', '%' . $term . '%');
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    public function getFeed(int $limit = 24): array
    {
        $query = $this->connection()->prepare(
            'SELECT f.*, COALESCE(ROUND(AVG(r.rating), 2), 0) AS average_rating
             FROM films f
             LEFT JOIN reviews r ON r.film_id = f.id
             GROUP BY f.id
             ORDER BY f.created_at DESC
             LIMIT :limit'
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }
}
