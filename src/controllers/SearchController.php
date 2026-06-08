<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

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

        $this->json(['results' => (new FilmsRepository())->search($term)]);
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
