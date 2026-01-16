<?php
// Check if this file is being accessed directly or included
$is_direct_access = basename($_SERVER['PHP_SELF']) === 'contact.php';

// Set content type header (only if not already set)
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Only check REQUEST_METHOD if this file is being accessed directly
// When included via contact-run.php, the check is already done there
if ($is_direct_access && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$timeline = isset($_POST['timeline']) ? trim($_POST['timeline']) : '';
$honeypot = isset($_POST['website']) ? trim($_POST['website']) : '';

// Honeypot check - reject if filled
if (!empty($honeypot)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid submission']);
    exit;
}

// Validate required fields
if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

// Length caps validation
$maxNameLength = 100;
$maxMessageLength = 2000;
if (strlen($name) > $maxNameLength) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name is too long. Maximum ' . $maxNameLength . ' characters allowed.']);
    exit;
}
if (strlen($message) > $maxMessageLength) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is too long. Maximum ' . $maxMessageLength . ' characters allowed.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
    exit;
}

// Block CR/LF in email (prevent header injection)
if (strpos($email, "\r") !== false || strpos($email, "\n") !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
    exit;
}

// Block CR/LF in name (prevent injection in subject/body)
if (strpos($name, "\r") !== false || strpos($name, "\n") !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid name format']);
    exit;
}

// Sanitize inputs
// Strip CR/LF from name (already validated, but double-check)
$name = str_replace(["\r", "\n"], '', $name);
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
// Sanitize timeline (optional field)
if (!empty($timeline)) {
    $timeline = str_replace(["\r", "\n"], '', $timeline);
    $timeline = htmlspecialchars($timeline, ENT_QUOTES, 'UTF-8');
}

// Email configuration
$to = 'aaron@spearcontracting.ca';
$subject = 'New Contact Form Submission from ' . $name;
$email_body = "You have received a new contact form submission:\n\n";
$email_body .= "Name: $name\n";
$email_body .= "Email: $email\n";
if (!empty($timeline)) {
    $email_body .= "Estimated Timeline: $timeline\n";
}
$email_body .= "\nMessage:\n$message\n";

// Email headers
$from = 'no-reply@spearcontracting.ca';

// Sanitize email for headers (already validated for CR/LF, but double-check)
$safeEmail = str_replace(["\r", "\n"], '', $email);

$headers  = "From: Spear Contracting <{$from}>\r\n";
$headers .= "Reply-To: {$safeEmail}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
// CC to abooth15@gmail.com
$cc = 'abooth15@gmail.com';
$headers .= "Cc: {$cc}\r\n";


// Send email
$mail_sent = mail($to, $subject, $email_body, $headers);

if ($mail_sent) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Thank you! Your message has been sent successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message. Please try again later.']);
}
?>
