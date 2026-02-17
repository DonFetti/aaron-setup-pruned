<?php
require_once __DIR__ . '/partials/no-cache.php';
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

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    header('Location: /admin/contacts.php?error=' . urlencode('Contact not specified.'));
    exit;
}

$contact_id = isset($_POST['id']) ? trim((string) $_POST['id']) : trim((string) $_GET['id']);
if ($contact_id === '') {
    header('Location: /admin/contacts.php?error=' . urlencode('Contact not specified.'));
    exit;
}

$contact = null;
$db_error = null;

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, company, role, status, source, created_at
                               FROM contacts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $contact_id]);
        $contact = $stmt->fetch();
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact']) && $contact) {
    if (!$pdo || isset($db_error)) {
        header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode('Database unavailable.'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = :id");
        $stmt->execute([':id' => $contact_id]);
        header('Location: /admin/contacts.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $delete_error = 'Could not delete contact. They may have related deals or tasks.';
        if (strpos($e->getMessage(), 'foreign key') !== false || strpos($e->getMessage(), 'violates') !== false) {
            $delete_error = 'Cannot delete: this contact has related deals or tasks.';
        }
        header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode($delete_error));
        exit;
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact']) && $contact) {
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
    if ($status !== '' && $status !== null) {
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = null;
        }
    } else {
        $status = null;
    }

    if (!empty($err)) {
        header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode(implode(' ', $err)));
        exit;
    }

    if (!$pdo || isset($db_error)) {
        header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode('Database unavailable.'));
        exit;
    }

    try {
        $sql = "UPDATE contacts SET
                    name = :name,
                    email = :email,
                    phone = :phone,
                    company = :company,
                    role = :role,
                    status = :status,
                    source = :source,
                    modified_by = :modified_by
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'        => $name,
            ':email'       => $email !== '' ? $email : null,
            ':phone'       => $phone ?: null,
            ':company'     => $company ?: null,
            ':role'        => $role ?: null,
            ':status'      => $status,
            ':source'      => $source ?: null,
            ':modified_by' => $user_id,
            ':id'          => $contact_id,
        ]);
        header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&updated=1');
        exit;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'contacts_email_key') !== false || strpos($e->getMessage(), 'unique') !== false) {
            header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode('A contact with this email already exists.'));
        } else {
            header('Location: /admin/contact.php?id=' . urlencode($contact_id) . '&error=' . urlencode('Could not update contact.'));
        }
        exit;
    }
}

if (!$contact) {
    header('Location: /admin/contacts.php?error=' . urlencode('Contact not found.'));
    exit;
}

$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
$flash_updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$created_date = $contact['created_at'] ? new DateTime($contact['created_at']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Contact: <?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?> - CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_updated): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Contact updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/contacts.php">Contacts</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <a href="/admin/contacts.php" class="btn btn-outline-secondary">Back to Contacts</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Contact</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="update_contact" value="1">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo $contact['email'] !== null ? htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?php echo $contact['phone'] !== null ? htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="company" class="form-label">Company</label>
                                <input type="text" class="form-control" id="company" name="company"
                                       value="<?php echo $contact['company'] !== null ? htmlspecialchars($contact['company'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" name="role"
                                       value="<?php echo $contact['role'] !== null ? htmlspecialchars($contact['role'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">— None —</option>
                                    <option value="active" <?php echo $contact['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $contact['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="source" class="form-label">Source</label>
                                <input type="text" class="form-control" id="source" name="source"
                                       value="<?php echo $contact['source'] !== null ? htmlspecialchars($contact['source'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="/admin/contacts.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-3"><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></p>
                        <p class="text-muted small mb-1">Contact ID</p>
                        <p class="mb-3 font-monospace small"><?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <hr>
                        <form method="POST" action="" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this contact? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="delete_contact" value="1">
                            <button type="submit" class="btn btn-outline-danger w-100">Delete Contact</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
