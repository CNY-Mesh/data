<?php
declare(strict_types=1);

namespace App\Web\Controllers;

use App\Web\Auth;

class LoginController extends BaseController
{
    private Auth $auth;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
    }
    
    public function handle(): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'show';
        
        switch ($action) {
            case 'login':
                $this->handleLogin();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            default:
                $this->showLoginForm();
                break;
        }
    }
    
    private function handleLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showLoginForm('Invalid request method');
            return;
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $this->showLoginForm('Please enter both username and password');
            return;
        }
        
        if ($this->auth->getRemainingLockoutTime() > 0) {
            $remaining = $this->auth->getRemainingLockoutTime();
            $this->showLoginForm("Too many failed attempts. Please try again in {$remaining} seconds.");
            return;
        }
        
        if ($this->auth->login($username, $password)) {
            // Redirect to the originally requested page or dashboard
            $redirect = $_POST['redirect'] ?? '/?r=dashboard';
            header("Location: $redirect");
            exit;
        } else {
            $this->showLoginForm('Invalid username or password');
        }
    }
    
    private function handleLogout(): void
    {
        $this->auth->logout();
        header('Location: /?r=login');
        exit;
    }
    
    private function showLoginForm(string $error = ''): void
    {
        $lockoutTime = $this->auth->getRemainingLockoutTime();
        
        $this->render('login', [
            'error' => $error,
            'lockoutTime' => $lockoutTime,
            'redirect' => $_GET['redirect'] ?? '/?r=dashboard'
        ]);
    }
}
