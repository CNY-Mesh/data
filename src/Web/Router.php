<?php
declare(strict_types=1);
namespace App\Web;

final class Router
{
    private Auth $auth;
    
    // Routes that require authentication
    private const PROTECTED_ROUTES = [
        'analytics',
        'rawdata',
        'search',
        'mqtt_manager',
        'password_hash',
        'cleanup',
        'tools',
        'node_management'
    ];
    
    public function __construct()
    {
        $this->auth = new Auth();
    }
    
    public function dispatch(): void
    {
        $route = $_GET['r'] ?? 'our_nodes';
        
        // Check if route requires authentication
        if (in_array($route, self::PROTECTED_ROUTES) && !$this->auth->isAuthenticated()) {
            $this->redirectToLogin($route);
            return;
        }
        
        $controller = match ($route) {
            'dashboard'      => new Controllers\DashboardController(),
            'map_embed',
            'embed_map'      => new Controllers\MapEmbedController(),
            'nodes'          => new Controllers\NodesController(),
            'our_nodes'      => new Controllers\OurNodesController(),
            'node'           => new Controllers\NodeController(),
            'neighbors'      => new Controllers\NeighborsController(),
            'telemetry'      => new Controllers\TelemetryController(),
            'traceroutes'    => new Controllers\TraceroutesController(),
            'mapreports'     => new Controllers\MapReportsController(),
            'positions'      => new Controllers\PositionsController(),
            'text_messages'  => new Controllers\TextMessagesController(),
            'search'         => new Controllers\SearchController(),
            'mqtt_manager'   => new Controllers\MqttManagerController(),
            'api'            => new Controllers\ApiController(),
            'rawdata'        => new Controllers\RawDataController(),
            'analytics'      => new Controllers\AnalyticsController(),
            'password_hash'  => new Controllers\PasswordHashController(),
            'cleanup'        => new Controllers\CleanupController(),
            'about'          => new Controllers\AboutController(),
            'tools'          => new Controllers\ToolsController(),
            'node_management' => new Controllers\NodeManagementController(),
            'login'          => new Controllers\LoginController(),
            default          => new Controllers\OurNodesController()
        };
        
        $controller->handle();
    }
    
    private function redirectToLogin(string $originalRoute): void
    {
        $currentUrl = $_SERVER['REQUEST_URI'];
        $loginUrl = '/?r=login&redirect=' . urlencode($currentUrl);
        header("Location: $loginUrl");
        exit;
    }
}
