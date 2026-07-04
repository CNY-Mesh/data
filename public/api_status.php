<?php
// API Status Checker
// Accessible at: https://data.cnymesh.org/api_status.php

// Require authentication for this tool
require_once __DIR__ . '/_auth_header.php';

header('Content-Type: application/json');

$status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'checks' => []
];

try {
    // Check 1: Bootstrap loading
    require_once __DIR__ . '/../bootstrap.php';
    $status['checks']['bootstrap'] = ['status' => 'OK', 'message' => 'Bootstrap loaded successfully'];
    
    // Check 2: Database connection
    try {
        $db = new CNYMesh\Database();
        $stmt = $db->query("SELECT COUNT(*) as count FROM raw_messages LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $status['checks']['database'] = [
            'status' => 'OK', 
            'message' => 'Database connected',
            'raw_messages_count' => $result['count']
        ];
    } catch (Exception $e) {
        $status['checks']['database'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    }
    
    // Check 3: API Controller
    try {
        if (class_exists('CNYMesh\Web\Controllers\ApiController')) {
            $controller = new CNYMesh\Web\Controllers\ApiController();
            $has_mesh_data = method_exists($controller, 'mesh_data');
            $status['checks']['api_controller'] = [
                'status' => 'OK',
                'message' => 'ApiController available',
                'mesh_data_method' => $has_mesh_data ? 'Available' : 'Missing'
            ];
        } else {
            $status['checks']['api_controller'] = ['status' => 'ERROR', 'message' => 'ApiController class not found'];
        }
    } catch (Exception $e) {
        $status['checks']['api_controller'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    }
    
    // Check 4: Recent activity
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM raw_messages WHERE timestamp > " . (time() - 3600));
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);
        $status['checks']['recent_activity'] = [
            'status' => 'INFO',
            'message' => 'Messages in last hour: ' . $recent['count']
        ];
    } catch (Exception $e) {
        $status['checks']['recent_activity'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    }
    
    // Overall status
    $error_count = 0;
    foreach ($status['checks'] as $check) {
        if ($check['status'] === 'ERROR') $error_count++;
    }
    
    $status['overall'] = $error_count === 0 ? 'HEALTHY' : ($error_count < 3 ? 'DEGRADED' : 'UNHEALTHY');
    
} catch (Exception $e) {
    $status['overall'] = 'CRITICAL';
    $status['error'] = $e->getMessage();
}

echo json_encode($status, JSON_PRETTY_PRINT);
