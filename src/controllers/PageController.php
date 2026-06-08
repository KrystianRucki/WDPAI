<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';

final class PageController extends AppController
{
    public function show(string $template, bool $public = false): void
    {
        if (!$public && !$this->requireLogin()) {
            return;
        }

        $this->render($template);
    }
}
