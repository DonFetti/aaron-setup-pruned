<?php
// Image proxy script - serves images from outside public_html
// Only accessible with valid token

// Get token and file from query string
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$file = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';

// Validate inputs
if (empty($token) || empty($file)) {
    http_response_code(400);
    die('Invalid request.');
}

// Validate token format (should be 64 character hex string)
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(400);
    die('Invalid token.');
}

// Validate file name (should be UUID with extension)
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(jpg|jpeg|png)$/i', $file)) {
    http_response_code(400);
    die('Invalid file.');
}

// Paths - tokens and uploads are outside public_html for security
// From api/image.php (in public_html/api/), we go up two levels to reach the same directory as contact.php
// __DIR__ = public_html/api/, dirname(__DIR__) = public_html/, dirname(dirname(__DIR__)) = parent (same as contact.php)
$baseDir = dirname(dirname(__DIR__));
$tokensDir = $baseDir . '/tokens';
$uploadsDir = $baseDir . '/uploads';
$tokenFile = $tokensDir . '/' . $token . '.json';
$filePath = $uploadsDir . '/' . $file;

// Verify token exists and is valid
if (!file_exists($tokenFile)) {
    http_response_code(404);
    die('Token not found.');
}

// Load token data
$tokenData = json_decode(file_get_contents($tokenFile), true);

if (!$tokenData || !isset($tokenData['token']) || !isset($tokenData['files']) || !isset($tokenData['expires_at'])) {
    http_response_code(500);
    die('Invalid token data.');
}

// Verify token matches
if ($tokenData['token'] !== $token) {
    http_response_code(403);
    die('Token mismatch.');
}

// Check token expiration
$expirationDate = new DateTime($tokenData['expires_at']);
$now = new DateTime();

if ($now > $expirationDate) {
    http_response_code(410);
    die('Token expired.');
}

// Verify file is associated with this token
if (!in_array($file, $tokenData['files'])) {
    http_response_code(403);
    die('File not associated with this token.');
}

// Verify file exists
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('File not found.');
}

// Get file info
$mimeType = mime_content_type($filePath);
$fileSize = filesize($filePath);
$lastModified = filemtime($filePath);

// Validate MIME type (only images)
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
    http_response_code(400);
    die('Invalid file type.');
}

// Set headers for image serving
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('Cache-Control: private, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Output file
readfile($filePath);
exit;
?>
