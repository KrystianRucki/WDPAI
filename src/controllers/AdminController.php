<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class AdminController extends AppController
{
    private UsersRepository $usersRepository;

    public function __construct()
    {
        $this->usersRepository = new UsersRepository();
    }

    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $this->render('admin_panel', ['showAdminLink' => true]);
    }

    public function users(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $search = trim($_GET['search'] ?? '');
        $offset = ($page - 1) * $limit;

        $total = $this->usersRepository->countUsers($search);
        $users = $this->usersRepository->listPaginated($search, $limit, $offset);

        $this->json([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
            'stats' => [
                'total' => $this->usersRepository->countUsers(),
                'active' => $this->usersRepository->countActiveUsers(),
                'blocked' => $this->usersRepository->countBlockedUsers(),
            ],
        ]);
    }

    public function blockUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $data = $this->getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);
        $blocked = (bool) ($data['blocked'] ?? true);

        if ($userId <= 0) {
            $this->json(['error' => 'Invalid user id'], 422);
            return;
        }

        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            $this->json(['error' => 'You cannot block your own account'], 422);
            return;
        }

        $updated = $this->usersRepository->setBlocked($userId, $blocked);
        $this->json(['updated' => $updated]);
    }
}
