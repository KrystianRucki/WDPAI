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


    public function uploadAvatar(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
            $this->json(['success' => false, 'message' => 'Avatar file is required.'], 422);
            return;
        }

        $file = $_FILES['avatar'];
        $maxBytes = 2 * 1024 * 1024;
        $maxWidth = 1024;
        $maxHeight = 1024;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Could not upload avatar.'], 422);
            return;
        }

        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
            $this->json(['success' => false, 'message' => 'Avatar must be smaller than 2 MB.'], 422);
            return;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $info = @getimagesize($tmpPath);

        if (!$info) {
            $this->json(['success' => false, 'message' => 'Avatar must be a valid image.'], 422);
            return;
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? '';

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            $this->json(['success' => false, 'message' => 'Avatar must be JPG, PNG or WEBP.'], 422);
            return;
        }

        if ($width > $maxWidth || $height > $maxHeight) {
            $this->json([
                'success' => false,
                'message' => 'Avatar resolution cannot exceed 1024 × 1024 px.',
            ], 422);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $uploadDir = __DIR__ . '/../../public/uploads/avatars';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->json(['success' => false, 'message' => 'Could not create upload directory.'], 500);
            return;
        }

        $extension = $extensions[$mime];
        $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;
        $publicUrl = '/public/uploads/avatars/' . $filename;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $this->json(['success' => false, 'message' => 'Could not save avatar.'], 500);
            return;
        }

        $repository = new UsersRepository();
        $oldUser = $repository->getUserById($userId);
        $repository->updateAvatar($userId, $publicUrl);

        $oldAvatar = (string) ($oldUser['avatar_url'] ?? '');
        if (str_starts_with($oldAvatar, '/public/uploads/avatars/')) {
            $oldPath = __DIR__ . '/../..' . $oldAvatar;
            if (is_file($oldPath) && realpath(dirname($oldPath)) === realpath($uploadDir)) {
                @unlink($oldPath);
            }
        }

        $user = $repository->getUserById($userId);

        $this->json([
            'success' => true,
            'updated' => true,
            'avatar_url' => $publicUrl,
            'user' => [
                'id' => $userId,
                'username' => $user['username'] ?? null,
                'bio' => $user['bio'] ?? null,
                'avatar_url' => $user['avatar_url'] ?? $publicUrl,
            ],
        ]);
    }

    public function updateNotifications(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $settings = is_array($data['notifications'] ?? null) ? $data['notifications'] : $data;

        $newFollowers = filter_var($settings['new_followers'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $reviewLikes = filter_var($settings['review_likes'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $reviewComments = filter_var($settings['review_comments'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        $newFollowers = $newFollowers ?? false;
        $reviewLikes = $reviewLikes ?? false;
        $reviewComments = $reviewComments ?? false;

        try {
            $savedSettings = (new UsersRepository())->updateNotificationSettings(
                (int) $_SESSION['user_id'],
                $newFollowers,
                $reviewLikes,
                $reviewComments
            );

            $this->json([
                'success' => true,
                'updated' => true,
                'settings' => $savedSettings,
            ]);
        } catch (Throwable) {
            $this->json([
                'success' => false,
                'message' => 'Could not save notification settings.',
            ], 500);
        }
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
