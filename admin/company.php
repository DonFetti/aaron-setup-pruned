<?php
require_once __DIR__ . '/partials/no-cache.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($company_id <= 0) {
    header('Location: /admin/companies.php?error=' . urlencode('Company not specified.'));
    exit;
}

$company = null;
$contacts = [];
$deals = [];
$tasks = [];
$interactions = [];
$db_error = null;

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, company_type, created_at FROM companies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $company_id]);
        $company = $stmt->fetch();

        if ($company) {
            $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at
                                   FROM contacts
                                   WHERE company_id = :company_id
                                   ORDER BY name ASC");
            $stmt->execute([':company_id' => $company_id]);
            $contacts = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT d.id, d.name, d.amount, d.stage, d.close_date, d.won_at, d.lost_at, d.created_at,
                                   c.name AS contact_name, c.id AS contact_id
                                   FROM deals d
                                   INNER JOIN contacts c ON d.contact_id = c.id
                                   WHERE d.company_id = :company_id
                                   ORDER BY d.created_at DESC");
            $stmt->execute([':company_id' => $company_id]);
            $deals = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT t.id, t.title, t.status, t.priority, t.due_at, t.created_at,
                                   c.name AS contact_name, c.id AS contact_id,
                                   d.name AS deal_name, d.id AS deal_id
                                   FROM tasks t
                                   LEFT JOIN contacts c ON t.contact_id = c.id
                                   LEFT JOIN deals d ON t.deal_id = d.id
                                   WHERE t.company_id = :company_id
                                   ORDER BY CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END, t.due_at ASC NULLS LAST");
            $stmt->execute([':company_id' => $company_id]);
            $tasks = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT i.id, i.type, i.direction, i.subject, i.occurred_at, i.created_at,
                                   c.name AS contact_name, c.id AS contact_id,
                                   d.name AS deal_name, d.id AS deal_id
                                   FROM interactions i
                                   INNER JOIN contacts c ON i.contact_id = c.id
                                   LEFT JOIN deals d ON i.deal_id = d.id
                                   WHERE i.company_id = :company_id
                                   ORDER BY i.occurred_at DESC");
            $stmt->execute([':company_id' => $company_id]);
            $interactions = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}

if (!$company) {
    header('Location: /admin/companies.php?error=' . urlencode('Company not found.'));
    exit;
}

$created_date = !empty($company['created_at']) ? new DateTime($company['created_at']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Company: <?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?> - CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php if (isset($db_error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/companies.php">Companies</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <a href="/admin/companies.php" class="btn btn-outline-secondary">Back to Companies</a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Company Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-1">Name</p>
                        <p class="mb-3"><?php echo htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted small mb-1">Type</p>
                        <p class="mb-3"><?php echo htmlspecialchars($company['company_type'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-3"><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></p>
                        <p class="text-muted small mb-1">Company ID</p>
                        <p class="mb-0 font-monospace small"><?php echo (int) $company['id']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contacts (<?php echo count($contacts); ?>)</h5>
                        <a href="/admin/contacts.php" class="btn btn-sm btn-primary">Add Contact</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contacts)): ?>
                            <p class="text-muted mb-0">No contacts linked to this company yet. <a href="/admin/contacts.php">Add a contact</a> and select this company.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <?php
                                            $contact_created = !empty($contact['created_at']) ? new DateTime($contact['created_at']) : null;
                                            $status_class = $contact['status'] === 'active' ? 'bg-success' : ($contact['status'] === 'inactive' ? 'bg-secondary' : '');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo $contact['email'] ? htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $contact['role'] ? htmlspecialchars($contact['role'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($contact['status']): ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($contact['status']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $contact_created ? $contact_created->format('M d, Y') : '—'; ?></td>
                                                <td>
                                                    <a href="contact.php?id=<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deals -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Deals (<?php echo count($deals); ?>)</h5>
                        <a href="/admin/deal-create.php" class="btn btn-sm btn-primary">Add Deal</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($deals)): ?>
                            <p class="text-muted mb-0">No deals linked to this company yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Deal</th>
                                            <th>Contact</th>
                                            <th>Amount</th>
                                            <th>Stage</th>
                                            <th>Close Date</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deals as $deal): ?>
                                            <?php
                                            $deal_created = !empty($deal['created_at']) ? new DateTime($deal['created_at']) : null;
                                            $close_date = !empty($deal['close_date']) ? new DateTime($deal['close_date']) : null;
                                            if ($deal['won_at']) { $deal_status = 'won'; $deal_status_class = 'bg-success'; }
                                            elseif ($deal['lost_at']) { $deal_status = 'lost'; $deal_status_class = 'bg-danger'; }
                                            else { $deal_status = 'open'; $deal_status_class = 'bg-primary'; }
                                            $amount = $deal['amount'] !== null ? number_format((float) $deal['amount'], 2) : null;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($deal['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><a href="contact.php?id=<?php echo htmlspecialchars($deal['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($deal['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                                <td><?php echo $amount !== null ? '$' . $amount : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $deal['stage'] ? htmlspecialchars($deal['stage'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $close_date ? $close_date->format('M d, Y') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><span class="badge <?php echo $deal_status_class; ?>"><?php echo ucfirst($deal_status); ?></span></td>
                                                <td><?php echo $deal_created ? $deal_created->format('M d, Y') : '—'; ?></td>
                                                <td><a href="deal.php?id=<?php echo (int) $deal['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Tasks (<?php echo count($tasks); ?>)</h5>
                        <a href="/admin/tasks.php" class="btn btn-sm btn-primary">Add Task</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <p class="text-muted mb-0">No tasks linked to this company yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Due Date</th>
                                            <th>Related To</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <?php
                                            $task_due = !empty($task['due_at']) ? new DateTime($task['due_at']) : null;
                                            $task_created = !empty($task['created_at']) ? new DateTime($task['created_at']) : null;
                                            $task_status_class = $task['status'] === 'open' ? 'bg-primary' : ($task['status'] === 'done' ? 'bg-success' : 'bg-secondary');
                                            $task_priority_class = $task['priority'] === 'high' ? 'bg-danger' : ($task['priority'] === 'medium' ? 'bg-warning' : 'bg-info');
                                            $related = $task['contact_name'] ? ('Contact: ' . $task['contact_name']) : ($task['deal_name'] ? ('Deal: ' . $task['deal_name']) : '—');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="badge <?php echo $task_status_class; ?>"><?php echo ucfirst($task['status']); ?></span></td>
                                                <td><span class="badge <?php echo $task_priority_class; ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                                <td><?php echo $task_due ? $task_due->format('M d, Y') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $task['contact_name'] ? '<a href="contact.php?id=' . htmlspecialchars($task['contact_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($task['contact_name'], ENT_QUOTES, 'UTF-8') . '</a>' : ($task['deal_name'] ? '<a href="deal.php?id=' . (int) $task['deal_id'] . '">' . htmlspecialchars($task['deal_name'], ENT_QUOTES, 'UTF-8') . '</a>' : '<span class="text-muted">—</span>'); ?></td>
                                                <td><?php echo $task_created ? $task_created->format('M d, Y') : '—'; ?></td>
                                                <td><a href="task.php?id=<?php echo (int) $task['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Interactions (<?php echo count($interactions); ?>)</h5>
                        <a href="/admin/interaction-create.php" class="btn btn-sm btn-primary">Add Interaction</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($interactions)): ?>
                            <p class="text-muted mb-0">No interactions linked to this company yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Direction</th>
                                            <th>Subject</th>
                                            <th>Contact</th>
                                            <th>Deal</th>
                                            <th>Occurred</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($interactions as $int): ?>
                                            <?php
                                            $int_occurred = !empty($int['occurred_at']) ? new DateTime($int['occurred_at']) : null;
                                            $subject_display = $int['subject'] !== null && $int['subject'] !== '' ? htmlspecialchars(mb_strimwidth($int['subject'], 0, 50, '…'), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>';
                                            $int_type_class = $int['type'] === 'call' ? 'bg-info' : ($int['type'] === 'email' ? 'bg-primary' : ($int['type'] === 'meeting' ? 'bg-success' : 'bg-secondary'));
                                            ?>
                                            <tr>
                                                <td><span class="badge <?php echo $int_type_class; ?>"><?php echo ucfirst($int['type']); ?></span></td>
                                                <td><?php echo $int['direction'] ? '<span class="badge bg-light text-dark">' . ucfirst($int['direction']) . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $subject_display; ?></td>
                                                <td><a href="contact.php?id=<?php echo htmlspecialchars($int['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($int['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                                <td><?php echo $int['deal_id'] && $int['deal_name'] ? '<a href="deal.php?id=' . (int) $int['deal_id'] . '">' . htmlspecialchars($int['deal_name'], ENT_QUOTES, 'UTF-8') . '</a>' : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $int_occurred ? $int_occurred->format('M d, Y H:i') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><a href="interaction.php?id=<?php echo (int) $int['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
