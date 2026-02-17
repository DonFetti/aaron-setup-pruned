<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
$user_id = null;
if ($username && $pdo && !isset($db_error)) {
    if (isset($_SESSION['user_id'], $_SESSION['user_username']) && $_SESSION['user_username'] === $username) {
        $user_id = (int) $_SESSION['user_id'];
    } else {
        try {
            $st = $pdo->prepare("SELECT id FROM users WHERE user_name = :u LIMIT 1");
            $st->execute([':u' => $username]);
            $r = $st->fetch();
            if ($r) {
                $user_id = (int) $r['id'];
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_username'] = $username;
            }
        } catch (PDOException $e) { /* ignore */ }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_contact'])) {
    header('Location: /admin/contacts.php');
    exit;
}

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim((string) $_POST['phone']) : null;
$company = isset($_POST['company']) ? trim((string) $_POST['company']) : null;
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : null;
$status = isset($_POST['status']) ? trim((string) $_POST['status']) : null;
$source = isset($_POST['source']) ? trim((string) $_POST['source']) : null;

$err = [];
if ($name === '') {
    $err[] = 'Name is required.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err[] = 'Invalid email format.';
}

// Validate status if provided
if ($status !== '' && $status !== null) {
    $allowed_status = ['active', 'inactive'];
    if (!in_array($status, $allowed_status, true)) {
        $status = null;
    }
} else {
    $status = null;
}

if (!empty($err)) {
    header('Location: /admin/contacts.php?error=' . urlencode(implode(' ', $err)));
    exit;
}

if (!$pdo || isset($db_error)) {
    header('Location: /admin/contacts.php?error=' . urlencode('Database unavailable.'));
    exit;
}

try {
    // Generate UUID v4 in pure PHP (no extensions required)
    // Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
    // where x is any hexadecimal digit and y is one of 8, 9, A, or B
    $contact_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, // Version 4
        mt_rand(0, 0x3fff) | 0x8000, // Variant bits
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $sql = "INSERT INTO contacts (id, name, email, phone, company, role, status, source, modified_by)
            VALUES (:id, :name, :email, :phone, :company, :role, :status, :source, :modified_by)";
    $params = [
        ':id'          => $contact_id,
        ':name'        => $name,
        ':email'       => $email !== '' ? $email : null,
        ':phone'       => $phone ?: null,
        ':company'     => $company ?: null,
        ':role'        => $role ?: null,
        ':status'      => $status,
        ':source'      => $source ?: null,
        ':modified_by' => $user_id,
    ];
    
    $st = $pdo->prepare($sql);
    $st->execute($params);
    header('Location: /admin/contacts.php?created=1');
    exit;
} catch (PDOException $e) {
    // Check if it's a unique constraint violation (duplicate email)
    if (strpos($e->getMessage(), 'contacts_email_key') !== false || strpos($e->getMessage(), 'unique') !== false) {
        header('Location: /admin/contacts.php?error=' . urlencode('A contact with this email already exists.'));
    } else {
        header('Location: /admin/contacts.php?error=' . urlencode('Could not create contact.'));
    }
    exit;
}
