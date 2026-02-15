<?php
require_once __DIR__ . '/partials/no-cache.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$interaction_id_raw = isset($_POST['id']) ? trim((string) $_POST['id']) : (isset($_GET['id']) ? trim((string) $_GET['id']) : '');
if ($interaction_id_raw === '' || !ctype_digit($interaction_id_raw)) {
    header('Location: /admin/interactions.php?error=' . urlencode('Interaction not specified.'));
    exit;
}
$interaction_id = (int) $interaction_id_raw;

$interaction = null;
$contacts = [];
$deals = [];
$db_error = null;
$allowed_types = ['call', 'email', 'meeting', 'sms', 'note'];
$allowed_directions = ['inbound', 'outbound'];

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT i.id, i.contact_id, i.deal_id, i.type, i.direction, i.subject, i.body,
                                      i.occurred_at, i.created_at,
                                      c.name AS contact_name,
                                      d.name AS deal_name
                               FROM interactions i
                               INNER JOIN contacts c ON i.contact_id = c.id
                               LEFT JOIN deals d ON i.deal_id = d.id
                               WHERE i.id = :id LIMIT 1");
        $stmt->execute([':id' => $interaction_id]);
        $interaction = $stmt->fetch();
        if ($interaction) {
            $contacts = $pdo->query('SELECT id, name FROM contacts ORDER BY name')->fetchAll();
            $deals = $pdo->query('SELECT id, name FROM deals ORDER BY name')->fetchAll();
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_interaction']) && $interaction) {
    if (!$pdo || isset($db_error)) {
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM interactions WHERE id = :id");
        $stmt->execute([':id' => $interaction_id]);
        header('Location: /admin/interactions.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&error=' . urlencode('Could not delete interaction.'));
        exit;
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interaction']) && $interaction) {
    $contact_id = isset($_POST['contact_id']) ? trim((string) $_POST['contact_id']) : '';
    $deal_id_raw = isset($_POST['deal_id']) ? trim((string) $_POST['deal_id']) : '';
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
    $direction = isset($_POST['direction']) ? trim((string) $_POST['direction']) : null;
    $subject = isset($_POST['subject']) ? trim((string) $_POST['subject']) : null;
    $body = isset($_POST['body']) ? trim((string) $_POST['body']) : null;
    $occurred_date = isset($_POST['occurred_date']) ? trim((string) $_POST['occurred_date']) : '';
    $occurred_time = isset($_POST['occurred_time']) ? trim((string) $_POST['occurred_time']) : '';

    $err = [];
    if ($contact_id === '') {
        $err[] = 'Contact is required.';
    }
    if ($type === '' || !in_array($type, $allowed_types, true)) {
        $err[] = 'Valid type is required.';
    }
    if ($direction !== null && $direction !== '' && !in_array($direction, $allowed_directions, true)) {
        $direction = null;
    }
    if ($direction === '') {
        $direction = null;
    }

    $deal_id = null;
    if ($deal_id_raw !== '' && ctype_digit($deal_id_raw)) {
        $deal_id = (int) $deal_id_raw;
        if ($deal_id < 1) $deal_id = null;
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
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&error=' . urlencode(implode(' ', $err)));
        exit;
    }

    if (!$pdo || isset($db_error)) {
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&error=' . urlencode('Database unavailable.'));
        exit;
    }

    try {
        $sql = "UPDATE interactions SET contact_id = :contact_id, deal_id = :deal_id, type = :type, direction = :direction,
                subject = :subject, body = :body, occurred_at = CAST(:occurred_at AS timestamp without time zone)
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':contact_id'  => $contact_id,
            ':deal_id'     => $deal_id,
            ':type'        => $type,
            ':direction'   => $direction,
            ':subject'     => $subject ?: null,
            ':body'        => $body ?: null,
            ':occurred_at' => $occurred_at ?: $interaction['occurred_at'],
            ':id'          => $interaction_id,
        ]);
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&updated=1');
        exit;
    } catch (PDOException $e) {
        header('Location: /admin/interaction.php?id=' . $interaction_id . '&error=' . urlencode('Could not update interaction.'));
        exit;
    }
}

if (!$interaction) {
    header('Location: /admin/interactions.php?error=' . urlencode('Interaction not found.'));
    exit;
}

$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
$flash_updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$occurred_dt = $interaction['occurred_at'] ? new DateTime($interaction['occurred_at']) : null;
$created_dt = $interaction['created_at'] ? new DateTime($interaction['created_at']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Interaction - CRM Dashboard</title>
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
                Interaction updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/interactions.php">Interactions</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $interaction['subject'] ? htmlspecialchars(mb_strimwidth($interaction['subject'], 0, 40, '…'), ENT_QUOTES, 'UTF-8') : ucfirst($interaction['type']); ?></li>
                    </ol>
                </nav>
                <a href="/admin/interactions.php" class="btn btn-outline-secondary">Back to Interactions</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit Interaction</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="id" value="<?php echo $interaction_id; ?>">
                            <input type="hidden" name="update_interaction" value="1">
                            <div class="mb-3">
                                <label for="contact_id" class="form-label">Contact <span class="text-danger">*</span></label>
                                <select class="form-select" id="contact_id" name="contact_id" required>
                                    <?php foreach ($contacts as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $c['id'] === $interaction['contact_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="deal_id" class="form-label">Deal</label>
                                <select class="form-select" id="deal_id" name="deal_id">
                                    <option value="">— None —</option>
                                    <?php foreach ($deals as $d): ?>
                                        <option value="<?php echo (int) $d['id']; ?>" <?php echo $interaction['deal_id'] && (int) $d['id'] === (int) $interaction['deal_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type" name="type" required>
                                        <?php foreach (['call', 'email', 'meeting', 'sms', 'note'] as $t): ?>
                                            <option value="<?php echo $t; ?>" <?php echo $interaction['type'] === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="direction" class="form-label">Direction</label>
                                    <select class="form-select" id="direction" name="direction">
                                        <option value="">— None —</option>
                                        <option value="inbound" <?php echo $interaction['direction'] === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                                        <option value="outbound" <?php echo $interaction['direction'] === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo $interaction['subject'] !== null ? htmlspecialchars($interaction['subject'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="body" class="form-label">Body / Notes</label>
                                <textarea class="form-control" id="body" name="body" rows="4"><?php echo $interaction['body'] !== null ? htmlspecialchars($interaction['body'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="occurred_date" class="form-label">Occurred date</label>
                                    <input type="date" class="form-control" id="occurred_date" name="occurred_date" value="<?php echo $occurred_dt ? $occurred_dt->format('Y-m-d') : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="occurred_time" class="form-label">Occurred time</label>
                                    <input type="time" class="form-control" id="occurred_time" name="occurred_time" value="<?php echo $occurred_dt ? $occurred_dt->format('H:i') : ''; ?>">
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="/admin/interactions.php" class="btn btn-outline-secondary">Cancel</a>
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
                        <p class="text-muted small mb-1">Contact</p>
                        <p class="mb-3">
                            <a href="contact.php?id=<?php echo htmlspecialchars($interaction['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($interaction['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                        <?php if ($interaction['deal_id'] && $interaction['deal_name']): ?>
                            <p class="text-muted small mb-1">Deal</p>
                            <p class="mb-3">
                                <a href="deal.php?id=<?php echo (int) $interaction['deal_id']; ?>"><?php echo htmlspecialchars($interaction['deal_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </p>
                        <?php endif; ?>
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-3"><?php echo $created_dt ? $created_dt->format('M d, Y H:i') : '—'; ?></p>
                        <p class="text-muted small mb-1">ID</p>
                        <p class="mb-3 font-monospace small"><?php echo $interaction_id; ?></p>
                        <hr>
                        <form method="POST" action="" onsubmit="return confirm('Delete this interaction? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?php echo $interaction_id; ?>">
                            <input type="hidden" name="delete_interaction" value="1">
                            <button type="submit" class="btn btn-outline-danger w-100">Delete Interaction</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
