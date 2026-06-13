<?php

declare(strict_types=1);

final class ErrorHandler
{
    public static function register(): void
    {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_exception_handler([self::class, 'handleException']);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $exception): void
    {
        error_log(sprintf(
            'Unhandled exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        self::renderInternalError();
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
            return;
        }

        error_log(sprintf(
            'Fatal error: %s in %s:%d',
            $error['message'] ?? 'unknown error',
            $error['file'] ?? 'unknown file',
            $error['line'] ?? 0
        ));

        self::renderInternalError();
    }

    private static function renderInternalError(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);

        if (self::expectsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $template = __DIR__ . '/../public/views/internal_error.html';
        if (file_exists($template)) {
            include $template;
            return;
        }

        echo 'Internal server error';
    }

    private static function expectsJson(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_starts_with($path, '/api') || str_contains($accept, 'application/json');
    }
}
