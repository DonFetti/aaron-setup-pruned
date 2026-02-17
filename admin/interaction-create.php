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

$contacts = [];
$deals = [];
$companies = [];
if ($pdo && !isset($db_error)) {
    try {
        $contacts = $pdo->query('SELECT id, name FROM contacts ORDER BY name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
    try {
        $deals = $pdo->query('SELECT id, name FROM deals ORDER BY name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
    try {
        $companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
}

$allowed_types = ['call', 'email', 'meeting', 'sms', 'note'];
$allowed_directions = ['inbound', 'outbound'];

// POST: create interaction and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_interaction'])) {
    $contact_id = isset($_POST['contact_id']) ? trim((string) $_POST['contact_id']) : '';
    $deal_id_raw = isset($_POST['deal_id']) ? trim((string) $_POST['deal_id']) : '';
    $company_id_raw = isset($_POST['company_id']) ? trim((string) $_POST['company_id']) : '';
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
    $company_id = (isset($company_id_raw) && $company_id_raw !== '' && ctype_digit($company_id_raw)) ? (int) $company_id_raw : null;
    $direction = isset($_POST['direction']) ? trim((string) $_POST['direction']) : null;
    $subject = isset($_POST['subject']) ? trim((string) $_POST['subject']) : null;
    $body = isset($_POST['body']) ? trim((string) $_POST['body']) : null;
    $occurred_date = isset($_POST['occurred_date']) ? trim((string) $_POST['occurred_date']) : '';
    $occurred_time = isset($_POST['occurred_time']) ? trim((string) $_POST['occurred_time']) : '';

    $err = [];
    if ($contact_id === '') {
        $err[] = 'Contact is required.';
    }
    if ($type === '') {
        $err[] = 'Type is required.';
    } elseif (!in_array($type, $allowed_types, true)) {
        $err[] = 'Invalid type.';
    }
    if ($direction !== null && $direction !== '') {
        if (!in_array($direction, $allowed_directions, true)) {
            $direction = null;
        }
    } else {
        $direction = null;
    }

    $deal_id = null;
    if ($deal_id_raw !== '' && ctype_digit($deal_id_raw)) {
        $deal_id = (int) $deal_id_raw;
        if ($deal_id < 1) {
            $deal_id = null;
        }
    }

    $occurred_at = null;
    if ($occurred_date !== '' || $occurred_time !== '') {
        $date_part = $occurred_date !== '' ? $occurred_date : date('Y-m-d');
        $time_part = $occurred_time !== '' ? $occurred_time : '00:00';
        $dt = DateTime::createFromFormat('Y-m-d H:i', $date_part . ' ' . $time_part);
        if ($dt) {
            $occurred_at = $dt->format('Y-m-d H:i:s');
        } else {
            $err[] = 'Occurred date/time must be valid.';
        }
    }

    if (!empty($err)) {
        header('Location: /admin/interaction-create.php?error=' . urlencode(implode(' ', $err)));
        exit;
    }

    if (!$pdo || isset($db_error)) {
        header('Location: /admin/interaction-create.php?error=' . urlencode('Database unavailable.'));
        exit;
    }

    try {
        $st = $pdo->prepare("SELECT id FROM contacts WHERE id = :id LIMIT 1");
        $st->execute([':id' => $contact_id]);
        if (!$st->fetch()) {
            header('Location: /admin/interaction-create.php?error=' . urlencode('Selected contact not found.'));
            exit;
        }
    } catch (PDOException $e) {
        header('Location: /admin/interaction-create.php?error=' . urlencode('Database error.'));
        exit;
    }

    if ($deal_id !== null) {
        try {
            $st = $pdo->prepare("SELECT id FROM deals WHERE id = :id LIMIT 1");
            $st->execute([':id' => $deal_id]);
            if (!$st->fetch()) {
                $deal_id = null;
            }
        } catch (PDOException $e) {
            $deal_id = null;
        }
    }

    try {
        if ($occurred_at !== null) {
            $sql = "INSERT INTO interactions (contact_id, deal_id, company_id, type, direction, subject, body, occurred_at, created_by)
                    VALUES (:contact_id, :deal_id, :company_id, :type, :direction, :subject, :body, CAST(:occurred_at AS timestamp without time zone), :created_by)";
            $params = [
                ':contact_id'  => $contact_id,
                ':deal_id'     => $deal_id,
                ':company_id'  => $company_id,
                ':type'        => $type,
                ':direction'   => $direction,
                ':subject'     => $subject ?: null,
                ':body'        => $body ?: null,
                ':occurred_at' => $occurred_at,
                ':created_by'  => $user_id,
            ];
        } else {
            $sql = "INSERT INTO interactions (contact_id, deal_id, company_id, type, direction, subject, body, created_by)
                    VALUES (:contact_id, :deal_id, :company_id, :type, :direction, :subject, :body, :created_by)";
            $params = [
                ':contact_id' => $contact_id,
                ':deal_id'    => $deal_id,
                ':company_id' => $company_id,
                ':type'       => $type,
                ':direction'  => $direction,
                ':subject'    => $subject ?: null,
                ':body'       => $body ?: null,
                ':created_by' => $user_id,
            ];
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        header('Location: /admin/interactions.php?created=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/interaction-create.php?error=' . urlencode('Could not create interaction.'));
        exit;
    }
}

// GET: show form
require_once __DIR__ . '/partials/no-cache.php';
$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
$default_date = date('Y-m-d');
$default_time = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Add Interaction - CRM Dashboard</title>
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

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/interactions.php">Interactions</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Interaction</li>
                    </ol>
                </nav>
                <a href="/admin/interactions.php" class="btn btn-outline-secondary">Back to Interactions</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add Interaction</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="create_interaction" value="1">
                            <div class="mb-3">
                                <label for="contact_id" class="form-label">Contact <span class="text-danger">*</span></label>
                                <select class="form-select" id="contact_id" name="contact_id" required>
                                    <option value="">— Select contact —</option>
                                    <?php foreach ($contacts as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="deal_id" class="form-label">Deal</label>
                                <select class="form-select" id="deal_id" name="deal_id">
                                    <option value="">— None —</option>
                                    <?php foreach ($deals as $d): ?>
                                        <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company</label>
                                <select class="form-select" id="company_id" name="company_id">
                                    <option value="">— None —</option>
                                    <?php foreach ($companies as $co): ?>
                                        <option value="<?php echo (int) $co['id']; ?>"><?php echo htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="">— Select type —</option>
                                        <option value="call">Call</option>
                                        <option value="email">Email</option>
                                        <option value="meeting">Meeting</option>
                                        <option value="sms">SMS</option>
                                        <option value="note">Note</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="direction" class="form-label">Direction</label>
                                    <select class="form-select" id="direction" name="direction">
                                        <option value="">— None —</option>
                                        <option value="inbound">Inbound</option>
                                        <option value="outbound">Outbound</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief subject or summary">
                            </div>
                            <div class="mb-3">
                                <label for="body" class="form-label">Body / Notes</label>
                                <textarea class="form-control" id="body" name="body" rows="4" placeholder="Details of the interaction..."></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="occurred_date" class="form-label">Occurred date</label>
                                    <input type="date" class="form-control" id="occurred_date" name="occurred_date" value="<?php echo htmlspecialchars($default_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="occurred_time" class="form-label">Occurred time</label>
                                    <input type="time" class="form-control" id="occurred_time" name="occurred_time" value="<?php echo htmlspecialchars($default_time, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <p class="text-muted small">Leave date/time as is for “now”, or change to backdate the interaction.</p>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Create Interaction</button>
                                <a href="/admin/interactions.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
