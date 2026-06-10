<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../services/TmdbService.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';

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

        $tmdbId = (int) ($_GET['tmdb_id'] ?? $_GET['id'] ?? 0);
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
        }

        if (!$film) {
            $film = $this->films->getFallbackFilm();
        }

        $this->render('film_details', compact('film', 'tmdbError'));
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
}
