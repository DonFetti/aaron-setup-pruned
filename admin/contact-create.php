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
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : null;
$status = isset($_POST['status']) ? trim((string) $_POST['status']) : null;
$source = isset($_POST['source']) ? trim((string) $_POST['source']) : null;
$company_id = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int) $_POST['company_id'] : null;
$new_company_name = isset($_POST['new_company_name']) ? trim((string) $_POST['new_company_name']) : '';
$new_company_type = isset($_POST['new_company_type']) ? trim((string) $_POST['new_company_type']) : '';

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

// Resolve company: either existing company_id or create new company
$company_display_name = null;
if ($new_company_name !== '') {
    if ($new_company_type === '') {
        $err[] = 'Company type is required when creating a new company.';
    } else {
        $company_id = null; // will set after insert
        $company_display_name = $new_company_name;
    }
} elseif ($company_id !== null) {
    // Validate existing company_id exists
    if (!$pdo || isset($db_error)) {
        // checked later
    } else {
        try {
            $st = $pdo->prepare("SELECT id, name FROM companies WHERE id = :id LIMIT 1");
            $st->execute([':id' => $company_id]);
            $row = $st->fetch();
            if ($row) {
                $company_display_name = $row['name'];
            } else {
                $company_id = null;
            }
        } catch (PDOException $e) {
            $company_id = null;
        }
    }
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
    // Create new company if user chose "Create new company"
    if ($new_company_name !== '' && $new_company_type !== '') {
        $st = $pdo->prepare("INSERT INTO companies (name, company_type) VALUES (:name, :company_type) RETURNING id, name");
        $st->execute([
            ':name'         => $new_company_name,
            ':company_type' => $new_company_type,
        ]);
        $row = $st->fetch();
        $company_id = (int) $row['id'];
        $company_display_name = $row['name'];
    }

    // Generate UUID v4 in pure PHP (no extensions required)
    $contact_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $sql = "INSERT INTO contacts (id, name, email, phone, company, company_id, role, status, source, modified_by)
            VALUES (:id, :name, :email, :phone, :company, :company_id, :role, :status, :source, :modified_by)";
    $params = [
        ':id'          => $contact_id,
        ':name'        => $name,
        ':email'       => $email !== '' ? $email : null,
        ':phone'       => $phone ?: null,
        ':company'     => $company_display_name,
        ':company_id'  => $company_id,
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
    $msg = $e->getMessage();
    if (strpos($msg, 'contacts_email_key') !== false || (strpos($msg, 'unique') !== false && strpos($msg, 'contacts') !== false)) {
        header('Location: /admin/contacts.php?error=' . urlencode('A contact with this email already exists.'));
    } elseif (strpos($msg, 'companies_name_key') !== false || (strpos($msg, 'unique') !== false && strpos($msg, 'companies') !== false)) {
        header('Location: /admin/contacts.php?error=' . urlencode('A company with this name already exists. Please select it from the dropdown.'));
    } else {
        header('Location: /admin/contacts.php?error=' . urlencode('Could not create contact.'));
    }
    exit;
}
