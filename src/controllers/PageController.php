<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ReviewsRepository.php';
require_once __DIR__ . '/../repositories/ListsRepository.php';
require_once __DIR__ . '/../repositories/DiaryRepository.php';

final class PageController extends AppController
{
    private const RELATIONS_PER_PAGE = 10;
    private const USER_COLLECTIONS_PER_PAGE = 10;

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
        $reviewsRepository = new ReviewsRepository();
        $listsRepository = new ListsRepository();
        $diaryRepository = new DiaryRepository();

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
            'profile_p_diary' => $this->diaryVariables($currentUser, $diaryRepository),
            'profile_p_lists' => $this->userListsVariables($currentUser, $listsRepository),
            'log_details' => $this->logDetailsVariables($currentUser, $diaryRepository),
            'list_details' => $this->listDetailsVariables($currentUser, $listsRepository),
            'add_to_list' => $this->addToListVariables($currentUser, $filmsRepository, $listsRepository),
            'followers' => $this->relationshipVariables('followers', $currentUser, $usersRepository),
            'following' => $this->relationshipVariables('following', $currentUser, $usersRepository),
            'users_films' => $this->userFilmsVariables($currentUser, $filmsRepository),
            'users_reviews' => $this->userReviewsVariables($currentUser, $reviewsRepository),
            'users_lists' => $this->userListsVariables($currentUser, $listsRepository),
            'users_watchlist' => $this->userWatchlistVariables($currentUser, $filmsRepository),
            default => [],
        };
    }



    private function addToListVariables(array $currentUser, FilmsRepository $filmsRepository, ListsRepository $listsRepository): array
    {
        $filmId = max(0, (int) ($_GET['film_id'] ?? $_GET['id'] ?? 0));
        $userId = (int) $currentUser['id'];
        $film = $filmId > 0 ? $filmsRepository->getById($filmId) : null;

        return [
            'profileUser' => $currentUser,
            'film' => $film,
            'filmId' => $filmId,
            'userLists' => $filmId > 0 ? $listsRepository->getUserListsForFilm($userId, $filmId) : [],
        ];
    }

    private function diaryVariables(array $currentUser, DiaryRepository $diaryRepository): array
    {
        $userId = (int) $currentUser['id'];

        return [
            'profileUser' => $currentUser,
            'diaryEntries' => $diaryRepository->getUserEntries($userId, 50),
            'diaryTotal' => $diaryRepository->countUserEntries($userId),
        ];
    }

    private function logDetailsVariables(array $currentUser, DiaryRepository $diaryRepository): array
    {
        $logId = max(0, (int) ($_GET['id'] ?? 0));
        $userId = (int) $currentUser['id'];

        return [
            'profileUser' => $currentUser,
            'logEntry' => $logId > 0 ? $diaryRepository->getEntryForUser($logId, $userId) : null,
        ];
    }


    private function listDetailsVariables(array $currentUser, ListsRepository $listsRepository): array
    {
        $listId = max(0, (int) ($_GET['id'] ?? 0));
        $currentUserId = (int) $currentUser['id'];

        return [
            'profileUser' => $currentUser,
            'listDetails' => $listId > 0 ? $listsRepository->getListDetails($listId, $currentUserId) : null,
            'listItems' => $listId > 0 ? $listsRepository->getListItems($listId, $currentUserId) : [],
        ];
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

    private function userCollectionLimit(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = $page * self::USER_COLLECTIONS_PER_PAGE;

        return [$page, $limit];
    }

    private function userFilmsVariables(array $currentUser, FilmsRepository $filmsRepository): array
    {
        $userId = (int) $currentUser['id'];
        [$page, $limit] = $this->userCollectionLimit();
        $total = $filmsRepository->countUserFilms($userId);

        return [
            'profileUser' => $currentUser,
            'collectionType' => 'films',
            'collectionItems' => $filmsRepository->getUserFilms($userId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }

    private function userReviewsVariables(array $currentUser, ReviewsRepository $reviewsRepository): array
    {
        $userId = (int) $currentUser['id'];
        [$page, $limit] = $this->userCollectionLimit();
        $total = $reviewsRepository->countUserReviews($userId);

        return [
            'profileUser' => $currentUser,
            'collectionType' => 'reviews',
            'collectionItems' => $reviewsRepository->getUserReviews($userId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }


    private function userListsVariables(array $currentUser, ListsRepository $listsRepository): array
    {
        $userId = (int) $currentUser['id'];
        [$page, $limit] = $this->userCollectionLimit();
        $total = $listsRepository->countUserLists($userId);

        return [
            'profileUser' => $currentUser,
            'collectionType' => 'lists',
            'collectionItems' => $listsRepository->getUserLists($userId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }

    private function userWatchlistVariables(array $currentUser, FilmsRepository $filmsRepository): array
    {
        $userId = (int) $currentUser['id'];
        [$page, $limit] = $this->userCollectionLimit();
        $total = $filmsRepository->countUserWatchlist($userId);

        return [
            'profileUser' => $currentUser,
            'collectionType' => 'watchlist',
            'collectionItems' => $filmsRepository->getUserWatchlist($userId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }


}
