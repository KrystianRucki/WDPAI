<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/ListsRepository.php';

final class ListsController extends AppController
{
    public function create(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $title = trim($data['title'] ?? $_POST['title'] ?? '');

        if ($title === '') {
            $this->json(['error' => 'List title is required'], 422);
            return;
        }

        $id = (new ListsRepository())->create(
            (int) $_SESSION['user_id'],
            $title,
            trim($data['description'] ?? $_POST['description'] ?? '') ?: null,
            (bool) ($data['is_public'] ?? true),
            (bool) ($data['is_ranked'] ?? false)
        );

        $this->json(['created' => true, 'id' => $id], 201);
    }

    public function addFilm(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $listId = (int) ($data['list_id'] ?? 0);
        $filmId = (int) ($data['film_id'] ?? 0);

        if ($listId <= 0 || $filmId <= 0) {
            $this->json(['error' => 'Invalid list or film id'], 422);
            return;
        }

        (new ListsRepository())->addFilm($listId, $filmId);
        $this->json(['added' => true]);
    }
}
