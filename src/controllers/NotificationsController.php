<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/NotificationsRepository.php';

final class NotificationsController extends AppController
{
    public function list(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $repository = new NotificationsRepository();

        $this->json([
            'notifications' => $repository->forUser($userId),
            'stats' => $repository->statsForUser($userId),
        ]);
    }

    public function markRead(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $notificationId = isset($data['id']) ? (int) $data['id'] : null;

        if ($notificationId !== null && $notificationId <= 0) {
            $notificationId = null;
        }

        $userId = (int) $_SESSION['user_id'];
        $repository = new NotificationsRepository();
        $updated = $repository->markRead($userId, $notificationId);

        $this->json([
            'success' => true,
            'updated' => $updated,
            'stats' => $repository->statsForUser($userId),
        ]);
    }
}
