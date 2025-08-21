<?php
/**
 * Health Check Endpoint for EspoCRM
 * Used by Dokploy/Traefik for zero-downtime deployments
 */

// Set JSON response header
header('Content-Type: application/json');

// Initialize response
$response = [
    'status' => 'healthy',
    'timestamp' => time(),
    'checks' => []
];

$httpCode = 200;

try {
    // Check 1: PHP Version
    $response['checks']['php'] = [
        'status' => 'ok',
        'version' => PHP_VERSION,
        'required' => '8.2+'
    ];

    // Check 2: Required PHP Extensions
    $requiredExtensions = [
        'pdo', 'pdo_mysql', 'json', 'openssl', 'gd', 
        'mbstring', 'zip', 'curl', 'xml', 'bcmath'
    ];
    
    $missingExtensions = [];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    $response['checks']['extensions'] = [
        'status' => empty($missingExtensions) ? 'ok' : 'error',
        'missing' => $missingExtensions
    ];
    
    if (!empty($missingExtensions)) {
        $response['status'] = 'degraded';
    }

    // Check 3: File System
    $requiredDirs = [
        '../data' => 'writable',
        '../custom' => 'writable',
        '../upload' => 'writable',
        '../client/custom' => 'writable'
    ];
    
    $fsErrors = [];
    foreach ($requiredDirs as $dir => $permission) {
        $fullPath = __DIR__ . '/../../' . $dir;
        if (!is_dir($fullPath)) {
            $fsErrors[] = "Directory $dir does not exist";
        } elseif ($permission === 'writable' && !is_writable($fullPath)) {
            $fsErrors[] = "Directory $dir is not writable";
        }
    }
    
    $response['checks']['filesystem'] = [
        'status' => empty($fsErrors) ? 'ok' : 'error',
        'errors' => $fsErrors
    ];
    
    if (!empty($fsErrors)) {
        $response['status'] = 'unhealthy';
        $httpCode = 503;
    }

    // Check 4: Configuration File
    $configPath = __DIR__ . '/../../data/config.php';
    if (file_exists($configPath)) {
        $config = @include($configPath);
        
        if ($config === false) {
            $response['checks']['config'] = [
                'status' => 'error',
                'message' => 'Could not load configuration'
            ];
            $response['status'] = 'unhealthy';
            $httpCode = 503;
        } else {
            $response['checks']['config'] = [
                'status' => 'ok',
                'loaded' => true
            ];
            
            // Check 5: Database Connection (only if config exists)
            if (isset($config['database'])) {
                try {
                    $dsn = sprintf(
                        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                        $config['database']['host'] ?? 'localhost',
                        $config['database']['port'] ?? 3306,
                        $config['database']['dbname'] ?? ''
                    );
                    
                    $pdo = new PDO(
                        $dsn,
                        $config['database']['user'] ?? '',
                        $config['database']['password'] ?? '',
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 5
                        ]
                    );
                    
                    // Test query
                    $stmt = $pdo->query('SELECT 1');
                    
                    $response['checks']['database'] = [
                        'status' => 'ok',
                        'connected' => true,
                        'host' => $config['database']['host'] ?? 'localhost'
                    ];
                } catch (PDOException $e) {
                    $response['checks']['database'] = [
                        'status' => 'error',
                        'connected' => false,
                        'error' => 'Connection failed'
                    ];
                    $response['status'] = 'unhealthy';
                    $httpCode = 503;
                }
            }
            
            // Check 6: Redis Connection (optional)
            if (extension_loaded('redis') && isset($config['redis'])) {
                try {
                    $redis = new Redis();
                    $redis->connect(
                        $config['redis']['host'] ?? 'redis',
                        $config['redis']['port'] ?? 6379,
                        2.0 // timeout
                    );
                    
                    if ($redis->ping()) {
                        $response['checks']['redis'] = [
                            'status' => 'ok',
                            'connected' => true
                        ];
                    } else {
                        throw new Exception('Redis ping failed');
                    }
                } catch (Exception $e) {
                    $response['checks']['redis'] = [
                        'status' => 'warning',
                        'connected' => false,
                        'note' => 'Redis unavailable but not critical'
                    ];
                    // Don't mark as unhealthy since Redis is optional
                    if ($response['status'] === 'healthy') {
                        $response['status'] = 'degraded';
                    }
                }
            }
        }
    } else {
        // No config file - might be initial installation
        $response['checks']['config'] = [
            'status' => 'warning',
            'message' => 'Configuration not found - initial setup may be required'
        ];
        $response['status'] = 'degraded';
    }

    // Check 7: Memory Usage
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    
    $response['checks']['memory'] = [
        'status' => 'ok',
        'limit' => $memoryLimit,
        'usage' => round($memoryUsage / 1024 / 1024, 2) . 'MB',
        'peak' => round($memoryPeak / 1024 / 1024, 2) . 'MB'
    ];

    // Check 8: Disk Space
    $freeSpace = disk_free_space(__DIR__);
    $totalSpace = disk_total_space(__DIR__);
    $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
    
    $response['checks']['disk'] = [
        'status' => $usedPercentage < 90 ? 'ok' : 'warning',
        'free' => round($freeSpace / 1024 / 1024 / 1024, 2) . 'GB',
        'used_percentage' => $usedPercentage . '%'
    ];
    
    if ($usedPercentage >= 90 && $response['status'] === 'healthy') {
        $response['status'] = 'degraded';
    }

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['error'] = 'Health check failed';
    $response['message'] = $e->getMessage();
    $httpCode = 503;
}

// Set appropriate HTTP response code
http_response_code($httpCode);

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// Ensure clean exit
exit(0);