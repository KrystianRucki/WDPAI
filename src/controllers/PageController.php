<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';

final class PageController extends AppController
{
    public function show(string $template, bool $public = false): void
    {
        if (!$public && !$this->requireLogin()) {
            return;
        }

        $variables = $this->variablesForTemplate($template, $public);

        $this->render($template, $variables);
    }

    private function variablesForTemplate(string $template, bool $public): array
    {
        if ($public) {
            return [];
        }

        $currentUser = $this->currentUser();

        if (!$currentUser) {
            return [];
        }

        $filmsRepository = new FilmsRepository();

        return match ($template) {
            'feed_films' => [
                'popularFilms' => $filmsRepository->getPopularThisWeek(4),
                'friendLogs' => $filmsRepository->getFriendsRecentLogs((int) $currentUser['id'], 3),
            ],
            'new_from_friends' => [
                'friendLogs' => $filmsRepository->getFriendsRecentLogs((int) $currentUser['id'], 30),
            ],
            default => [],
        };
    }
}
