<?php
// Simple test without database connection
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

echo "Testing form action generation...\n";

// Simulate form submission with filters
$_GET = [
    'r' => 'positions',
    'node_num' => '12345',
    'lat_min' => '43.0',
    'lat_max' => '43.1'
];

echo "Current GET parameters:\n";
print_r($_GET);

echo "\nForm action should be empty string with hidden r=positions field\n";
echo "Current query string: " . http_build_query($_GET) . "\n";
