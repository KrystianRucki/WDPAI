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

        $sourceFilmId = (int) ($data['film_id'] ?? $_POST['film_id'] ?? 0);
        $listsRepository = new ListsRepository();

        $id = $listsRepository->create(
            (int) $_SESSION['user_id'],
            $title,
            trim($data['description'] ?? $_POST['description'] ?? '') ?: null,
            $this->booleanValue($data['is_public'] ?? $_POST['is_public'] ?? null, true),
            $this->booleanValue($data['is_ranked'] ?? $_POST['is_ranked'] ?? null, false)
        );

        if ($sourceFilmId > 0) {
            $listsRepository->addFilmToUserLists((int) $_SESSION['user_id'], $sourceFilmId, [$id]);
        }

        $this->json(['success' => true, 'created' => true, 'id' => $id, 'redirect' => '/list-details?id=' . $id], 201);
    }

    public function addFilm(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $filmId = (int) ($data['film_id'] ?? 0);
        $listIds = $data['list_ids'] ?? [];

        if (!$listIds && isset($data['list_id'])) {
            $listIds = [(int) $data['list_id']];
        }

        if (!is_array($listIds)) {
            $listIds = [];
        }

        $listIds = array_values(array_unique(array_filter(array_map('intval', $listIds), fn (int $id): bool => $id > 0)));

        if ($filmId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Film could not be resolved.',
            ], 422);
            return;
        }

        try {
            $result = (new ListsRepository())->syncFilmUserLists((int) $_SESSION['user_id'], $filmId, $listIds);

            $this->json([
                'success' => true,
                'saved' => true,
                'added_count' => $result['added_count'],
                'removed_count' => $result['removed_count'],
                'selected_count' => $result['selected_count'],
                'redirect' => '/film-details?id=' . $filmId,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'error' => 'Could not save list changes.',
            ], 500);
        }
    }


    public function reorder(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $listId = (int) ($data['list_id'] ?? 0);
        $filmIds = $data['film_ids'] ?? [];

        if (!is_array($filmIds)) {
            $filmIds = [];
        }

        $filmIds = array_values(array_unique(array_filter(array_map('intval', $filmIds), fn (int $id): bool => $id > 0)));

        if ($listId <= 0 || !$filmIds) {
            $this->json([
                'success' => false,
                'error' => 'Invalid list order.',
            ], 422);
            return;
        }

        try {
            $updated = (new ListsRepository())->reorderRankedList((int) $_SESSION['user_id'], $listId, $filmIds);

            if ($updated <= 0) {
                $this->json([
                    'success' => false,
                    'error' => 'This list cannot be reordered.',
                ], 403);
                return;
            }

            $this->json([
                'success' => true,
                'updated_count' => $updated,
                'redirect' => '/list-details?id=' . $listId,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'error' => 'Could not save list order.',
            ], 500);
        }
    }


    public function removeFilm(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $listId = (int) ($data['list_id'] ?? 0);
        $filmId = (int) ($data['film_id'] ?? 0);

        if ($listId <= 0 || $filmId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Invalid list or film id.',
            ], 422);
            return;
        }

        try {
            $removed = (new ListsRepository())->removeFilmFromUserList((int) $_SESSION['user_id'], $listId, $filmId);

            if (!$removed) {
                $this->json([
                    'success' => false,
                    'error' => 'Film was not removed.',
                ], 404);
                return;
            }

            $this->json([
                'success' => true,
                'removed' => true,
                'redirect' => '/list-details?id=' . $listId,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'error' => 'Could not remove film from list.',
            ], 500);
        }
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
