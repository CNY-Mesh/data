<?php
// Test script to debug positions route
require_once __DIR__ . '/bootstrap.php';

echo "Testing positions route...\n";

// Simulate $_GET parameters
$_GET['r'] = 'positions';
$_GET['node_num'] = '12345';

echo "Route parameter: " . ($_GET['r'] ?? 'not set') . "\n";
echo "Node number parameter: " . ($_GET['node_num'] ?? 'not set') . "\n";

// Test the router
try {
    $router = new \App\Web\Router();
    echo "Router created successfully\n";
    
    // Check if PositionsController exists
    if (class_exists('\App\Web\Controllers\PositionsController')) {
        echo "PositionsController class exists\n";
        $controller = new \App\Web\Controllers\PositionsController();
        echo "PositionsController instantiated successfully\n";
    } else {
        echo "ERROR: PositionsController class not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
