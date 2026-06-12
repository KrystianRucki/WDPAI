<?php

declare(strict_types=1);

$configPath = __DIR__ . '/../../config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

final class TmdbService
{
    private string $token;
    private string $apiBaseUrl;
    private string $imageBaseUrl;
    private string $language;

    public function __construct()
    {
        $this->token = trim((string) (getenv('TMDB_ACCESS_TOKEN') ?: (defined('TMDB_ACCESS_TOKEN') ? TMDB_ACCESS_TOKEN : '')));
        $this->apiBaseUrl = rtrim((string) (getenv('TMDB_API_BASE_URL') ?: (defined('TMDB_API_BASE_URL') ? TMDB_API_BASE_URL : 'https://api.themoviedb.org/3')), '/');
        $this->imageBaseUrl = rtrim((string) (getenv('TMDB_IMAGE_BASE_URL') ?: (defined('TMDB_IMAGE_BASE_URL') ? TMDB_IMAGE_BASE_URL : 'https://image.tmdb.org/t/p')), '/');
        $this->language = (string) (getenv('TMDB_LANGUAGE') ?: (defined('TMDB_LANGUAGE') ? TMDB_LANGUAGE : 'en-US'));
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->token !== 'YOUR_TMDB_ACCESS_TOKEN';
    }

    public function searchMovies(string $query, int $page = 1): array
    {
        if (trim($query) === '') {
            return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []];
        }

        $response = $this->request('/search/movie', [
            'query' => $query,
            'page' => max(1, $page),
            'language' => $this->language,
            'include_adult' => 'false',
        ]);

        $response['results'] = array_map(fn (array $movie): array => $this->normalizeMovieResult($movie), $response['results'] ?? []);
        return $response;
    }

    public function searchPeople(string $query, int $page = 1): array
    {
        if (trim($query) === '') {
            return ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []];
        }

        $response = $this->request('/search/person', [
            'query' => $query,
            'page' => max(1, $page),
            'language' => $this->language,
            'include_adult' => 'false',
        ]);

        $response['results'] = array_map(fn (array $person): array => $this->normalizePersonResult($person), $response['results'] ?? []);
        return $response;
    }

    public function getMovieDetails(int $tmdbId): array
    {
        return $this->request('/movie/' . $tmdbId, [
            'language' => $this->language,
            'append_to_response' => 'credits,images',
            'include_image_language' => 'en,null',
        ]);
    }


    public function getPersonDetails(int $tmdbId): array
    {
        return $this->request('/person/' . $tmdbId, [
            'language' => $this->language,
            'append_to_response' => 'movie_credits,images',
            'include_image_language' => 'en,null',
        ]);
    }

    public function getMovieGenres(): array
    {
        $response = $this->request('/genre/movie/list', [
            'language' => $this->language,
        ]);

        return $response['genres'] ?? [];
    }

    public function imageUrl(?string $path, string $size = 'w500'): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return $this->imageBaseUrl . '/' . $size . '/' . ltrim($path, '/');
    }

    public function posterPlaceholder(): string
    {
        return '/public/assets/img/movie_placeholder.svg';
    }

    private function normalizeMovieResult(array $movie): array
    {
        $releaseDate = $movie['release_date'] ?? null;

        return [
            'tmdb_id' => (int) ($movie['id'] ?? 0),
            'title' => $movie['title'] ?? $movie['original_title'] ?? 'Untitled',
            'original_title' => $movie['original_title'] ?? null,
            'overview' => $movie['overview'] ?? '',
            'release_date' => $releaseDate,
            'release_year' => $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            'poster_path' => $movie['poster_path'] ?? null,
            'poster_url' => $this->imageUrl($movie['poster_path'] ?? null, 'w342') ?? $this->posterPlaceholder(),
            'backdrop_path' => $movie['backdrop_path'] ?? null,
            'backdrop_url' => $this->imageUrl($movie['backdrop_path'] ?? null, 'w780'),
            'vote_average' => $movie['vote_average'] ?? null,
            'vote_count' => $movie['vote_count'] ?? null,
            'popularity' => $movie['popularity'] ?? null,
            'genre_ids' => $movie['genre_ids'] ?? [],
        ];
    }

    private function normalizePersonResult(array $person): array
    {
        return [
            'tmdb_id' => (int) ($person['id'] ?? 0),
            'name' => $person['name'] ?? 'Unknown person',
            'known_for_department' => $person['known_for_department'] ?? null,
            'profile_path' => $person['profile_path'] ?? null,
            'profile_url' => $this->imageUrl($person['profile_path'] ?? null, 'w185') ?? $this->posterPlaceholder(),
            'popularity' => $person['popularity'] ?? null,
            'known_for' => $person['known_for'] ?? [],
        ];
    }

    private function request(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('TMDB_ACCESS_TOKEN is missing. Add it to config.php or .env.');
        }

        $url = $this->apiBaseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = $this->extractStatusCode($http_response_header ?? []);

        if ($body === false) {
            throw new RuntimeException('TMDB request failed. Check internet connection and API token.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('TMDB returned an invalid response.');
        }

        if ($status >= 400) {
            $message = $decoded['status_message'] ?? ('TMDB request failed with HTTP status ' . $status);
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 200;
    }
}
