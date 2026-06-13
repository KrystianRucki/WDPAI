<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/FilmsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/ReviewsRepository.php';
require_once __DIR__ . '/../repositories/ListsRepository.php';
require_once __DIR__ . '/../repositories/DiaryRepository.php';
require_once __DIR__ . '/../services/TmdbService.php';

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
        $status = (int) ($variables['httpStatus'] ?? 200);

        if ($status === 403) {
            $this->renderForbidden((string) ($variables['errorMessage'] ?? 'You do not have permission to view this page.'));
            return;
        }

        if ($status === 404) {
            $this->renderNotFound((string) ($variables['errorMessage'] ?? 'The requested resource was not found.'));
            return;
        }

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
            'feed_reviews' => $this->feedReviewsVariables($reviewsRepository),
            'feed_lists' => $this->feedListsVariables($listsRepository),
            'new_from_friends' => [
                'friendLogs' => $filmsRepository->getFriendsRecentLogs($currentUserId, 30),
            ],
            'profile_p_main' => [
                'profileUser' => $currentUser,
                'profileStats' => $usersRepository->getFollowStats($currentUserId),
                'favoriteFilms' => $filmsRepository->getUserFavoriteFilms($currentUserId, 4),
                'favoriteCandidates' => $filmsRepository->getUserFilmsForFavoriteSelection($currentUserId, 100),
                'ratingDistribution' => $filmsRepository->getUserRatingDistribution($currentUserId),
                'mostCommonRating' => $filmsRepository->getUserMostCommonRating($currentUserId),
            ],
            'profile_p_diary' => $this->diaryVariables($currentUser, $diaryRepository),
            'profile_p_lists' => $this->userListsVariables($currentUser, $listsRepository),
            'profile_p_watchlist' => $this->userWatchlistVariables($currentUser, $filmsRepository),
            'profile_u_main' => $this->publicUserMainVariables($currentUser, $filmsRepository, $usersRepository, $listsRepository, $reviewsRepository),
            'profile_u_lists' => $this->publicUserListsVariables($currentUser, $usersRepository, $listsRepository),
            'profile_u_watchlist' => $this->publicUserWatchlistVariables($currentUser, $usersRepository, $filmsRepository),
            'log_details' => $this->logDetailsVariables($currentUser, $diaryRepository),
            'review_details' => $this->reviewDetailsVariables($currentUser, $reviewsRepository),
            'review_comments' => $this->reviewDetailsVariables($currentUser, $reviewsRepository),
            'list_details' => $this->listDetailsVariables($currentUser, $listsRepository),
            'add_to_list' => $this->addToListVariables($currentUser, $filmsRepository, $listsRepository),
            'crew_profile' => $this->crewProfileVariables($currentUser, $filmsRepository),
            'followers' => $this->relationshipVariables('followers', $currentUser, $usersRepository),
            'following' => $this->relationshipVariables('following', $currentUser, $usersRepository),
            'users_films' => $this->userFilmsVariables($currentUser, $filmsRepository),
            'users_reviews' => $this->userReviewsVariables($currentUser, $reviewsRepository),
            'users_lists' => $this->userListsVariables($currentUser, $listsRepository),
            'users_watchlist' => $this->userWatchlistVariables($currentUser, $filmsRepository),
            default => [],
        };
    }







    private function targetUser(array $currentUser, UsersRepository $usersRepository): ?array
    {
        $targetUserId = max(0, (int) ($_GET['id'] ?? 0));

        if ($targetUserId <= 0) {
            $targetUserId = (int) ($currentUser['id'] ?? 0);
        }

        return $targetUserId > 0 ? $usersRepository->getUserById($targetUserId) : null;
    }

    private function publicUserMainVariables(
        array $currentUser,
        FilmsRepository $filmsRepository,
        UsersRepository $usersRepository,
        ListsRepository $listsRepository,
        ReviewsRepository $reviewsRepository
    ): array {
        $publicUser = $this->targetUser($currentUser, $usersRepository);

        if (!$publicUser) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This user profile does not exist.',
            ];
        }

        $publicUserId = (int) ($publicUser['id'] ?? 0);
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        $stats = $usersRepository->getFollowStats($publicUserId);
        $stats['lists_count'] = $listsRepository->countVisibleUserLists($publicUserId, $currentUserId);
        $stats['reviews_count'] = $reviewsRepository->countPublicUserReviews($publicUserId);

        return [
            'profileUser' => $currentUser,
            'publicUser' => $publicUser,
            'profileStats' => $stats,
            'isFollowing' => $publicUserId > 0 && $publicUserId !== $currentUserId ? $usersRepository->isFollowing($currentUserId, $publicUserId) : false,
            'favoriteFilms' => $filmsRepository->getUserFavoriteFilms($publicUserId, 4),
            'ratingDistribution' => $filmsRepository->getUserRatingDistribution($publicUserId),
            'mostCommonRating' => $filmsRepository->getUserMostCommonRating($publicUserId),
        ];
    }

    private function publicUserListsVariables(array $currentUser, UsersRepository $usersRepository, ListsRepository $listsRepository): array
    {
        $publicUser = $this->targetUser($currentUser, $usersRepository);

        if (!$publicUser) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This user profile does not exist.',
            ];
        }

        $publicUserId = (int) ($publicUser['id'] ?? 0);
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        [$page, $limit] = $this->userCollectionLimit();
        $total = $listsRepository->countVisibleUserLists($publicUserId, $currentUserId);

        return [
            'profileUser' => $currentUser,
            'publicUser' => $publicUser,
            'collectionType' => 'lists',
            'collectionItems' => $listsRepository->getVisibleUserLists($publicUserId, $currentUserId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }

    private function publicUserWatchlistVariables(array $currentUser, UsersRepository $usersRepository, FilmsRepository $filmsRepository): array
    {
        $publicUser = $this->targetUser($currentUser, $usersRepository);

        if (!$publicUser) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This user profile does not exist.',
            ];
        }

        $publicUserId = (int) ($publicUser['id'] ?? 0);
        [$page, $limit] = $this->userCollectionLimit();
        $total = $filmsRepository->countUserWatchlist($publicUserId);

        return [
            'profileUser' => $currentUser,
            'publicUser' => $publicUser,
            'collectionType' => 'watchlist',
            'collectionItems' => $filmsRepository->getUserWatchlist($publicUserId, $limit),
            'collectionTotal' => $total,
            'collectionPage' => $page,
            'collectionLimit' => $limit,
            'collectionHasMore' => $limit < $total,
            'collectionNextPage' => $page + 1,
        ];
    }

    private function feedListsVariables(ListsRepository $listsRepository): array
    {
        return [
            'publicLists' => $listsRepository->getPublicLists(40),
            'publicListsTotal' => $listsRepository->countPublicLists(),
        ];
    }

    private function feedReviewsVariables(ReviewsRepository $reviewsRepository): array
    {
        $filmId = max(0, (int) ($_GET['film_id'] ?? 0));
        $userId = max(0, (int) ($_GET['user_id'] ?? 0));

        return [
            'reviews' => $reviewsRepository->feed(40, $filmId > 0 ? $filmId : null, $userId > 0 ? $userId : null),
            'filterFilmId' => $filmId,
            'filterUserId' => $userId,
        ];
    }

    private function reviewDetailsVariables(array $currentUser, ReviewsRepository $reviewsRepository): array
    {
        $reviewId = max(0, (int) ($_GET['id'] ?? $_GET['review_id'] ?? 0));
        $review = $reviewId > 0 ? $reviewsRepository->getReviewDetails($reviewId, (int) $currentUser['id']) : null;

        if (!$review) {
            return [
                'httpStatus' => $reviewId > 0 && $reviewsRepository->reviewExists($reviewId) ? 403 : 404,
                'errorMessage' => $reviewId > 0 && $reviewsRepository->reviewExists($reviewId)
                    ? 'This review is private or unavailable.'
                    : 'This review does not exist.',
            ];
        }

        return [
            'review' => $review,
            'reviewComments' => $reviewsRepository->getReviewComments((int) $review['review_id'], (int) $currentUser['id']),
        ];
    }

    private function crewProfileVariables(array $currentUser, FilmsRepository $filmsRepository): array
    {
        $personId = max(0, (int) ($_GET['id'] ?? 0));
        $person = $personId > 0 ? $filmsRepository->getPersonById($personId) : null;
        $tmdbPersonFilms = [];
        $tmdbError = null;

        if ($person) {
            try {
                $tmdb = new TmdbService();
                $details = $this->resolveTmdbPersonDetails($person, $tmdb);

                if ($details) {
                    $person = $filmsRepository->updatePersonFromTmdbDetails($personId, $details, $tmdb) ?: $person;
                    $tmdbPersonFilms = $this->tmdbPersonMovieCredits($details, $tmdb);
                }
            } catch (Throwable $exception) {
                $tmdbError = $exception->getMessage();
            }
        }

        $localFilms = $personId > 0 ? $filmsRepository->getPersonFilmography($personId, 40) : [];

        return [
            'profileUser' => $currentUser,
            'person' => $person,
            'personFilms' => $tmdbPersonFilms ?: $localFilms,
            'personFilmsSource' => $tmdbPersonFilms ? 'tmdb' : 'local',
            'tmdbError' => $tmdbError,
        ];
    }


    private function resolveTmdbPersonDetails(array $person, TmdbService $tmdb): ?array
    {
        $name = trim((string) ($person['full_name'] ?? ''));
        $tmdbId = (int) ($person['tmdb_id'] ?? 0);

        if ($name !== '') {
            try {
                $response = $tmdb->searchPeople($name);
                $best = $this->bestTmdbPersonMatch($person, $response['results'] ?? []);

                if ($best && !empty($best['tmdb_id'])) {
                    $tmdbId = (int) $best['tmdb_id'];
                }
            } catch (Throwable) {
                // If person search is temporarily unavailable, fall back to existing tmdb_id.
            }
        }

        if ($tmdbId <= 0) {
            return null;
        }

        return $tmdb->getPersonDetails($tmdbId);
    }

    private function bestTmdbPersonMatch(array $person, array $results): ?array
    {
        if (!$results) {
            return null;
        }

        $localName = mb_strtolower(trim((string) ($person['full_name'] ?? '')));
        $localDepartment = mb_strtolower(trim((string) ($person['known_for_department'] ?? '')));

        $departmentAliases = [
            'director' => 'directing',
            'directing' => 'directing',
            'actor' => 'acting',
            'actors' => 'acting',
            'acting' => 'acting',
            'writer' => 'writing',
            'writing' => 'writing',
            'composer' => 'sound',
            'sound' => 'sound',
        ];

        $expectedDepartment = $departmentAliases[$localDepartment] ?? $localDepartment;

        usort($results, static function (array $a, array $b) use ($localName, $expectedDepartment): int {
            $aName = mb_strtolower(trim((string) ($a['name'] ?? '')));
            $bName = mb_strtolower(trim((string) ($b['name'] ?? '')));
            $aDepartment = mb_strtolower(trim((string) ($a['known_for_department'] ?? '')));
            $bDepartment = mb_strtolower(trim((string) ($b['known_for_department'] ?? '')));

            $aScore = 0;
            $bScore = 0;

            if ($aName === $localName) {
                $aScore += 100;
            }

            if ($bName === $localName) {
                $bScore += 100;
            }

            if ($expectedDepartment !== '' && $aDepartment === $expectedDepartment) {
                $aScore += 20;
            }

            if ($expectedDepartment !== '' && $bDepartment === $expectedDepartment) {
                $bScore += 20;
            }

            $aScore += (int) round((float) ($a['popularity'] ?? 0));
            $bScore += (int) round((float) ($b['popularity'] ?? 0));

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }

    private function tmdbPersonMovieCredits(array $details, TmdbService $tmdb): array
    {
        $credits = $details['movie_credits'] ?? [];
        $items = [];

        foreach (($credits['cast'] ?? []) as $movie) {
            $items[(int) ($movie['id'] ?? 0)] = $this->normalizeTmdbPersonMovie($movie, $tmdb, 'actor');
        }

        foreach (($credits['crew'] ?? []) as $movie) {
            $id = (int) ($movie['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            if (isset($items[$id])) {
                $items[$id]['job'] = $movie['job'] ?? $items[$id]['job'] ?? null;
                $items[$id]['department'] = $movie['department'] ?? $items[$id]['department'] ?? null;
                $items[$id]['credit_type'] = trim(($items[$id]['credit_type'] ?? 'actor') . ', ' . strtolower((string) ($movie['job'] ?? 'crew')), ', ');
                continue;
            }

            $items[$id] = $this->normalizeTmdbPersonMovie($movie, $tmdb, strtolower((string) ($movie['job'] ?? 'crew')));
        }

        $items = array_values(array_filter($items, fn (array $movie): bool => (int) ($movie['tmdb_id'] ?? 0) > 0));

        usort($items, static function (array $a, array $b): int {
            $aYear = (int) ($a['release_year'] ?? 0);
            $bYear = (int) ($b['release_year'] ?? 0);

            if ($aYear !== $bYear) {
                return $bYear <=> $aYear;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $items;
    }

    private function normalizeTmdbPersonMovie(array $movie, TmdbService $tmdb, string $creditType): array
    {
        $releaseDate = (string) ($movie['release_date'] ?? '');
        $releaseYear = $releaseDate !== '' ? (int) substr($releaseDate, 0, 4) : null;

        return [
            'id' => null,
            'tmdb_id' => (int) ($movie['id'] ?? 0),
            'title' => $movie['title'] ?? $movie['original_title'] ?? 'Untitled',
            'release_year' => $releaseYear,
            'poster_url' => $tmdb->imageUrl($movie['poster_path'] ?? null, 'w342') ?? '/public/assets/img/movie_placeholder.svg',
            'tmdb_vote_average' => null,
            'average_rating' => null,
            'credit_type' => $creditType,
            'character_name' => $movie['character'] ?? null,
            'job' => $movie['job'] ?? null,
            'department' => $movie['department'] ?? null,
        ];
    }

    private function addToListVariables(array $currentUser, FilmsRepository $filmsRepository, ListsRepository $listsRepository): array
    {
        $filmId = max(0, (int) ($_GET['film_id'] ?? $_GET['id'] ?? 0));
        $userId = (int) $currentUser['id'];
        $film = $filmId > 0 ? $filmsRepository->getById($filmId) : null;

        if ($filmId <= 0 || !$film) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This film does not exist.',
            ];
        }

        return [
            'profileUser' => $currentUser,
            'film' => $film,
            'filmId' => $filmId,
            'userLists' => $listsRepository->getUserListsForFilm($userId, $filmId),
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
        $logEntry = $logId > 0 ? $diaryRepository->getEntryForUser($logId, $userId) : null;

        if (!$logEntry) {
            return [
                'httpStatus' => $logId > 0 && $diaryRepository->entryExists($logId) ? 403 : 404,
                'errorMessage' => $logId > 0 && $diaryRepository->entryExists($logId)
                    ? 'This log belongs to another user.'
                    : 'This log does not exist.',
            ];
        }

        return [
            'profileUser' => $currentUser,
            'logEntry' => $logEntry,
        ];
    }

    private function listDetailsVariables(array $currentUser, ListsRepository $listsRepository): array
    {
        $listId = max(0, (int) ($_GET['id'] ?? 0));
        $currentUserId = (int) $currentUser['id'];

        $listDetails = $listId > 0 ? $listsRepository->getListDetails($listId, $currentUserId) : null;

        if (!$listDetails) {
            return [
                'httpStatus' => $listId > 0 && $listsRepository->listExists($listId) ? 403 : 404,
                'errorMessage' => $listId > 0 && $listsRepository->listExists($listId)
                    ? 'This list is private or unavailable.'
                    : 'This list does not exist.',
            ];
        }

        return [
            'profileUser' => $currentUser,
            'listDetails' => $listDetails,
            'listItems' => $listsRepository->getListItems($listId, $currentUserId),
        ];
    }

    private function relationshipVariables(string $type, array $currentUser, UsersRepository $usersRepository): array
    {
        $currentUserId = (int) $currentUser['id'];
        $targetUserId = max(0, (int) ($_GET['id'] ?? 0));
        if ($targetUserId <= 0) {
            $targetUserId = $currentUserId;
        }

        $targetUser = $usersRepository->getUserById($targetUserId);

        if (!$targetUser) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This user profile does not exist.',
            ];
        }

        $targetUserId = (int) ($targetUser['id'] ?? $currentUserId);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = self::RELATIONS_PER_PAGE;
        $offset = ($page - 1) * $limit;

        if ($type === 'followers') {
            $total = $usersRepository->countFollowers($targetUserId);
            $users = $usersRepository->getFollowers($targetUserId, $currentUserId, $limit, $offset);
        } else {
            $total = $usersRepository->countFollowing($targetUserId);
            $users = $usersRepository->getFollowing($targetUserId, $currentUserId, $limit, $offset);
        }

        return [
            'profileUser' => $currentUser,
            'relationTargetUser' => $targetUser,
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
        $targetUserId = max(0, (int) ($_GET['id'] ?? 0));
        if ($targetUserId <= 0) {
            $targetUserId = (int) $currentUser['id'];
        }

        $targetUser = $targetUserId === (int) $currentUser['id']
            ? $currentUser
            : ((new UsersRepository())->getUserById($targetUserId));

        if (!$targetUser) {
            return [
                'httpStatus' => 404,
                'errorMessage' => 'This user profile does not exist.',
            ];
        }

        $userId = (int) ($targetUser['id'] ?? $currentUser['id']);
        [$page, $limit] = $this->userCollectionLimit();
        $total = $filmsRepository->countUserFilms($userId);

        return [
            'profileUser' => $targetUser,
            'collectionOwnerId' => $userId,
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
