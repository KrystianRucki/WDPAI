<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/src/Support/ErrorHandler.php';
require_once __DIR__ . '/Routing.php';

ErrorHandler::register();

try {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
    Routing::run($path);
} catch (Throwable $exception) {
    ErrorHandler::serverError($exception);
}
