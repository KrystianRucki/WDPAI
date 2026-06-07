<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController {

    public function login() {
        if (!$this->isPost()) {
            return $this->render('login');
        }

        $email = trim($_POST["email"] ?? '');
        $password = $_POST["password"] ?? '';

        if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Fill all fields']);
        }

        $userRepository = new UsersRepository();
        $user = $userRepository->getUserByEmail($email);

        if (!$user) {
            return $this->render('login', ['messages' => 'User not found']);
        }

        if (!password_verify($password, $user['password'])) {
            return $this->render('login', ['messages' => 'Wrong password']);
        }

        // TODO: CREATE USER SESSION AND REDIRECT TO DASHBOARD
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        return;
    }

    public function register() {
        $userRepository = new UsersRepository();

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            $firstName = trim($_POST['firstName'] ?? '');
            $lastName = trim($_POST['lastName'] ?? '');

            if (empty($email) || empty($password) || empty($password2) || empty($firstName) || empty($lastName)) {
                return $this->render('register', ['messages' => 'Fill all fields']);
            }

            if ($password !== $password2) {
                return $this->render('register', ['messages' => 'Passwords are not the same']);
            }

            $user = $userRepository->getUserByEmail($email);
            if ($user) {
                return $this->render('register', ['messages' => 'User exists']);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $username = $firstName;
            $fullName = $firstName . ' ' . $lastName;

            $userRepository->createUser($username, $email, $hashedPassword, $fullName);

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            return;
        }

        return $this->render("register");
    }
}
