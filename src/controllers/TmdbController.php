<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../services/TmdbService.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/ReviewsRepository.php';

final class TmdbController extends AppController
{
    private TmdbService $tmdb;
    private FilmsRepository $films;

    public function __construct()
    {
        $this->tmdb = new TmdbService();
        $this->films = new FilmsRepository();
    }

    public function searchMoviesApi(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        try {
            $this->json($this->tmdb->searchMovies($query, $page));
        } catch (Throwable $exception) {
            $this->json(['error' => true, 'message' => $exception->getMessage(), 'results' => []], 502);
        }
    }

    public function searchPeopleApi(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        try {
            $this->json($this->tmdb->searchPeople($query, $page));
        } catch (Throwable $exception) {
            $this->json(['error' => true, 'message' => $exception->getMessage(), 'results' => []], 502);
        }
    }

    public function cacheGenresApi(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $genres = $this->tmdb->getMovieGenres();
            $this->films->cacheGenres($genres);
            $this->json(['success' => true, 'count' => count($genres)]);
        } catch (Throwable $exception) {
            $this->json(['success' => false, 'message' => $exception->getMessage()], 502);
        }
    }

    public function logSearchResults(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
        $movieResults = [];
        $tmdbError = null;
        $totalResults = 0;

        try {
            $response = $this->tmdb->searchMovies($query, max(1, (int) ($_GET['page'] ?? 1)));
            $movieResults = $response['results'] ?? [];
            $totalResults = (int) ($response['total_results'] ?? count($movieResults));
        } catch (Throwable $exception) {
            $tmdbError = $exception->getMessage();
        }

        $this->render('log_search_results', compact('query', 'movieResults', 'tmdbError', 'totalResults'));
    }

    public function logSelected(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $filmId = (int) ($_GET['film_id'] ?? 0);
        $tmdbId = (int) ($_GET['tmdb_id'] ?? $_GET['id'] ?? 0);
        $film = null;
        $tmdbError = null;

        if ($filmId > 0) {
            $film = $this->films->getById($filmId);
        } elseif ($tmdbId > 0) {
            try {
                $details = $this->tmdb->getMovieDetails($tmdbId);
                $film = $this->films->saveFromTmdb($details, $this->tmdb);
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
                $film = $this->films->getByTmdbId($tmdbId);
            }
        }

        if (!$film) {
            $film = $this->films->getFallbackFilm();
        }

        $this->render('log_selected', compact('film', 'tmdbError'));
    }

    public function filmDetails(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $tmdbId = (int) ($_GET['tmdb_id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $film = null;
        $tmdbError = null;

        if ($tmdbId > 0) {
            try {
                $details = $this->tmdb->getMovieDetails($tmdbId);
                $film = $this->films->saveFromTmdb($details, $this->tmdb);
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
                $film = $this->films->getByTmdbId($tmdbId);
            }
        } elseif ($id > 0) {
            $film = $this->films->getById($id);

            if ($film && !empty($film['tmdb_id'])) {
                try {
                    $details = $this->tmdb->getMovieDetails((int) $film['tmdb_id']);
                    $film = $this->films->saveFromTmdb($details, $this->tmdb);
                } catch (Throwable $exception) {
                    $tmdbError = $exception->getMessage();
                    $film = $this->films->getById($id);
                }
            }
        }

        if (!$film) {
            $film = $this->films->getFallbackFilm();
        }

        $filmId = (int) ($film['id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        $filmState = [
            'watched' => $filmId > 0 && $currentUserId > 0 ? $this->films->userHasWatchedFilm($currentUserId, $filmId) : false,
            'watchlisted' => $filmId > 0 && $currentUserId > 0 ? $this->films->userHasFilmInWatchlist($currentUserId, $filmId) : false,
        ];

        $filmReviews = $filmId > 0 ? (new ReviewsRepository())->getFilmReviews($filmId, 6) : [];

        $this->render('film_details', compact('film', 'tmdbError', 'filmState', 'filmReviews'));
    }

    public function searchFilmsPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
        $movieResults = [];
        $tmdbError = null;
        $totalResults = 0;

        if ($query !== '') {
            try {
                $response = $this->tmdb->searchMovies($query, max(1, (int) ($_GET['page'] ?? 1)));
                $movieResults = $response['results'] ?? [];
                $totalResults = (int) ($response['total_results'] ?? count($movieResults));
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $this->render('search_films', compact('query', 'movieResults', 'tmdbError', 'totalResults'));
    }

    public function searchActorsPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
        $peopleResults = [];
        $tmdbError = null;
        $totalResults = 0;

        if ($query !== '') {
            try {
                $response = $this->tmdb->searchPeople($query, max(1, (int) ($_GET['page'] ?? 1)));
                $peopleResults = $response['results'] ?? [];
                $totalResults = (int) ($response['total_results'] ?? count($peopleResults));
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $this->render('search_actors', compact('query', 'peopleResults', 'tmdbError', 'totalResults'));
    }

    public function removeFromWatchlist(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $filmId = (int) ($data['film_id'] ?? 0);

        if ($filmId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Invalid film id.',
            ], 422);
            return;
        }

        try {
            $removed = $this->films->removeFromWatchlist((int) $_SESSION['user_id'], $filmId);

            $this->json([
                'success' => true,
                'removed' => $removed,
                'redirect' => '/profile-watchlist',
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'error' => 'Could not remove film from watchlist.',
            ], 500);
        }
    }


    public function addToWatchlist(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $filmId = (int) ($data['film_id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        if ($filmId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid film id.',
            ], 422);
            return;
        }

        try {
            $result = $this->films->toggleWatchlist($userId, $filmId);

            $this->json([
                'success' => true,
                ...$result,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'message' => 'Could not update watchlist.',
            ], 500);
        }
    }

    public function markWatchedOnly(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $filmId = (int) ($data['film_id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        if ($filmId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid film id.',
            ], 422);
            return;
        }

        try {
            $result = $this->films->toggleUserFilm($userId, $filmId);

            $this->json([
                'success' => true,
                ...$result,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'message' => 'Could not update watched films.',
            ], 500);
        }
    }

}
