<?php

declare(strict_types=1);

session_start();

require_once 'Routing.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
Routing::run($path);
