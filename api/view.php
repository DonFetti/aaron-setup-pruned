<?php
// Image viewing page with token authentication and auto-cleanup

// Get token from query string
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validate token format (should be 64 character hex string)
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(404);
    die('Invalid token. The link may be incorrect or expired.');
}

// Paths - tokens and uploads are outside public_html for security
// From api/view.php (in public_html/api/), we go up two levels to reach the same directory as contact.php
// __DIR__ = public_html/api/, dirname(__DIR__) = public_html/, dirname(dirname(__DIR__)) = parent (same as contact.php)
$baseDir = dirname(dirname(__DIR__));
$tokensDir = $baseDir . '/tokens';
$uploadsDir = $baseDir . '/uploads';
$tokenFile = $tokensDir . '/' . $token . '.json';

// Check if token file exists
if (!file_exists($tokenFile)) {
    http_response_code(404);
    die('Token not found. The link may be incorrect or expired.');
}

// Load token data
$tokenData = json_decode(file_get_contents($tokenFile), true);

if (!$tokenData || !isset($tokenData['token']) || !isset($tokenData['files']) || !isset($tokenData['expires_at'])) {
    http_response_code(500);
    die('Invalid token data. Please try again later.');
}

// Check token expiration
$expirationDate = new DateTime($tokenData['expires_at']);
$now = new DateTime();

// Cleanup expired tokens and their files
if ($now > $expirationDate) {
    // Delete token file
    @unlink($tokenFile);
    
    // Delete associated image files
    if (isset($tokenData['files']) && is_array($tokenData['files'])) {
        foreach ($tokenData['files'] as $file) {
            $filePath = $uploadsDir . '/' . $file;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
    
    http_response_code(410);
    die('This link has expired. Images are automatically deleted after 15 days.');
}

// Get file information
$files = $tokenData['files'];
$name = isset($tokenData['name']) ? htmlspecialchars($tokenData['name'], ENT_QUOTES, 'UTF-8') : 'Contact';
$email = isset($tokenData['email']) ? htmlspecialchars($tokenData['email'], ENT_QUOTES, 'UTF-8') : '';

// Calculate days until expiration
$daysRemaining = $now->diff($expirationDate)->days;

// Cleanup routine: check and delete other expired tokens (runs on access)
cleanupExpiredTokens($tokensDir, $uploadsDir);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title>View Images - Spear Contracting</title>
    <link href="../styles/css/main.css" rel="stylesheet" type="text/css">
    <link href="../styles/css/custom.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .image-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .image-item {
            position: relative;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .image-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        .image-item img {
            width: 100%;
            height: auto;
            display: block;
        }
        .view-header {
            text-align: center;
            padding: 40px 20px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .view-header h1 {
            margin-bottom: 10px;
        }
        .view-header p {
            color: #6c757d;
            margin-bottom: 5px;
        }
        .expiration-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 10px 20px;
            margin: 20px auto;
            max-width: 600px;
            text-align: center;
        }
        .no-images {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <header data-pgc-define="header" data-pgc-edit="active" data-pgc-edit-classes="active-vav,non-active-nav"
        data-pgc-edit-types="header" data-pgc-define-name="navbar">
        <div id="navbar"></div>
    </header>
    <main>
        <div class="view-header">
            <h1>Contact Form Images</h1>
            <?php if (!empty($name)): ?>
                <p><strong>From:</strong> <?php echo $name; ?></p>
            <?php endif; ?>
            <?php if (!empty($email)): ?>
                <p><strong>Email:</strong> <?php echo $email; ?></p>
            <?php endif; ?>
            <p><strong>Images:</strong> <?php echo count($files); ?> file(s)</p>
            <?php if ($daysRemaining > 0): ?>
                <div class="expiration-notice">
                    <small>This link will expire in <?php echo $daysRemaining; ?> day(s). Images will be automatically deleted after 15 days.</small>
                </div>
            <?php else: ?>
                <div class="expiration-notice" style="background: #f8d7da; border-color: #dc3545;">
                    <small><strong>This link expires today!</strong> Images will be automatically deleted.</small>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($files)): ?>
            <div class="no-images">
                <p>No images found for this submission.</p>
            </div>
        <?php else: ?>
            <div class="image-container">
                <?php foreach ($files as $file): ?>
                    <?php 
                    $filePath = $uploadsDir . '/' . $file;
                    // Use image proxy script since files are outside public_html
                    // Since we're in api/view.php, image.php is in the same directory
                    $fileUrl = 'image.php?file=' . urlencode($file) . '&token=' . urlencode($token);
                    if (file_exists($filePath)): 
                    ?>
                        <div class="image-item">
                            <a href="<?php echo htmlspecialchars($fileUrl, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo htmlspecialchars($fileUrl, ENT_QUOTES); ?>" 
                                     alt="Uploaded image" 
                                     loading="lazy">
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <footer class="bg-primary">
        <div class="text-center">
            <p class="d-inline small text-secondary">Copyright &copy; 2025 Spear Contracting</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/nav_place.js"></script>
</body>
</html>
<?php

// Function to cleanup expired tokens and their associated files
function cleanupExpiredTokens($tokensDir, $uploadsDir) {
    if (!is_dir($tokensDir)) {
        return;
    }
    
    $now = new DateTime();
    $deletedCount = 0;
    
    // Get all token files
    $tokenFiles = glob($tokensDir . '/*.json');
    
    foreach ($tokenFiles as $tokenFile) {
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        
        if (!$tokenData || !isset($tokenData['expires_at'])) {
            continue;
        }
        
        $expirationDate = new DateTime($tokenData['expires_at']);
        
        // If expired, delete token and associated files
        if ($now > $expirationDate) {
            // Delete associated image files
            if (isset($tokenData['files']) && is_array($tokenData['files'])) {
                foreach ($tokenData['files'] as $file) {
                    $filePath = $uploadsDir . '/' . $file;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            
            // Delete token file
            @unlink($tokenFile);
            $deletedCount++;
        }
    }
    
    // Optional: Log cleanup (uncomment if needed)
    // if ($deletedCount > 0) {
    //     error_log("Cleaned up $deletedCount expired token(s) and associated files.");
    // }
}
?>
