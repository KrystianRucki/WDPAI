<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/ReviewsRepository.php';

final class ReviewsController extends AppController
{
    public function comment(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $reviewId = (int) ($data['review_id'] ?? 0);
        $content = trim($data['content'] ?? '');

        if ($reviewId <= 0 || $content === '') {
            $this->json(['error' => 'Review and comment content are required'], 422);
            return;
        }

        $commentId = (new ReviewsRepository())->comment($reviewId, (int) $_SESSION['user_id'], $content);
        $this->json(['created' => true, 'comment_id' => $commentId], 201);
    }

    public function like(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $reviewId = (int) ($data['review_id'] ?? 0);

        if ($reviewId <= 0) {
            $this->json(['error' => 'Review id is required'], 422);
            return;
        }

        $liked = (new ReviewsRepository())->toggleLike($reviewId, (int) $_SESSION['user_id']);
        $this->json(['liked' => $liked]);
    }
}
