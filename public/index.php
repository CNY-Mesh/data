<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

// Handle access denied redirects from .htaccess
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $file = $_GET['file'] ?? 'unknown';
    http_response_code(403);
    
    // Show error message with redirect to tools page
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - CNYmesh Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center mt-5">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0"><i class="fas fa-shield-alt"></i> Access Denied</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-lock"></i> Debug Tools Now Protected</h5>
                                <p class="mb-0">Direct access to <code>' . htmlspecialchars($file) . '</code> is no longer allowed.</p>
                            </div>
                            
                            <p>All debug and development tools have been moved to a secure, authenticated interface.</p>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                                <a href="/?r=login" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login to Access Tools
                                </a>
                                <a href="/?r=dashboard" class="btn btn-outline-secondary">
                                    <i class="fas fa-home"></i> Go to Dashboard
                                </a>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                After logging in, use the <strong>Tools</strong> menu to access debug utilities.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

use App\Web\Router;
$router = new Router();
$router->dispatch();
