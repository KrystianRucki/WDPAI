<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';

final class SettingsController extends AppController
{
    public function updateProfile(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $username = trim((string) ($data['username'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));

        if ($username === '') {
            $this->json(['success' => false, 'message' => 'Username is required.'], 422);
            return;
        }

        if (mb_strlen($username) > 50) {
            $this->json(['success' => false, 'message' => 'Username is too long.'], 422);
            return;
        }

        if (mb_strlen($bio) > 64) {
            $this->json(['success' => false, 'message' => 'Bio cannot be longer than 64 characters.'], 422);
            return;
        }

        try {
            $repository = new UsersRepository();
            $repository->updateProfile((int) $_SESSION['user_id'], $username, $bio);
            $user = $repository->getUserById((int) $_SESSION['user_id']);

            $this->json([
                'success' => true,
                'updated' => true,
                'user' => [
                    'id' => (int) ($user['id'] ?? $_SESSION['user_id']),
                    'username' => $user['username'] ?? $username,
                    'bio' => $user['bio'] ?? $bio,
                    'avatar_url' => $user['avatar_url'] ?? null,
                ],
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                $this->json(['success' => false, 'message' => 'This username is already taken.'], 409);
                return;
            }

            $this->json(['success' => false, 'message' => 'Could not save profile changes.'], 500);
        }
    }

    public function updateNotifications(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $this->json(['success' => true, 'updated' => true]);
    }

    public function saveFavorites(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $filmIds = $data['film_ids'] ?? [];

        if (!is_array($filmIds)) {
            $filmIds = [];
        }

        $filmIds = array_values(array_unique(array_filter(array_map('intval', $filmIds), fn (int $id): bool => $id > 0)));

        if (count($filmIds) > 4) {
            $this->json([
                'success' => false,
                'message' => 'You can select up to 4 favorite films.',
            ], 422);
            return;
        }

        try {
            $result = (new FilmsRepository())->saveUserFavoriteFilms((int) $_SESSION['user_id'], $filmIds);

            $this->json([
                'success' => true,
                'saved' => true,
                'saved_count' => $result['saved_count'],
                'film_ids' => $result['film_ids'],
                'redirect' => '/profile',
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'message' => 'Could not save favorite films.',
            ], 500);
        }
    }


}
