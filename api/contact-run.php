<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Rate limiting function
function checkRateLimit($ip, $maxRequests = 5, $timeWindow = 300) {
    // Store rate_limit outside public_html for security (same directory as contact.php)
    // From api/contact-run.php, go up two levels to reach parent of public_html
    $baseDir = dirname(dirname(__DIR__));
    $rateLimitDir = $baseDir . '/rate_limit';
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    $ipHash = hash('sha256', $ip);
    $rateLimitFile = $rateLimitDir . '/' . $ipHash . '.json';
    
    $now = time();
    $requests = [];
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && isset($data['requests'])) {
            // Filter out requests outside the time window
            $requests = array_filter($data['requests'], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
        }
    }
    
    // Check if limit exceeded
    if (count($requests) >= $maxRequests) {
        return false;
    }
    
    // Add current request
    $requests[] = $now;
    
    // Save updated requests
    file_put_contents($rateLimitFile, json_encode(['requests' => array_values($requests)]));
    
    return true;
}

// Get client IP address
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Check rate limit (5 requests per 5 minutes = 300 seconds)
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 5, 300)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// Check if contact.php exists
$contact_file = __DIR__ . '/../../contact.php';
if (!file_exists($contact_file)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
    exit;
}

// Start output buffering to capture the response from contact.php
ob_start();

// Include contact.php which will handle the email sending
// The POST data is already available in $_POST
try {
    // Enable error reporting temporarily for better error handling
    $oldErrorReporting = error_reporting(E_ALL);
    $oldDisplayErrors = ini_set('display_errors', 0);
    
    require_once $contact_file;
    
    // Restore error settings
    error_reporting($oldErrorReporting);
    if ($oldDisplayErrors !== false) {
        ini_set('display_errors', $oldDisplayErrors);
    }
} catch (Throwable $e) {
    ob_end_clean();
    error_reporting($oldErrorReporting ?? E_ALL);
    if (isset($oldDisplayErrors) && $oldDisplayErrors !== false) {
        ini_set('display_errors', $oldDisplayErrors);
    }
    http_response_code(500);
    // Log error for debugging - store outside public_html for security
    $baseDir = dirname(dirname(__DIR__));
    $logFile = $baseDir . '/error_log';
    error_log('Contact form error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 3, $logFile);
    echo json_encode(['status' => 'error', 'message' => 'Server error occurred']);
    exit;
}

// Get the output from contact.php
$response = ob_get_clean();

// Check if response is already valid JSON (from contact.php's exit statements)
// If there's any output before JSON, clean it up
$response = trim($response);

// Try to validate JSON
$jsonData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Response is not valid JSON, might have PHP errors/warnings
    // Log the issue and return a generic error
    $baseDir = dirname(dirname(__DIR__));
    $logFile = $baseDir . '/error_log';
    error_log('Invalid JSON response from contact.php: ' . substr($response, 0, 500), 3, $logFile);
    
    // Try to find JSON in the response (in case there's output before it)
    if (preg_match('/\{[^}]*"status"[^}]*\}/', $response, $matches)) {
        $response = $matches[0];
    } else {
        // No valid JSON found, return generic error
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error occurred']);
        exit;
    }
}

// Output the response
echo $response;
?>
