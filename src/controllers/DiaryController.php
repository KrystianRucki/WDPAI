<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/DiaryRepository.php';

final class DiaryController extends AppController
{
    public function deleteLog(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $logId = (int) ($data['log_id'] ?? $_POST['log_id'] ?? 0);
        $currentUser = $this->currentUser();

        if (!$currentUser || $logId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid log id.',
            ], 422);
            return;
        }

        $deleted = (new DiaryRepository())->deleteLogForUser($logId, (int) $currentUser['id']);

        if (!$deleted) {
            $this->json([
                'success' => false,
                'message' => 'Log not found or already deleted.',
            ], 404);
            return;
        }

        $this->json([
            'success' => true,
            'deleted' => true,
            'redirect' => '/profile-diary',
        ]);
    }
}
