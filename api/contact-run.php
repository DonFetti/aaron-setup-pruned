<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Start output buffering to capture the response from contact.php
ob_start();

// Include contact.php which will handle the email sending
// The POST data is already available in $_POST
require_once '../contact.php';

// Get the output from contact.php
$response = ob_get_clean();

// Output the response
echo $response;
?>
