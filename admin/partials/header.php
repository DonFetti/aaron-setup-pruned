<?php
// Require database connection
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get username from HTTP Basic Authentication and resolve to users.id
$logged_in_username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
$logged_in_user_id = null;

if ($logged_in_username && $pdo && !isset($db_error)) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_username']) && $_SESSION['user_username'] === $logged_in_username) {
        $logged_in_user_id = (int) $_SESSION['user_id'];
        $logged_in_first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : null;
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE user_name = :user_name LIMIT 1");
            $stmt->execute([':user_name' => $logged_in_username]);
            $row = $stmt->fetch();
            if ($row) {
                $logged_in_user_id = (int) $row['id'];
                $_SESSION['user_id'] = $logged_in_user_id;
                $_SESSION['user_username'] = $logged_in_username;
                $_SESSION['first_name'] = (string) $row['first_name'];
                $logged_in_first_name = $_SESSION['first_name'];
            } else {
                unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['first_name']);
            }
        } catch (PDOException $e) {
            unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['first_name']);
        }
    }
} else {
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['first_name']);
}
if (!isset($logged_in_first_name)) {
    $logged_in_first_name = null;
}
$GLOBALS['logged_in_user_id'] = $logged_in_user_id;

?>
<link rel="stylesheet" href="/styles/css/main.css">
<link rel="stylesheet" href="/admin/style.css">
