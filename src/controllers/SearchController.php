<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ListsRepository.php';
require_once __DIR__ . '/../services/TmdbService.php';

final class SearchController extends AppController
{
    private const DEFAULT_LIMIT = 24;
    private const ALL_LIMIT = 8;

    public function index(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = $this->query();
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        $filmsRepository = new FilmsRepository();
        $usersRepository = new UsersRepository();
        $listsRepository = new ListsRepository();

        $filmResults = $filmsRepository->search($query, self::ALL_LIMIT);
        $crewResults = $filmsRepository->searchPeople($query, self::ALL_LIMIT);
        $tmdbFilmFallback = [];
        $tmdbCrewFallback = [];
        $tmdbError = null;

        if ($query !== '' && (!$filmResults || !$crewResults)) {
            try {
                $tmdb = new TmdbService();

                if (!$filmResults) {
                    $tmdbFilmFallback = $this->tmdbMovieResults($tmdb, $query, self::ALL_LIMIT);
                }

                if (!$crewResults) {
                    $tmdbCrewFallback = $this->tmdbPersonResults($tmdb, $query, self::ALL_LIMIT);
                }
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $this->render('search_empty', [
            'query' => $query,
            'activeSearchType' => 'all',
            'filmResults' => $filmResults,
            'crewResults' => $crewResults,
            'tmdbFilmFallback' => $tmdbFilmFallback,
            'tmdbCrewFallback' => $tmdbCrewFallback,
            'tmdbError' => $tmdbError,
            'userResults' => $usersRepository->searchPublicUsers($query, $currentUserId, self::ALL_LIMIT),
            'listResults' => $listsRepository->searchVisibleLists($query, $currentUserId, self::ALL_LIMIT),
        ]);
    }

    public function filmsPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = $this->query();
        $filmResults = (new FilmsRepository())->search($query, self::DEFAULT_LIMIT);
        $tmdbFilmFallback = [];
        $tmdbError = null;

        if ($query !== '' && !$filmResults) {
            try {
                $tmdbFilmFallback = $this->tmdbMovieResults(new TmdbService(), $query, self::DEFAULT_LIMIT);
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $this->render('search_films', [
            'query' => $query,
            'activeSearchType' => 'films',
            'filmResults' => $filmResults,
            'tmdbFilmFallback' => $tmdbFilmFallback,
            'tmdbError' => $tmdbError,
        ]);
    }

    public function crewPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = $this->query();
        $crewResults = (new FilmsRepository())->searchPeople($query, self::DEFAULT_LIMIT);
        $tmdbCrewFallback = [];
        $tmdbError = null;

        if ($query !== '' && !$crewResults) {
            try {
                $tmdbCrewFallback = $this->tmdbPersonResults(new TmdbService(), $query, self::DEFAULT_LIMIT);
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $this->render('search_crew', [
            'query' => $query,
            'activeSearchType' => 'crew',
            'crewResults' => $crewResults,
            'tmdbCrewFallback' => $tmdbCrewFallback,
            'tmdbError' => $tmdbError,
        ]);
    }

    public function usersPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = $this->query();

        $this->render('search_users', [
            'query' => $query,
            'activeSearchType' => 'users',
            'userResults' => (new UsersRepository())->searchPublicUsers($query, (int) $_SESSION['user_id'], self::DEFAULT_LIMIT),
        ]);
    }

    public function listsPage(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $query = $this->query();

        $this->render('search_lists', [
            'query' => $query,
            'activeSearchType' => 'lists',
            'listResults' => (new ListsRepository())->searchVisibleLists($query, (int) $_SESSION['user_id'], self::DEFAULT_LIMIT),
        ]);
    }

    public function search(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $type = strtolower((string) ($_GET['type'] ?? 'films'));
        $query = $this->query();
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        $filmsRepository = new FilmsRepository();

        if ($type === 'users') {
            $this->json(['results' => (new UsersRepository())->searchPublicUsers($query, $currentUserId, self::DEFAULT_LIMIT)]);
            return;
        }

        if ($type === 'lists') {
            $this->json(['results' => (new ListsRepository())->searchVisibleLists($query, $currentUserId, self::DEFAULT_LIMIT)]);
            return;
        }

        if ($type === 'crew' || $type === 'people') {
            $results = $filmsRepository->searchPeople($query, self::DEFAULT_LIMIT);
            $source = 'local';

            if ($query !== '' && !$results) {
                try {
                    $results = $this->tmdbPersonResults(new TmdbService(), $query, self::DEFAULT_LIMIT);
                    $source = 'tmdb';
                } catch (Throwable $exception) {
                    $this->json(['results' => [], 'source' => 'tmdb_error', 'message' => $exception->getMessage()], 502);
                    return;
                }
            }

            $this->json(['results' => $results, 'source' => $source]);
            return;
        }

        $results = $filmsRepository->search($query, self::DEFAULT_LIMIT);
        $source = 'local';

        if ($query !== '' && !$results) {
            try {
                $results = $this->tmdbMovieResults(new TmdbService(), $query, self::DEFAULT_LIMIT);
                $source = 'tmdb';
            } catch (Throwable $exception) {
                $this->json(['results' => [], 'source' => 'tmdb_error', 'message' => $exception->getMessage()], 502);
                return;
            }
        }

        $this->json(['results' => $results, 'source' => $source]);
    }

    public function users(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $query = trim((string) ($data['search'] ?? $_GET['q'] ?? $_GET['query'] ?? ''));

        $this->json((new UsersRepository())->searchPublicUsers($query, (int) $_SESSION['user_id'], self::DEFAULT_LIMIT));
    }


    private function tmdbMovieResults(TmdbService $tmdb, string $query, int $limit): array
    {
        $response = $tmdb->searchMovies($query);
        $results = array_slice($response['results'] ?? [], 0, $limit);

        return array_map(static function (array $movie): array {
            $movie['source'] = 'tmdb';
            return $movie;
        }, $results);
    }

    private function tmdbPersonResults(TmdbService $tmdb, string $query, int $limit): array
    {
        $response = $tmdb->searchPeople($query);
        $results = array_slice($response['results'] ?? [], 0, $limit);

        return (new FilmsRepository())->savePeopleFromTmdbSearchResults($results);
    }

    private function query(): string
    {
        return trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
    }
}
