<?php
require_once __DIR__ . '/partials/no-cache.php';
require_once __DIR__ . '/db.php';

$stats_contacts = 0;
$stats_open_deals = 0;
$stats_open_tasks = 0;
$stats_interactions = 0;
$recent_interactions = [];
$recent_contacts = [];
$contacts = [];
$deals = [];
$users = [];
$db_error = null;

if ($pdo && !isset($db_error)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM contacts");
        $stats_contacts = (int) $stmt->fetch()['c'];
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM deals WHERE won_at IS NULL AND lost_at IS NULL");
        $stats_open_deals = (int) $stmt->fetch()['c'];
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM tasks WHERE status = 'open'");
        $stats_open_tasks = (int) $stmt->fetch()['c'];
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM interactions");
        $stats_interactions = (int) $stmt->fetch()['c'];

        $stmt = $pdo->query("
            SELECT i.id, i.type, i.direction, i.subject, i.occurred_at,
                   c.name AS contact_name, c.id AS contact_id,
                   d.name AS deal_name, d.id AS deal_id
            FROM interactions i
            INNER JOIN contacts c ON i.contact_id = c.id
            LEFT JOIN deals d ON i.deal_id = d.id
            ORDER BY i.occurred_at DESC
            LIMIT 10
        ");
        $recent_interactions = $stmt->fetchAll();

        $stmt = $pdo->query("SELECT id, name, email, company, status FROM contacts ORDER BY created_at DESC NULLS LAST LIMIT 5");
        $recent_contacts = $stmt->fetchAll();

        $contacts = $pdo->query('SELECT id, name FROM contacts ORDER BY name')->fetchAll();
        $deals = $pdo->query('SELECT id, name FROM deals ORDER BY name')->fetchAll();
        $users = $pdo->query('SELECT id, first_name FROM users ORDER BY first_name')->fetchAll();
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Contacts</h5>
                        <h2 class="card-text"><?php echo $stats_contacts; ?></h2>
                        <a href="contacts.php" class="small">View all</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Open Deals</h5>
                        <h2 class="card-text"><?php echo $stats_open_deals; ?></h2>
                        <a href="deals.php?status=open" class="small">View all</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Open Tasks</h5>
                        <h2 class="card-text"><?php echo $stats_open_tasks; ?></h2>
                        <a href="tasks.php?status=open" class="small">View all</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Interactions</h5>
                        <h2 class="card-text"><?php echo $stats_interactions; ?></h2>
                        <a href="interactions.php" class="small">View all</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="interactions.php" class="btn btn-sm btn-outline-primary">All interactions</a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger mb-0" role="alert">
                                <strong>Database error:</strong> <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php elseif (empty($recent_interactions)): ?>
                            <p class="text-muted mb-0">No interactions yet.</p>
                            <small class="text-muted">Calls, emails, meetings, SMS, and notes appear here.</small>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_interactions as $r):
                                    $occ = $r['occurred_at'] ? (new DateTime($r['occurred_at']))->format('M j, g:i A') : '—';
                                    $subj = $r['subject'] ? mb_strimwidth($r['subject'], 0, 45, '…') : ucfirst($r['type']);
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                        <div>
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(ucfirst($r['type']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <a href="interaction.php?id=<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($subj, ENT_QUOTES, 'UTF-8'); ?></a>
                                            <br>
                                            <small class="text-muted">
                                                <a href="contact.php?id=<?php echo htmlspecialchars($r['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($r['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                <?php if (!empty($r['deal_name'])): ?>
                                                    · <a href="deal.php?id=<?php echo (int) $r['deal_id']; ?>"><?php echo htmlspecialchars($r['deal_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <small class="text-muted"><?php echo $occ; ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">Add New Contact</button>
                            <a href="deal-create.php" class="btn btn-secondary">Create Deal</a>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">Create Task</button>
                            <a href="interaction-create.php" class="btn btn-outline-secondary">Log Interaction</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Contacts</h5>
                        <a href="contacts.php" class="btn btn-sm btn-outline-primary">All contacts</a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <p class="text-muted mb-0">Unable to load contacts.</p>
                        <?php elseif (empty($recent_contacts)): ?>
                            <p class="text-muted mb-0">No contacts yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_contacts as $c): ?>
                                            <tr>
                                                <td><a href="contact.php?id=<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                                <td><?php echo $c['email'] ? htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                                <td><?php echo $c['company'] ? htmlspecialchars($c['company'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                                <td><?php echo $c['status'] ? ucfirst($c['status']) : '—'; ?></td>
                                                <td><a href="contact.php?id=<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
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

    <!-- Add Contact Modal (same as on contacts.php) -->
    <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/contact-create.php" method="POST">
                    <input type="hidden" name="add_contact" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addContactModalLabel">Add Contact</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_name" name="name" required placeholder="Full name">
                        </div>
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email" placeholder="email@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="add_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="add_phone" name="phone" placeholder="+1 555 0000">
                        </div>
                        <div class="mb-3">
                            <label for="add_company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="add_company" name="company" placeholder="Company name">
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Role</label>
                            <input type="text" class="form-control" id="add_role" name="role" placeholder="Job title or role">
                        </div>
                        <div class="mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="">— None —</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_source" class="form-label">Source</label>
                            <input type="text" class="form-control" id="add_source" name="source" placeholder="e.g. Website, Referral">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Task Modal (same as on tasks.php) -->
    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/task-create.php" method="POST">
                    <input type="hidden" name="create_task" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createTaskModalLabel">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="create_title" name="title" required maxlength="65535" placeholder="Task title">
                        </div>
                        <div class="mb-3">
                            <label for="create_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="create_notes" name="notes" rows="3" placeholder="Optional notes"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="create_status" class="form-label">Status</label>
                                <select class="form-select" id="create_status" name="status">
                                    <option value="open" selected>Open</option>
                                    <option value="done">Done</option>
                                    <option value="canceled">Canceled</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="create_priority" class="form-label">Priority</label>
                                <select class="form-select" id="create_priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="create_due_at" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="create_due_at" name="due_at">
                        </div>
                        <div class="mb-3">
                            <label for="create_contact" class="form-label">Contact</label>
                            <select class="form-select" id="create_contact" name="contact_id">
                                <option value="">— None —</option>
                                <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create_deal" class="form-label">Deal</label>
                            <select class="form-select" id="create_deal" name="deal_id">
                                <option value="">— None —</option>
                                <?php foreach ($deals as $d): ?>
                                    <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create_assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="create_assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $u):
                                    $uid = (int) $u['id'];
                                    $sel = (isset($GLOBALS['logged_in_user_id']) && $GLOBALS['logged_in_user_id'] === $uid) ? ' selected' : '';
                                ?>
                                    <option value="<?php echo $uid; ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="small text-muted">At least one of Contact or Deal is required.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
    (function () {
        var modal = document.getElementById('createTaskModal');
        if (modal) {
            var form = modal.querySelector('form');
            var contact = document.getElementById('create_contact');
            var deal = document.getElementById('create_deal');
            if (form && contact && deal) {
                form.addEventListener('submit', function (e) {
                    var c = contact.value.trim();
                    var d = deal.value.trim();
                    if (!c && !d) {
                        e.preventDefault();
                        alert('Please select at least one of Contact or Deal.');
                        return false;
                    }
                });
            }
        }
    })();
    </script>
</body>
</html>
