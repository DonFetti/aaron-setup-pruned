<?php
// this file exists outside of public_html!

// Prevent any output before headers (check for BOM, whitespace, etc.)
if (ob_get_level() == 0) {
    ob_start();
}

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

// Image upload processing
$uploadedFiles = [];
$viewToken = null;
$allowedTypes = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'heic'];
$maxFileSize = 8 * 1024 * 1024; // 8MB in bytes
$maxFiles = 5;

// Process file uploads if any files were uploaded
if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    // Check if Imagick extension is available (only needed when processing images)
    if (!extension_loaded('imagick')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Image processing not available. Please contact support.']);
        exit;
    }
    $files = $_FILES['images'];
    $fileCount = count($files['name']);
    
    // Validate file count
    if ($fileCount > $maxFiles) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Maximum $maxFiles files allowed. Please select fewer files."]);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    // contact.php is already outside public_html, so tokens and uploads are in the same directory
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory. Please try again later.']);
            exit;
        }
    }
    
    // Process each file
    for ($i = 0; $i < $fileCount; $i++) {
        // Skip if file upload had an error
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $tmpName = $files['tmp_name'][$i];
        $originalName = $files['name'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];
        
        // Validate file size
        if ($fileSize > $maxFileSize) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "File \"$originalName\" exceeds 8MB limit. Please choose a smaller file."]);
            exit;
        }
        
        // Validate file type by extension
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "File \"$originalName\" is not a supported format. Please use JPG, JPEG, PNG, or HEIC files."]);
            exit;
        }
        
        // Validate that file actually exists (security check)
        if (!is_uploaded_file($tmpName)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid file upload.']);
            exit;
        }
        
        try {
            // Read file content directly from tmp_name without saving
            $fileContent = file_get_contents($tmpName);
            if ($fileContent === false) {
                throw new Exception('Failed to read uploaded file');
            }
            
            // Create Imagick object from blob (never save before re-encoding)
            $imagick = new Imagick();
            
            // Read image from blob (handles different formats including HEIC)
            $imagick->readImageBlob($fileContent);
            
            // Remove all profiles and properties to strip metadata (security)
            $imagick->stripImage();
            
            // Ensure we have only the first image (in case of multi-page images)
            if ($imagick->getNumberImages() > 1) {
                $imagick = $imagick->coalesceImages();
                $imagick = $imagick->getImage(0);
            }
            
            // Convert HEIC to JPEG, keep other formats as-is
            if ($fileExtension === 'heic' || $fileExtension === 'heif') {
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $outputExtension = 'jpg';
            } elseif ($fileExtension === 'png') {
                $imagick->setImageFormat('png');
                $imagick->setImageCompressionQuality(85);
                $outputExtension = 'png';
            } else {
                // JPEG
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $outputExtension = 'jpg';
            }
            
            // Generate UUID v4 for filename (cryptographically secure)
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            
            $newFileName = $uuid . '.' . $outputExtension;
            $savePath = $uploadDir . '/' . $newFileName;
            
            // Write the re-encoded image (this is the first time it's saved)
            $imagick->writeImage($savePath);
            
            // Clean up Imagick object
            $imagick->clear();
            $imagick->destroy();
            
            // Store successful upload
            $uploadedFiles[] = $newFileName;
            
        } catch (Exception $e) {
            // Clean up on error
            if (isset($imagick)) {
                $imagick->clear();
                $imagick->destroy();
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Failed to process image \"$originalName\". Please ensure the file is a valid image."]);
            exit;
        }
        
        // Explicitly delete the temp file (security - ensure it's not saved before re-encoding)
        if (file_exists($tmpName)) {
            @unlink($tmpName);
        }
    }
    
    // Generate token for viewing uploaded images (if files were uploaded)
    if (!empty($uploadedFiles)) {
        // Generate secure token
        $token = bin2hex(random_bytes(32)); // 64 character hex token
        
        // Create tokens directory if it doesn't exist
        // contact.php is already outside public_html, so tokens and uploads are in the same directory
        $tokensDir = __DIR__ . '/tokens';
        if (!is_dir($tokensDir)) {
            if (!mkdir($tokensDir, 0755, true)) {
                // If we can't create tokens directory, continue without token
                $token = null;
            }
        }
        
        if ($token) {
            // Calculate expiration date (15 days from now)
            $expirationDate = date('Y-m-d H:i:s', strtotime('+15 days'));
            $creationDate = date('Y-m-d H:i:s');
            
            // Store token metadata
            $tokenData = [
                'token' => $token,
                'files' => $uploadedFiles,
                'created_at' => $creationDate,
                'expires_at' => $expirationDate,
                'name' => $name,
                'email' => $email
            ];
            
            $tokenFile = $tokensDir . '/' . $token . '.json';
            file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
            
            // Store token for email (set at top level scope)
            $viewToken = $token;
        }
    }
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
if (!empty($uploadedFiles)) {
    $email_body .= "\nUploaded Images (" . count($uploadedFiles) . "):\n";
    foreach ($uploadedFiles as $file) {
        $email_body .= "- $file\n";
    }
    // Add viewing link if token was generated
    if (isset($viewToken) && !empty($viewToken)) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $scriptPath = dirname($_SERVER['PHP_SELF']);
        // Remove trailing slash if present, then add view.php
        $scriptPath = rtrim($scriptPath, '/');
        $viewUrl = $protocol . '://' . $host . $scriptPath . '/view.php?token=' . urlencode($viewToken);
        $email_body .= "\nView Images: $viewUrl\n";
        $email_body .= "(Link expires in 15 days)\n";
    }
}

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
