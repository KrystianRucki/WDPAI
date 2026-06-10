<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../services/TmdbService.php';

final class SearchController extends AppController
{
    public function search(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $type = $_GET['type'] ?? 'films';
        $term = trim($_GET['q'] ?? '');

        if ($type === 'users') {
            $this->json(['results' => (new UsersRepository())->searchUsers($term)]);
            return;
        }

        try {
            $tmdb = new TmdbService();

            if ($type === 'actors' || $type === 'people') {
                $this->json($tmdb->searchPeople($term));
                return;
            }

            $this->json($tmdb->searchMovies($term));
        } catch (Throwable $exception) {
            $this->json([
                'error' => true,
                'message' => $exception->getMessage(),
                'results' => (new FilmsRepository())->search($term),
            ], 502);
        }
    }

    public function users(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $term = trim($data['search'] ?? $_GET['q'] ?? '');
        $this->json((new UsersRepository())->searchUsers($term));
    }
}
