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

    public function saveLog(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $currentUser = $this->currentUser();
        $data = $this->getJsonInput();

        $filmId = (int) ($data['film_id'] ?? 0);
        $watchedOn = trim((string) ($data['watched_on'] ?? ''));
        $rating = $data['rating'] ?? null;
        $isRewatch = (bool) ($data['is_rewatch'] ?? false);
        $review = trim((string) ($data['review'] ?? ''));

        if (!$currentUser || $filmId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid film id.',
            ], 422);
            return;
        }

        if ($watchedOn === '') {
            $watchedOn = date('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $watchedOn);
        if (!$date || $date->format('Y-m-d') !== $watchedOn) {
            $this->json([
                'success' => false,
                'message' => 'Invalid watched date.',
            ], 422);
            return;
        }

        $ratingValue = null;
        if ($rating !== null && $rating !== '') {
            $ratingValue = round((float) $rating * 2) / 2;

            if ($ratingValue < 0.5 || $ratingValue > 5.0) {
                $this->json([
                    'success' => false,
                    'message' => 'Rating must be between 0.5 and 5.0.',
                ], 422);
                return;
            }
        }

        try {
            $result = (new DiaryRepository())->saveFilmLog(
                (int) $currentUser['id'],
                $filmId,
                $watchedOn,
                $ratingValue,
                $isRewatch,
                $review
            );

            $this->json([
                'success' => true,
                ...$result,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'success' => false,
                'message' => 'Could not save log.',
            ], 500);
        }
    }

}
