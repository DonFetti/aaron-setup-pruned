<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
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
    require_once $contact_file;
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error occurred']);
    exit;
}

// Get the output from contact.php
$response = ob_get_clean();

// Output the response
echo $response;
?>
