<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class SecurityController extends AppController
{
    public function login(): void
    {
        if (!$this->isPost()) {
            $this->render('login');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->render('login', ['messages' => 'Fill all fields']);
            return;
        }

        $usersRepository = new UsersRepository();
        $user = $usersRepository->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', ['messages' => 'Invalid email or password']);
            return;
        }

        if (!$user['is_active']) {
            $this->render('login', ['messages' => 'Your account is blocked. Contact administrator.']);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $this->redirect('/feed-films');
    }

    public function register(): void
    {
        if (!$this->isPost()) {
            $this->render('register');
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($username === '' || $email === '' || $password === '' || $password2 === '') {
            $this->render('register', ['messages' => 'Fill all fields']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', ['messages' => 'Invalid email address']);
            return;
        }

        if ($password !== $password2) {
            $this->render('register', ['messages' => 'Passwords are not the same']);
            return;
        }

        $usersRepository = new UsersRepository();
        if ($usersRepository->getUserByEmail($email) || $usersRepository->getUserByUsername($username)) {
            $this->render('register', ['messages' => 'User already exists']);
            return;
        }

        $usersRepository->createUser(
            $username,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            'user'
        );

        $this->redirect('/login');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('/login');
    }
}
