<?php
declare(strict_types=1);

namespace App\Web;

use App\Support\Env;

class Auth
{
    private const SESSION_KEY = 'mesh_authenticated';
    private const LOGIN_ATTEMPTS_KEY = 'login_attempts';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 900; // 15 minutes
    
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    private function getUsers(): array
    {
        $users = [];
        
        // Get users from environment variables
        $adminUser = Env::get('ADMIN_USERNAME', 'admin');
        $adminPass = Env::get('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // default: password
        
        $meshUser = Env::get('MESH_USERNAME', 'mesh');
        $meshPass = Env::get('MESH_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // default: password
        
        if ($adminUser) {
            $users[$adminUser] = $adminPass;
        }
        
        if ($meshUser && $meshUser !== $adminUser) {
            $users[$meshUser] = $meshPass;
        }
        
        return $users;
    }
    
    public function isAuthenticated(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
    
    public function login(string $username, string $password): bool
    {
        if ($this->isLockedOut()) {
            return false;
        }
        
        $users = $this->getUsers();
        
        if (!isset($users[$username])) {
            $this->recordFailedAttempt();
            return false;
        }
        
        if (password_verify($password, $users[$username])) {
            $_SESSION[self::SESSION_KEY] = true;
            $_SESSION['username'] = $username;
            $this->clearFailedAttempts();
            return true;
        }
        
        $this->recordFailedAttempt();
        return false;
    }
    
    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION['username']);
        session_destroy();
    }
    
    public function getUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }
    
    private function isLockedOut(): bool
    {
        if (!isset($_SESSION[self::LOGIN_ATTEMPTS_KEY])) {
            return false;
        }
        
        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY];
        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $lockoutTime = $attempts['last_attempt'] + self::LOCKOUT_TIME;
            if (time() < $lockoutTime) {
                return true;
            } else {
                // Lockout period expired, reset attempts
                $this->clearFailedAttempts();
            }
        }
        
        return false;
    }
    
    private function recordFailedAttempt(): void
    {
        if (!isset($_SESSION[self::LOGIN_ATTEMPTS_KEY])) {
            $_SESSION[self::LOGIN_ATTEMPTS_KEY] = ['count' => 0, 'last_attempt' => 0];
        }
        
        $_SESSION[self::LOGIN_ATTEMPTS_KEY]['count']++;
        $_SESSION[self::LOGIN_ATTEMPTS_KEY]['last_attempt'] = time();
    }
    
    private function clearFailedAttempts(): void
    {
        unset($_SESSION[self::LOGIN_ATTEMPTS_KEY]);
    }
    
    public function getRemainingLockoutTime(): int
    {
        if (!$this->isLockedOut()) {
            return 0;
        }
        
        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY];
        $lockoutTime = $attempts['last_attempt'] + self::LOCKOUT_TIME;
        return max(0, $lockoutTime - time());
    }
}
