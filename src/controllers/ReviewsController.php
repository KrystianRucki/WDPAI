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
        $parentId = isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null;
        $content = trim((string) ($data['content'] ?? ''));

        if ($reviewId <= 0 || $content === '') {
            $this->json(['success' => false, 'message' => 'Review and comment content are required.'], 422);
            return;
        }

        $commentId = (new ReviewsRepository())->comment($reviewId, (int) $_SESSION['user_id'], $content, $parentId);

        $this->json([
            'success' => true,
            'created' => true,
            'comment_id' => $commentId,
            'message' => 'Comment posted.',
        ], 201);
    }

    public function like(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $reviewId = (int) ($data['review_id'] ?? 0);

        if ($reviewId <= 0) {
            $this->json(['success' => false, 'message' => 'Review id is required.'], 422);
            return;
        }

        $result = (new ReviewsRepository())->toggleLike($reviewId, (int) $_SESSION['user_id']);

        $this->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function commentLike(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $data = $this->getJsonInput();
        $commentId = (int) ($data['comment_id'] ?? 0);

        if ($commentId <= 0) {
            $this->json(['success' => false, 'message' => 'Comment id is required.'], 422);
            return;
        }

        $result = (new ReviewsRepository())->toggleCommentLike($commentId, (int) $_SESSION['user_id']);

        $this->json([
            'success' => true,
            ...$result,
        ]);
    }
}
