<?php

declare(strict_types=1);

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../services/TmdbService.php';

final class FilmsRepository extends Repository
{
    public function search(string $term, int $limit = 20): array
    {
        $query = $this->connection()->prepare(
            'SELECT f.*, COALESCE(ROUND(AVG(r.rating), 2), 0) AS average_rating
             FROM films f
             LEFT JOIN reviews r ON r.film_id = f.id
             WHERE f.title ILIKE :term OR f.original_title ILIKE :term OR f.director ILIKE :term
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

    public function getById(int $id): ?array
    {
        $query = $this->connection()->prepare('SELECT * FROM films WHERE id = :id');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $film = $query->fetch();

        return $film ? $this->hydrateFilm($film) : null;
    }

    public function getByTmdbId(int $tmdbId): ?array
    {
        $query = $this->connection()->prepare('SELECT * FROM films WHERE tmdb_id = :tmdb_id');
        $query->bindValue(':tmdb_id', $tmdbId, PDO::PARAM_INT);
        $query->execute();
        $film = $query->fetch();

        return $film ? $this->hydrateFilm($film) : null;
    }

    public function saveFromTmdb(array $movie, TmdbService $tmdb): array
    {
        $connection = $this->connection();
        $connection->beginTransaction();

        try {
            $this->cacheGenres($movie['genres'] ?? []);

            $director = $this->directorName($movie['credits']['crew'] ?? []);
            $releaseYear = !empty($movie['release_date']) ? (int) substr((string) $movie['release_date'], 0, 4) : null;
            $posterPath = $movie['poster_path'] ?? null;
            $backdropPath = $movie['backdrop_path'] ?? null;

            $query = $connection->prepare(
                'INSERT INTO films (
                    tmdb_id, title, original_title, release_year, director, description,
                    poster_url, poster_path, backdrop_url, backdrop_path, runtime_minutes,
                    tmdb_vote_average, tmdb_vote_count, tmdb_popularity, tmdb_raw, tmdb_synced_at
                 ) VALUES (
                    :tmdb_id, :title, :original_title, :release_year, :director, :description,
                    :poster_url, :poster_path, :backdrop_url, :backdrop_path, :runtime_minutes,
                    :vote_average, :vote_count, :popularity, CAST(:tmdb_raw AS jsonb), CURRENT_TIMESTAMP
                 )
                 ON CONFLICT (tmdb_id) DO UPDATE SET
                    title = EXCLUDED.title,
                    original_title = EXCLUDED.original_title,
                    release_year = EXCLUDED.release_year,
                    director = EXCLUDED.director,
                    description = EXCLUDED.description,
                    poster_url = EXCLUDED.poster_url,
                    poster_path = EXCLUDED.poster_path,
                    backdrop_url = EXCLUDED.backdrop_url,
                    backdrop_path = EXCLUDED.backdrop_path,
                    runtime_minutes = EXCLUDED.runtime_minutes,
                    tmdb_vote_average = EXCLUDED.tmdb_vote_average,
                    tmdb_vote_count = EXCLUDED.tmdb_vote_count,
                    tmdb_popularity = EXCLUDED.tmdb_popularity,
                    tmdb_raw = EXCLUDED.tmdb_raw,
                    tmdb_synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                 RETURNING *'
            );

            $query->execute([
                ':tmdb_id' => (int) $movie['id'],
                ':title' => $movie['title'] ?? $movie['original_title'] ?? 'Untitled',
                ':original_title' => $movie['original_title'] ?? null,
                ':release_year' => $releaseYear,
                ':director' => $director,
                ':description' => $movie['overview'] ?? null,
                ':poster_url' => $tmdb->imageUrl($posterPath, 'w500'),
                ':poster_path' => $posterPath,
                ':backdrop_url' => $tmdb->imageUrl($backdropPath, 'w1280'),
                ':backdrop_path' => $backdropPath,
                ':runtime_minutes' => $movie['runtime'] ?? null,
                ':vote_average' => $movie['vote_average'] ?? null,
                ':vote_count' => $movie['vote_count'] ?? null,
                ':popularity' => $movie['popularity'] ?? null,
                ':tmdb_raw' => json_encode($movie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $film = $query->fetch();
            $filmId = (int) $film['id'];

            $this->replaceFilmGenres($filmId, $movie['genres'] ?? []);
            $this->replaceFilmCredits($filmId, $movie['credits'] ?? [], $tmdb);

            $connection->commit();
            return $this->getById($filmId) ?? $film;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function cacheGenres(array $genres): void
    {
        if ($genres === []) {
            return;
        }

        $query = $this->connection()->prepare(
            'INSERT INTO genres (tmdb_id, name, tmdb_synced_at)
             VALUES (:tmdb_id, :name, CURRENT_TIMESTAMP)
             ON CONFLICT (name) DO UPDATE SET
                tmdb_id = EXCLUDED.tmdb_id,
                tmdb_synced_at = CURRENT_TIMESTAMP'
        );

        foreach ($genres as $genre) {
            if (empty($genre['name'])) {
                continue;
            }

            $query->execute([
                ':tmdb_id' => $genre['id'] ?? null,
                ':name' => $genre['name'],
            ]);
        }
    }

    public function getFallbackFilm(): ?array
    {
        $query = $this->connection()->query('SELECT * FROM films ORDER BY id ASC LIMIT 1');
        $film = $query->fetch();
        return $film ? $this->hydrateFilm($film) : null;
    }

    private function hydrateFilm(array $film): array
    {
        $film['genres'] = $this->getGenresForFilm((int) $film['id']);
        $film['cast'] = $this->getCreditsForFilm((int) $film['id'], 'actor');
        $film['crew'] = $this->getCreditsForFilm((int) $film['id'], 'director');
        $film['average_rating'] = $this->averageRating((int) $film['id']);
        return $film;
    }

    private function getGenresForFilm(int $filmId): array
    {
        $query = $this->connection()->prepare(
            'SELECT g.* FROM genres g
             INNER JOIN film_genres fg ON fg.genre_id = g.id
             WHERE fg.film_id = :film_id
             ORDER BY g.name ASC'
        );
        $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    private function getCreditsForFilm(int $filmId, string $type): array
    {
        $query = $this->connection()->prepare(
            'SELECT p.*, fp.credit_type, fp.character_name, fp.job, fp.department, fp.cast_order
             FROM people p
             INNER JOIN film_people fp ON fp.person_id = p.id
             WHERE fp.film_id = :film_id AND fp.credit_type = :credit_type
             ORDER BY fp.cast_order NULLS LAST, p.full_name ASC
             LIMIT 12'
        );
        $query->execute([':film_id' => $filmId, ':credit_type' => $type]);
        return $query->fetchAll();
    }

    private function averageRating(int $filmId): float
    {
        $query = $this->connection()->prepare('SELECT COALESCE(ROUND(AVG(rating), 2), 0) AS average_rating FROM reviews WHERE film_id = :film_id');
        $query->bindValue(':film_id', $filmId, PDO::PARAM_INT);
        $query->execute();
        return (float) ($query->fetch()['average_rating'] ?? 0);
    }

    private function replaceFilmGenres(int $filmId, array $genres): void
    {
        $connection = $this->connection();
        $connection->prepare('DELETE FROM film_genres WHERE film_id = :film_id')->execute([':film_id' => $filmId]);

        $select = $connection->prepare('SELECT id FROM genres WHERE name = :name LIMIT 1');
        $insert = $connection->prepare('INSERT INTO film_genres (film_id, genre_id) VALUES (:film_id, :genre_id) ON CONFLICT DO NOTHING');

        foreach ($genres as $genre) {
            if (empty($genre['name'])) {
                continue;
            }

            $select->execute([':name' => $genre['name']]);
            $row = $select->fetch();
            if ($row) {
                $insert->execute([':film_id' => $filmId, ':genre_id' => (int) $row['id']]);
            }
        }
    }

    private function replaceFilmCredits(int $filmId, array $credits, TmdbService $tmdb): void
    {
        $connection = $this->connection();
        $connection->prepare('DELETE FROM film_people WHERE film_id = :film_id')->execute([':film_id' => $filmId]);

        $cast = array_slice($credits['cast'] ?? [], 0, 12);
        foreach ($cast as $person) {
            $personId = $this->upsertPerson($person, $tmdb);
            $this->insertCredit($filmId, $personId, 'actor', $person['character'] ?? null, null, 'Acting', $person['order'] ?? null);
        }

        foreach (($credits['crew'] ?? []) as $person) {
            $job = $person['job'] ?? '';
            if (!in_array($job, ['Director', 'Writer', 'Screenplay', 'Composer'], true)) {
                continue;
            }

            $type = match ($job) {
                'Director' => 'director',
                'Composer' => 'composer',
                default => 'writer',
            };

            $personId = $this->upsertPerson($person, $tmdb);
            $this->insertCredit($filmId, $personId, $type, null, $job, $person['department'] ?? null, null);
        }
    }

    private function upsertPerson(array $person, TmdbService $tmdb): int
    {
        $query = $this->connection()->prepare(
            'INSERT INTO people (tmdb_id, full_name, photo_url, profile_path, known_for_department, tmdb_synced_at)
             VALUES (:tmdb_id, :full_name, :photo_url, :profile_path, :known_for_department, CURRENT_TIMESTAMP)
             ON CONFLICT (tmdb_id) DO UPDATE SET
                full_name = EXCLUDED.full_name,
                photo_url = EXCLUDED.photo_url,
                profile_path = EXCLUDED.profile_path,
                known_for_department = EXCLUDED.known_for_department,
                tmdb_synced_at = CURRENT_TIMESTAMP
             RETURNING id'
        );
        $query->execute([
            ':tmdb_id' => $person['id'] ?? null,
            ':full_name' => $person['name'] ?? 'Unknown person',
            ':photo_url' => $tmdb->imageUrl($person['profile_path'] ?? null, 'w185'),
            ':profile_path' => $person['profile_path'] ?? null,
            ':known_for_department' => $person['known_for_department'] ?? $person['department'] ?? null,
        ]);

        return (int) $query->fetch()['id'];
    }

    private function insertCredit(int $filmId, int $personId, string $type, ?string $character, ?string $job, ?string $department, mixed $order): void
    {
        $query = $this->connection()->prepare(
            'INSERT INTO film_people (film_id, person_id, credit_type, character_name, job, department, cast_order)
             VALUES (:film_id, :person_id, :credit_type, :character_name, :job, :department, :cast_order)
             ON CONFLICT (film_id, person_id, credit_type) DO UPDATE SET
                character_name = EXCLUDED.character_name,
                job = EXCLUDED.job,
                department = EXCLUDED.department,
                cast_order = EXCLUDED.cast_order'
        );
        $query->execute([
            ':film_id' => $filmId,
            ':person_id' => $personId,
            ':credit_type' => $type,
            ':character_name' => $character,
            ':job' => $job,
            ':department' => $department,
            ':cast_order' => is_numeric($order) ? (int) $order : null,
        ]);
    }

    private function directorName(array $crew): ?string
    {
        foreach ($crew as $person) {
            if (($person['job'] ?? null) === 'Director') {
                return $person['name'] ?? null;
            }
        }

        return null;
    }
}
