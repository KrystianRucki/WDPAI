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
            $this->booleanValue($data['is_public'] ?? $_POST['is_public'] ?? null, true),
            $this->booleanValue($data['is_ranked'] ?? $_POST['is_ranked'] ?? null, false)
        );

        $this->json(['success' => true, 'created' => true, 'id' => $id, 'redirect' => '/list-details?id=' . $id], 201);
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

    private function booleanValue(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }


}
