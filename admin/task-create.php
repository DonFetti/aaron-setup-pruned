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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_task'])) {
    header('Location: /admin/tasks.php');
    exit;
}

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;
$status = isset($_POST['status']) ? (string) $_POST['status'] : 'open';
$priority = isset($_POST['priority']) ? (string) $_POST['priority'] : 'medium';
$contact_id = isset($_POST['contact_id']) ? trim((string) $_POST['contact_id']) : null;
$deal_id = isset($_POST['deal_id']) ? trim((string) $_POST['deal_id']) : null;
$assigned_to_raw = isset($_POST['assigned_to']) ? trim((string) $_POST['assigned_to']) : '';
$due_at_raw = isset($_POST['due_at']) ? trim((string) $_POST['due_at']) : null;

$err = [];
if ($title === '') {
    $err[] = 'Title is required.';
}
if (!$contact_id && !$deal_id) {
    $err[] = 'Select at least one of Contact or Deal.';
}
$allowed_status = ['open', 'done', 'canceled'];
if (!in_array($status, $allowed_status, true)) {
    $status = 'open';
}
$allowed_priority = ['low', 'medium', 'high'];
if (!in_array($priority, $allowed_priority, true)) {
    $priority = 'medium';
}

$assigned_to = null;
if ($assigned_to_raw !== '') {
    $assigned_to = ctype_digit($assigned_to_raw) ? (int) $assigned_to_raw : null;
    if ($assigned_to !== null && $assigned_to < 1) {
        $assigned_to = null;
    }
}

$due_at = null;
if ($due_at_raw !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $due_at_raw);
    if ($dt) {
        $due_at = $dt->format('Y-m-d H:i:s');
    }
}

if (!empty($err)) {
    header('Location: /admin/tasks.php?error=' . urlencode(implode(' ', $err)));
    exit;
}

if (!$pdo || isset($db_error)) {
    header('Location: /admin/tasks.php?error=' . urlencode('Database unavailable.'));
    exit;
}

try {
    $sql = "INSERT INTO tasks (title, notes, status, priority, due_at, assigned_to, contact_id, deal_id)
            VALUES (:title, :notes, :status, :priority, :due_at, :assigned_to, :contact_id, :deal_id)";
    $params = [
        ':title'       => $title,
        ':notes'       => $notes ?: null,
        ':status'      => $status,
        ':priority'    => $priority,
        ':due_at'      => $due_at,
        ':assigned_to' => $assigned_to,
        ':contact_id'  => $contact_id ?: null,
        ':deal_id'     => $deal_id !== '' && ctype_digit($deal_id) ? (int) $deal_id : null,
    ];
    $st = $pdo->prepare($sql);
    $st->execute($params);
    header('Location: /admin/tasks.php?created=1');
    exit;
} catch (PDOException $e) {
    header('Location: /admin/tasks.php?error=' . urlencode('Could not create task.'));
    exit;
}
