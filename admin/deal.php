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

$deal_id_raw = isset($_POST['id']) ? trim((string) $_POST['id']) : (isset($_GET['id']) ? trim((string) $_GET['id']) : '');
if ($deal_id_raw === '' || !ctype_digit($deal_id_raw)) {
    header('Location: /admin/deals.php?error=' . urlencode('Deal not specified.'));
    exit;
}
$deal_id = (int) $deal_id_raw;

$deal = null;
$contacts = [];
$companies = [];
$db_error = null;

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT d.id, d.contact_id, d.company_id, d.name, d.amount, d.stage, d.close_date, d.type,
                                     d.created_at, d.won_at, d.lost_at, d.lost_reason,
                                     c.name AS contact_name,
                                     co.name AS company_name
                              FROM deals d
                              INNER JOIN contacts c ON d.contact_id = c.id
                              LEFT JOIN companies co ON d.company_id = co.id
                              WHERE d.id = :id LIMIT 1");
        $stmt->execute([':id' => $deal_id]);
        $deal = $stmt->fetch();
        if ($deal) {
            $contacts = $pdo->query('SELECT id, name FROM contacts ORDER BY name')->fetchAll();
            $companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_deal']) && $deal) {
    if (!$pdo || isset($db_error)) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM deals WHERE id = :id");
        $stmt->execute([':id' => $deal_id]);
        header('Location: /admin/deals.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $delete_error = 'Could not delete deal. It may have related interactions.';
        if (strpos($e->getMessage(), 'foreign key') !== false || strpos($e->getMessage(), 'violates') !== false) {
            $delete_error = 'Cannot delete: this deal has related interactions.';
        }
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode($delete_error));
        exit;
    }
}

// Handle mark as won
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_won']) && $deal) {
    if (!$pdo || isset($db_error)) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE deals SET won_at = NOW(), lost_at = NULL, lost_reason = NULL, modifyed_by = :uid, modifyed_at = NOW() WHERE id = :id");
        $stmt->execute([':uid' => $user_id, ':id' => $deal_id]);
        header('Location: /admin/deal.php?id=' . $deal_id . '&updated=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Could not update deal.'));
        exit;
    }
}

// Handle mark as lost
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_lost']) && $deal) {
    if (!$pdo || isset($db_error)) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }
    $lost_reason = isset($_POST['lost_reason']) ? trim((string) $_POST['lost_reason']) : null;
    try {
        $stmt = $pdo->prepare("UPDATE deals SET lost_at = NOW(), won_at = NULL, lost_reason = :reason, modifyed_by = :uid, modifyed_at = NOW() WHERE id = :id");
        $stmt->execute([':reason' => $lost_reason ?: null, ':uid' => $user_id, ':id' => $deal_id]);
        header('Location: /admin/deal.php?id=' . $deal_id . '&updated=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Could not update deal.'));
        exit;
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_deal']) && $deal) {
    $contact_id = isset($_POST['contact_id']) ? trim((string) $_POST['contact_id']) : '';
    $company_id_raw = isset($_POST['company_id']) ? trim((string) $_POST['company_id']) : '';
    $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
    $amount_raw = isset($_POST['amount']) ? trim((string) $_POST['amount']) : '';
    $stage = isset($_POST['stage']) ? trim((string) $_POST['stage']) : null;
    $close_date_raw = isset($_POST['close_date']) ? trim((string) $_POST['close_date']) : '';
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : null;
    $company_id = (isset($company_id_raw) && $company_id_raw !== '' && ctype_digit($company_id_raw)) ? (int) $company_id_raw : null;

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
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode(implode(' ', $err)));
        exit;
    }

    if (!$pdo || isset($db_error)) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }

    try {
        $sql = "UPDATE deals SET
                    contact_id = :contact_id,
                    company_id = :company_id,
                    name = :name,
                    amount = :amount,
                    stage = :stage,
                    close_date = :close_date,
                    type = :type,
                    modifyed_by = :modified_by,
                    modifyed_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':contact_id'  => $contact_id,
            ':company_id'  => $company_id,
            ':name'        => $name,
            ':amount'      => $amount,
            ':stage'       => $stage ?: null,
            ':close_date'  => $close_date,
            ':type'        => $type ?: null,
            ':modified_by' => $user_id,
            ':id'          => $deal_id,
        ]);
        header('Location: /admin/deal.php?id=' . $deal_id . '&updated=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/deal.php?id=' . $deal_id . '&error=' . urlencode('Could not update deal.'));
        exit;
    }
}

if (!$deal) {
    header('Location: /admin/deals.php?error=' . urlencode('Deal not found.'));
    exit;
}

$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
$flash_updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$created_date = $deal['created_at'] ? new DateTime($deal['created_at']) : null;
$close_date = $deal['close_date'] ? new DateTime($deal['close_date']) : null;
$is_open = !$deal['won_at'] && !$deal['lost_at'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deal: <?php echo htmlspecialchars($deal['name'], ENT_QUOTES, 'UTF-8'); ?> - CRM Dashboard</title>
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
                Deal updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/deals.php">Deals</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($deal['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <a href="/admin/deals.php" class="btn btn-outline-secondary">Back to Deals</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Deal</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="id" value="<?php echo $deal_id; ?>">
                            <input type="hidden" name="update_deal" value="1">
                            <div class="mb-3">
                                <label for="contact_id" class="form-label">Contact <span class="text-danger">*</span></label>
                                <select class="form-select" id="contact_id" name="contact_id" required>
                                    <?php foreach ($contacts as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $c['id'] === $deal['contact_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company</label>
                                <select class="form-select" id="company_id" name="company_id">
                                    <option value="">— None —</option>
                                    <?php foreach ($companies as $co): ?>
                                        <option value="<?php echo (int) $co['id']; ?>" <?php echo $deal['company_id'] && (int) $co['id'] === (int) $deal['company_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="name" class="form-label">Deal name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($deal['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" value="<?php echo $deal['amount'] !== null ? htmlspecialchars((string) $deal['amount'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="stage" class="form-label">Stage</label>
                                <input type="text" class="form-control" id="stage" name="stage" value="<?php echo $deal['stage'] !== null ? htmlspecialchars($deal['stage'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="close_date" class="form-label">Expected close date</label>
                                <input type="date" class="form-control" id="close_date" name="close_date" value="<?php echo $close_date ? $close_date->format('Y-m-d') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <input type="text" class="form-control" id="type" name="type" value="<?php echo $deal['type'] !== null ? htmlspecialchars($deal['type'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="/admin/deals.php" class="btn btn-outline-secondary">Cancel</a>
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
                        <p class="text-muted small mb-1">Status</p>
                        <p class="mb-2">
                            <?php if ($deal['won_at']): ?>
                                <span class="badge bg-success">Won</span>
                            <?php elseif ($deal['lost_at']): ?>
                                <span class="badge bg-danger">Lost</span>
                                <?php if ($deal['lost_reason']): ?>
                                    <span class="d-block small mt-1"><?php echo htmlspecialchars($deal['lost_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-primary">Open</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-muted small mb-1">Contact</p>
                        <p class="mb-3">
                            <a href="contact.php?id=<?php echo htmlspecialchars($deal['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($deal['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                        <?php if (!empty($deal['company_name'])): ?>
                        <p class="text-muted small mb-1">Company</p>
                        <p class="mb-3">
                            <a href="company.php?id=<?php echo (int) $deal['company_id']; ?>"><?php echo htmlspecialchars($deal['company_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                        <?php endif; ?>
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-3"><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></p>
                        <p class="text-muted small mb-1">Deal ID</p>
                        <p class="mb-3 font-monospace small"><?php echo $deal_id; ?></p>

                        <?php if ($is_open): ?>
                            <hr>
                            <div class="d-flex flex-column gap-2">
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $deal_id; ?>">
                                    <input type="hidden" name="mark_won" value="1">
                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Mark this deal as Won?');">Mark as Won</button>
                                </form>
                                <form method="POST" action="" class="mark-lost-form">
                                    <input type="hidden" name="id" value="<?php echo $deal_id; ?>">
                                    <input type="hidden" name="mark_lost" value="1">
                                    <div class="mb-2">
                                        <label for="lost_reason_sidebar" class="form-label small">Reason (optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="lost_reason_sidebar" name="lost_reason" placeholder="e.g. Budget, Competitor">
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Mark this deal as Lost?');">Mark as Lost</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <hr>
                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this deal? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo $deal_id; ?>">
                            <input type="hidden" name="delete_deal" value="1">
                            <button type="submit" class="btn btn-outline-danger w-100">Delete Deal</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
