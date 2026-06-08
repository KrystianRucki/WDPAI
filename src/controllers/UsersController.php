<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class UsersController extends AppController
{
    public function search(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $term = trim($data['search'] ?? '');
        $this->json((new UsersRepository())->searchUsers($term));
    }

    public function delete(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $data = $this->getJsonInput();
        $userId = (int) ($data['id'] ?? 0);

        if ($userId <= 0) {
            $this->json(['deleted' => false, 'error' => 'Invalid user id'], 422);
            return;
        }

        $deleted = (new UsersRepository())->deleteUser($userId);
        $this->json(['deleted' => $deleted]);
    }
}
