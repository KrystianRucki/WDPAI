<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/UsersRepository.php';

abstract class AppController
{
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function isDelete(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'DELETE';
    }

    protected function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $path): void
    {
        $url = sprintf('http://%s%s', $_SERVER['HTTP_HOST'], $path);
        header('Location: ' . $url);
    }

    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireLogin(): bool
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            return false;
        }

        return true;
    }

    protected function requireAdmin(): bool
    {
        if (!$this->requireLogin()) {
            return false;
        }

        if (($_SESSION['role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            $this->render('not_found', ['messages' => 'Access denied']);
            return false;
        }

        return true;
    }

    protected function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return (new UsersRepository())->getUserById((int) $_SESSION['user_id']);
    }

    protected function render(string $template, array $variables = []): void
    {
        $templatePath = __DIR__ . '/../../public/views/' . $template . '.html';
        $notFoundPath = __DIR__ . '/../../public/views/not_found.html';

        $currentUser = $variables['currentUser'] ?? $this->currentUser();
        $showAdminLink = $variables['showAdminLink'] ?? (($currentUser['role'] ?? null) === 'admin');
        $messages = $variables['messages'] ?? null;

        extract($variables, EXTR_SKIP);

        ob_start();
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            include $notFoundPath;
        }

        echo ob_get_clean();
    }
}
