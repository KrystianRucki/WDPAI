<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';

final class DashboardController extends AppController
{
    public function index(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $this->render('feed_films');
    }
}
