<?php
/**
 * Simple Health Check Endpoint for EspoCRM
 * Used by Dokploy/Traefik for zero-downtime deployments
 */

// Set JSON response header
header('Content-Type: application/json');

// Simple health check - always return healthy for basic container functionality
$response = [
    'status' => 'healthy',
    'timestamp' => time(),
    'message' => 'EspoCRM container is running'
];

// Basic checks
try {
    // Check if we can write to temp
    $tempFile = tempnam(sys_get_temp_dir(), 'health_check');
    if ($tempFile && unlink($tempFile)) {
        $response['filesystem'] = 'ok';
    }
    
    // Check PHP version
    $response['php_version'] = PHP_VERSION;
    
    // Check if EspoCRM directory exists
    $response['espocrm_path'] = is_dir('/var/www/html') ? 'ok' : 'missing';
    
    // Check if config exists (optional for first run)
    $configPath = '/var/www/html/data/config.php';
    if (file_exists($configPath)) {
        $response['config'] = 'found';
        $response['status'] = 'ready';
    } else {
        $response['config'] = 'not_found';
        $response['message'] = 'EspoCRM ready for initial setup';
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Even if there's an error, return 200 for container health
    http_response_code(200);
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => time(),
        'message' => 'Container running with limited checks',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

exit(0);