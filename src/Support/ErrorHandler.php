<?php

declare(strict_types=1);

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception): void {
            self::serverError($exception);
        });
    }

    public static function badRequest(string $message = 'This request could not be understood.'): void
    {
        self::render(400, 'bad_request', 'Error 400', 'Bad request', $message, 'report');
    }

    public static function forbidden(string $message = 'You do not have permission to view this page.'): void
    {
        self::render(403, 'forbidden', 'Error 403', 'Access denied', $message, 'lock');
    }

    public static function notFound(string $message = 'The page you are looking for does not exist.'): void
    {
        self::render(404, 'not_found', 'Error 404', 'Scene not found', $message, 'movie_off');
    }

    public static function serverError(?Throwable $exception = null): void
    {
        if ($exception) {
            error_log('[Reevio] Unhandled exception: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
        }

        $message = self::debugEnabled() && $exception
            ? $exception->getMessage()
            : 'Something went wrong on the Reevio server. Try again in a moment.';

        self::render(500, 'server_error', 'Error 500', 'Server error', $message, 'warning');
    }

    public static function render(
        int $status,
        string $template,
        string $eyebrow,
        string $title,
        string $message,
        string $icon
    ): void {
        if (!headers_sent()) {
            http_response_code($status);
        }

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode([
                'success' => false,
                'status' => $status,
                'error' => $title,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $view = __DIR__ . '/../../public/views/' . $template . '.html';
        if (!file_exists($view)) {
            $view = __DIR__ . '/../../public/views/error_page.html';
        }

        $errorStatus = $status;
        $errorEyebrow = $eyebrow;
        $errorTitle = $title;
        $errorMessage = $message;
        $errorIcon = $icon;
        $errorBodyClass = $template;

        include $view;
    }

    private static function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        return str_starts_with($path, 'api-') || str_contains($accept, 'application/json');
    }

    private static function debugEnabled(): bool
    {
        $debug = strtolower((string) getenv('APP_DEBUG'));
        return in_array($debug, ['1', 'true', 'yes', 'on'], true);
    }
}
