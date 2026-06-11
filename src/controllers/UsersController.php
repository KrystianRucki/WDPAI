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

    public function toggleFollow(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $currentUser = $this->currentUser();
        $data = $this->getJsonInput();

        $targetUserId = (int) ($data['user_id'] ?? 0);
        $shouldFollow = $data['follow'] ?? null;

        if (!$currentUser || $targetUserId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid user id.'], 422);
            return;
        }

        $currentUserId = (int) $currentUser['id'];

        if ($targetUserId === $currentUserId) {
            $this->json(['success' => false, 'message' => 'You cannot follow yourself.'], 422);
            return;
        }

        $repository = new UsersRepository();
        $targetUser = $repository->getUserById($targetUserId);

        if (!$targetUser || !($targetUser['is_active'] ?? false)) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $currentlyFollowing = $repository->isFollowing($currentUserId, $targetUserId);
        $nextFollowing = is_bool($shouldFollow) ? $shouldFollow : !$currentlyFollowing;

        if ($nextFollowing) {
            $repository->followUser($currentUserId, $targetUserId);
        } else {
            $repository->unfollowUser($currentUserId, $targetUserId);
        }

        $this->json([
            'success' => true,
            'user_id' => $targetUserId,
            'following' => $repository->isFollowing($currentUserId, $targetUserId),
            'target_stats' => $repository->getFollowStats($targetUserId),
            'current_user_stats' => $repository->getFollowStats($currentUserId),
        ]);
    }


}
