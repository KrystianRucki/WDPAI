<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class PageController extends AppController
{
    private const RELATIONS_PER_PAGE = 10;

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

        $currentUserId = (int) $currentUser['id'];
        $filmsRepository = new FilmsRepository();
        $usersRepository = new UsersRepository();

        return match ($template) {
            'feed_films' => [
                'popularFilms' => $filmsRepository->getPopularThisWeek(4),
                'friendLogs' => $filmsRepository->getFriendsRecentLogs($currentUserId, 3),
            ],
            'new_from_friends' => [
                'friendLogs' => $filmsRepository->getFriendsRecentLogs($currentUserId, 30),
            ],
            'profile_p_main' => [
                'profileUser' => $currentUser,
                'profileStats' => $usersRepository->getFollowStats($currentUserId),
                'favoriteFilms' => $filmsRepository->getUserFavoriteFilms($currentUserId, 4),
                'ratingDistribution' => $filmsRepository->getUserRatingDistribution($currentUserId),
                'mostCommonRating' => $filmsRepository->getUserMostCommonRating($currentUserId),
            ],
            'followers' => $this->relationshipVariables('followers', $currentUser, $usersRepository),
            'following' => $this->relationshipVariables('following', $currentUser, $usersRepository),
            default => [],
        };
    }

    private function relationshipVariables(string $type, array $currentUser, UsersRepository $usersRepository): array
    {
        $currentUserId = (int) $currentUser['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = self::RELATIONS_PER_PAGE;
        $offset = ($page - 1) * $limit;

        if ($type === 'followers') {
            $total = $usersRepository->countFollowers($currentUserId);
            $users = $usersRepository->getFollowers($currentUserId, $currentUserId, $limit, $offset);
        } else {
            $total = $usersRepository->countFollowing($currentUserId);
            $users = $usersRepository->getFollowing($currentUserId, $currentUserId, $limit, $offset);
        }

        return [
            'profileUser' => $currentUser,
            'relationType' => $type,
            'relationUsers' => $users,
            'relationTotal' => $total,
            'relationPage' => $page,
            'relationPerPage' => $limit,
            'relationTotalPages' => max(1, (int) ceil($total / $limit)),
        ];
    }
}
