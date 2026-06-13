<?php

declare(strict_types=1);

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

final class SecurityController extends AppController
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 60;
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_USERNAME_LENGTH = 50;
    private const MAX_PASSWORD_LENGTH = 72;

    public function login(): void
    {
        if (!$this->isPost()) {
            $this->render('login');
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->render('login', ['messages' => 'Session expired. Refresh the page and try again.']);
            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->render('login', ['messages' => 'Fill all fields']);
            return;
        }

        if (!$this->isLoginInputLengthValid($email, $password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('login', ['messages' => 'Invalid email or password']);
            return;
        }

        $lockSeconds = $this->remainingLoginLockSeconds($email);
        if ($lockSeconds > 0) {
            http_response_code(429);
            $this->render('login', ['messages' => 'Too many failed login attempts. Try again in ' . $lockSeconds . ' seconds.']);
            return;
        }

        $usersRepository = new UsersRepository();
        $user = $usersRepository->getAuthenticationUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailedLogin($usersRepository, $email, $user);
            $this->render('login', ['messages' => 'Invalid email or password']);
            return;
        }

        if (!$user['is_active']) {
            $this->recordFailedLogin($usersRepository, $email, $user);
            $this->render('login', ['messages' => 'Your account is blocked. Contact administrator.']);
            return;
        }

        $this->clearFailedLoginAttempts($email);
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

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->render('register', ['messages' => 'Session expired. Refresh the page and try again.']);
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');

        if ($username === '' || $email === '' || $password === '' || $password2 === '') {
            $this->render('register', ['messages' => 'Fill all fields']);
            return;
        }

        if (!$this->isRegisterInputLengthValid($username, $email, $password, $password2)) {
            $this->render('register', ['messages' => 'Input is too long']);
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

        $passwordError = $this->passwordComplexityError($password);
        if ($passwordError !== null) {
            $this->render('register', ['messages' => $passwordError]);
            return;
        }

        $usersRepository = new UsersRepository();
        if ($usersRepository->getUserByEmail($email) || $usersRepository->getUserByUsername($username)) {
            $this->render('register', ['messages' => 'Registration data is invalid']);
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

    private function isLoginInputLengthValid(string $email, string $password): bool
    {
        return strlen($email) <= self::MAX_EMAIL_LENGTH
            && strlen($password) <= self::MAX_PASSWORD_LENGTH;
    }

    private function isRegisterInputLengthValid(string $username, string $email, string $password, string $password2): bool
    {
        return strlen($username) <= self::MAX_USERNAME_LENGTH
            && strlen($email) <= self::MAX_EMAIL_LENGTH
            && strlen($password) <= self::MAX_PASSWORD_LENGTH
            && strlen($password2) <= self::MAX_PASSWORD_LENGTH;
    }

    private function passwordComplexityError(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must have at least 8 characters.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain a lowercase letter.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain an uppercase letter.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Password must contain a number.';
        }

        return null;
    }

    private function remainingLoginLockSeconds(string $email): int
    {
        $key = $this->loginAttemptKey($email);
        $attempt = $_SESSION['failed_login_attempts'][$key] ?? null;

        if (!is_array($attempt)) {
            return 0;
        }

        $lockedUntil = (int) ($attempt['locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            return $lockedUntil - time();
        }

        if ($lockedUntil > 0) {
            unset($_SESSION['failed_login_attempts'][$key]);
        }

        return 0;
    }

    private function recordFailedLogin(UsersRepository $usersRepository, string $email, ?array $user): void
    {
        $key = $this->loginAttemptKey($email);
        $attempt = $_SESSION['failed_login_attempts'][$key] ?? ['count' => 0, 'locked_until' => 0];
        $attempt['count'] = (int) ($attempt['count'] ?? 0) + 1;

        if ($attempt['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $attempt['locked_until'] = time() + self::LOGIN_LOCK_SECONDS;
        }

        $_SESSION['failed_login_attempts'][$key] = $attempt;

        $usersRepository->logAudit(
            isset($user['id']) ? (int) $user['id'] : null,
            'failed_login',
            [
                'email' => $email,
                'ip' => $this->clientIp(),
                'attempts' => $attempt['count'],
                'locked' => ((int) ($attempt['locked_until'] ?? 0)) > time(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
    }

    private function clearFailedLoginAttempts(string $email): void
    {
        unset($_SESSION['failed_login_attempts'][$this->loginAttemptKey($email)]);
    }

    private function loginAttemptKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
    }
}
