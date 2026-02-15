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
if ($pdo && !isset($db_error)) {
    try {
        $contacts = $pdo->query('SELECT id, name FROM contacts ORDER BY name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
}

// POST: create deal and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_deal'])) {
    $contact_id = isset($_POST['contact_id']) ? trim((string) $_POST['contact_id']) : '';
    $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
    $amount_raw = isset($_POST['amount']) ? trim((string) $_POST['amount']) : '';
    $stage = isset($_POST['stage']) ? trim((string) $_POST['stage']) : null;
    $close_date_raw = isset($_POST['close_date']) ? trim((string) $_POST['close_date']) : '';
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : null;

    $err = [];
    if ($contact_id === '') {
        $err[] = 'Contact is required.';
    }
    if ($name === '') {
        $err[] = 'Deal name is required.';
    }

    $amount = null;
    if ($amount_raw !== '') {
        if (is_numeric($amount_raw) && (float) $amount_raw >= 0) {
            $amount = (float) $amount_raw;
        } else {
            $err[] = 'Amount must be a non-negative number.';
        }
    }

    $close_date = null;
    if ($close_date_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $close_date_raw);
        if ($dt) {
            $close_date = $dt->format('Y-m-d');
        } else {
            $err[] = 'Close date must be a valid date.';
        }
    }

    if (!empty($err)) {
        header('Location: /admin/deal-create.php?error=' . urlencode(implode(' ', $err)));
        exit;
    }

    if (!$pdo || isset($db_error)) {
        header('Location: /admin/deal-create.php?error=' . urlencode('Database unavailable.'));
        exit;
    }

    // Verify contact exists
    try {
        $st = $pdo->prepare("SELECT id FROM contacts WHERE id = :id LIMIT 1");
        $st->execute([':id' => $contact_id]);
        if (!$st->fetch()) {
            header('Location: /admin/deal-create.php?error=' . urlencode('Selected contact not found.'));
            exit;
        }
    } catch (PDOException $e) {
        header('Location: /admin/deal-create.php?error=' . urlencode('Database error.'));
        exit;
    }

    try {
        $sql = "INSERT INTO deals (contact_id, name, amount, stage, close_date, type, created_by)
                VALUES (:contact_id, :name, :amount, :stage, :close_date, :type, :created_by)";
        $params = [
            ':contact_id' => $contact_id,
            ':name'       => $name,
            ':amount'     => $amount,
            ':stage'      => $stage ?: null,
            ':close_date' => $close_date,
            ':type'       => $type ?: null,
            ':created_by' => $user_id,
        ];
        $st = $pdo->prepare($sql);
        $st->execute($params);
        header('Location: /admin/deals.php?created=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/deal-create.php?error=' . urlencode('Could not create deal.'));
        exit;
    }
}

// GET: show form
require_once __DIR__ . '/partials/no-cache.php';
$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Add Deal - CRM Dashboard</title>
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
                        <li class="breadcrumb-item"><a href="/admin/deals.php">Deals</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Deal</li>
                    </ol>
                </nav>
                <a href="/admin/deals.php" class="btn btn-outline-secondary">Back to Deals</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add Deal</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="create_deal" value="1">
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
                                <label for="name" class="form-label">Deal name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Acme Corp – Website project">
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="stage" class="form-label">Stage</label>
                                <input type="text" class="form-control" id="stage" name="stage" placeholder="e.g. Lead, Qualified, Proposal">
                            </div>
                            <div class="mb-3">
                                <label for="close_date" class="form-label">Expected close date</label>
                                <input type="date" class="form-control" id="close_date" name="close_date">
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <input type="text" class="form-control" id="type" name="type" placeholder="e.g. New business, Renewal">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Create Deal</button>
                                <a href="/admin/deals.php" class="btn btn-outline-secondary">Cancel</a>
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
