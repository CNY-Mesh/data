<?php
/**
 * Common Authentication Header for Public Tools
 * Include this at the top of any public PHP file that requires authentication
 */

// Prevent direct access to this file
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    die('Direct access not allowed');
}

// Load the application bootstrap
if (!class_exists('App\Web\Auth')) {
    require_once __DIR__ . '/../bootstrap.php';
}

use App\Web\Auth;

// Create auth instance and check authentication
$auth = new Auth();

if (!$auth->isAuthenticated()) {
    // Store the current URL for redirect after login
    $currentUrl = $_SERVER['REQUEST_URI'];
    $loginUrl = '/index.php?r=login&redirect=' . urlencode($currentUrl);
    
    // Show authentication required page
    http_response_code(401);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication Required - CNYmesh Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center mt-5">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0"><i class="fas fa-lock"></i> Authentication Required</h4>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <i class="fas fa-shield-alt fa-4x text-warning mb-3"></i>
                                <h5>Access Restricted</h5>
                                <p class="text-muted">
                                    This tool requires authentication to access.
                                    Please log in to continue.
                                </p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login to Access Tool
                                </a>
                                <a href="/index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home"></i> Go to Dashboard
                                </a>
                            </div>
                            
                            <hr class="my-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                You will be redirected back to this page after successful login.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Authentication successful - user info is available
$username = $auth->getUsername();
?>
