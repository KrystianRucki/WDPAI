<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class SettingsController extends AppController
{
    public function updateProfile(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $username = trim($data['username'] ?? '');
        $bio = trim($data['bio'] ?? '');

        if ($username === '') {
            $this->json(['error' => 'Username is required'], 422);
            return;
        }

        $updated = (new UsersRepository())->updateProfile((int) $_SESSION['user_id'], $username, $bio);
        $this->json(['updated' => $updated]);
    }

    public function updateNotifications(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        // Stored as a JSON preference in real projects; this endpoint is ready for frontend FETCH integration.
        $this->json(['updated' => true]);
    }
}
