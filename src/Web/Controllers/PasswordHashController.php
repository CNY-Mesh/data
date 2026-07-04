<?php
declare(strict_types=1);

namespace App\Web\Controllers;

class PasswordHashController extends BaseController
{
    public function handle(): void
    {
        $hash = '';
        $password = '';
        $message = '';
        $messageType = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $message = 'Password hash generated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Please enter a password to hash.';
                $messageType = 'danger';
            }
        }
        
        $this->render('password_hash', [
            'hash' => $hash,
            'password' => $password,
            'message' => $message,
            'messageType' => $messageType
        ]);
    }
}
