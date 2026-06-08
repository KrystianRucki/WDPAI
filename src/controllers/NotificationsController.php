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

        $this->json(['notifications' => (new NotificationsRepository())->forUser((int) $_SESSION['user_id'])]);
    }

    public function markRead(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $notificationId = isset($data['id']) ? (int) $data['id'] : null;
        (new NotificationsRepository())->markRead((int) $_SESSION['user_id'], $notificationId);
        $this->json(['updated' => true]);
    }
}
