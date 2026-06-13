<?php

declare(strict_types=1);

require_once __DIR__ . '/src/controllers/AppController.php';
require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/PageController.php';
require_once __DIR__ . '/src/controllers/AdminController.php';
require_once __DIR__ . '/src/controllers/SearchController.php';
require_once __DIR__ . '/src/controllers/UsersController.php';
require_once __DIR__ . '/src/controllers/ListsController.php';
require_once __DIR__ . '/src/controllers/ReviewsController.php';
require_once __DIR__ . '/src/controllers/NotificationsController.php';
require_once __DIR__ . '/src/controllers/SettingsController.php';
require_once __DIR__ . '/src/controllers/TmdbController.php';
require_once __DIR__ . '/src/controllers/DiaryController.php';
require_once __DIR__ . '/src/Support/ErrorHandler.php';

final class Routing
{
    private const PUBLIC_VIEWS = [
        'login',
        'register',
        'bad_request',
        'not_found',
        'forbidden',
        'server_error',
        'offline_page',
    ];

    private const VIEW_ALIASES = [
        '' => 'login',
        'dashboard' => 'feed_films',
        'feed-films' => 'feed_films',
        'feed-reviews' => 'feed_reviews',
        'feed-lists' => 'feed_lists',
        'bad-request' => 'bad_request',
        'offline-page' => 'offline_page',
        'server-error' => 'server_error',
        'film-details' => 'film_details',
        'review-details' => 'review_details',
        'review-comments' => 'review_comments',
        'list-details' => 'list_details',
        'new-from-friends' => 'new_from_friends',
        'activity-following' => 'activity_following',
        'activity-you' => 'activity_you',
        'profile' => 'profile_p_main',
        'profile-p-profile' => 'profile_p_main',
        'profile-diary' => 'profile_p_diary',
        'profile-lists' => 'profile_p_lists',
        'profile-watchlist' => 'profile_p_watchlist',
        'profile-u-main' => 'profile_u_main',
        'profile-u-lists' => 'profile_u_lists',
        'profile-u-watchlist' => 'profile_u_watchlist',
        'settings' => 'settings',
        'notifications' => 'notifications',
        'search' => 'search_empty',
        'search-films' => 'search_films',
        'search-users' => 'search_users',
        'search-lists' => 'search_lists',
        'search-crew' => 'search_crew',
        'crew-profile' => 'crew_profile',
        'log-search' => 'log_search_empty',
        'log-search-results' => 'log_search_results',
        'log-selected' => 'log_selected',
        'log-details' => 'log_details',
        'calendar' => 'calendar',
        'admin-panel' => 'admin_panel',
    ];

    public static function run(string $path): void
    {
        if (!self::isSafePath($path)) {
            ErrorHandler::badRequest('The requested URL contains invalid characters.');
            return;
        }

        $path = self::normalizePath($path);

        if (in_array($path, ['bad-request', '400'], true)) {
            ErrorHandler::badRequest('This request could not be understood.');
            return;
        }

        if (in_array($path, ['forbidden', '403'], true)) {
            ErrorHandler::forbidden();
            return;
        }

        if (in_array($path, ['not-found', '404'], true)) {
            ErrorHandler::notFound();
            return;
        }

        if (in_array($path, ['server-error', '500'], true)) {
            ErrorHandler::serverError();
            return;
        }

        $routes = [
            'login' => [SecurityController::class, 'login'],
            'register' => [SecurityController::class, 'register'],
            'logout' => [SecurityController::class, 'logout'],

            'admin-panel' => [AdminController::class, 'index'],
            'api-admin-users' => [AdminController::class, 'users'],
            'api-admin-block-user' => [AdminController::class, 'blockUser'],

            'api-tmdb-search-movies' => [TmdbController::class, 'searchMoviesApi'],
            'api-tmdb-search-people' => [TmdbController::class, 'searchPeopleApi'],
            'api-tmdb-cache-genres' => [TmdbController::class, 'cacheGenresApi'],
            'log-search-results' => [TmdbController::class, 'logSearchResults'],
            'log-selected' => [TmdbController::class, 'logSelected'],
            'film-details' => [TmdbController::class, 'filmDetails'],

            'search' => [SearchController::class, 'index'],
            'search-films' => [SearchController::class, 'filmsPage'],
            'search-crew' => [SearchController::class, 'crewPage'],
            'search-users' => [SearchController::class, 'usersPage'],
            'search-lists' => [SearchController::class, 'listsPage'],

            'api-search' => [SearchController::class, 'search'],
            'api-search-users' => [SearchController::class, 'users'],
            'api-users-follow' => [UsersController::class, 'toggleFollow'],
            'api-notifications' => [NotificationsController::class, 'list'],
            'api-notifications-read' => [NotificationsController::class, 'markRead'],
            'api-settings-profile' => [SettingsController::class, 'updateProfile'],
            'api-settings-avatar' => [SettingsController::class, 'uploadAvatar'],
            'api-settings-notifications' => [SettingsController::class, 'updateNotifications'],
            'api-profile-favorites' => [SettingsController::class, 'saveFavorites'],
            'api-lists-create' => [ListsController::class, 'create'],
            'api-lists-add-film' => [ListsController::class, 'addFilm'],
            'api-lists-reorder' => [ListsController::class, 'reorder'],
            'api-lists-remove-film' => [ListsController::class, 'removeFilm'],
            'api-lists-delete' => [ListsController::class, 'delete'],
            'api-watchlist-remove' => [TmdbController::class, 'removeFromWatchlist'],
            'api-watchlist-add' => [TmdbController::class, 'addToWatchlist'],
            'api-film-mark-watched' => [TmdbController::class, 'markWatchedOnly'],
            'api-reviews-comment' => [ReviewsController::class, 'comment'],
            'api-reviews-like' => [ReviewsController::class, 'like'],
            'api-review-comments-like' => [ReviewsController::class, 'commentLike'],
            'api-diary-delete-log' => [DiaryController::class, 'deleteLog'],
            'api-log-save' => [DiaryController::class, 'saveLog'],

            // Backwards-compatible routes from the original class baseline.
            'delete-user' => [UsersController::class, 'delete'],
            'search-users-legacy' => [UsersController::class, 'search'],
        ];

        if (isset($routes[$path])) {
            [$controllerClass, $action] = $routes[$path];
            (new $controllerClass())->$action();
            return;
        }

        $template = self::VIEW_ALIASES[$path] ?? self::templateFromPath($path);

        if ($template !== null && file_exists(__DIR__ . '/public/views/' . $template . '.html')) {
            if ($template === 'admin_panel') {
                (new AdminController())->index();
                return;
            }

            $isPublic = in_array($template, self::PUBLIC_VIEWS, true);
            (new PageController())->show($template, $isPublic);
            return;
        }

        ErrorHandler::notFound('The requested route does not exist.');
    }

    private static function isSafePath(string $path): bool
    {
        $decoded = rawurldecode($path);

        if (preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
            return false;
        }

        if (str_contains($decoded, '..')) {
            return false;
        }

        return true;
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path, '/');

        if (str_ends_with($path, '.html')) {
            $path = substr($path, 0, -5);
        }

        return str_replace('_', '-', $path);
    }

    private static function templateFromPath(string $path): ?string
    {
        if ($path === '') {
            return 'login';
        }

        $candidate = str_replace('-', '_', $path);
        return preg_match('/^[a-z0-9_]+$/', $candidate) ? $candidate : null;
    }
}
