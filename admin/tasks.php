<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Tasks - CRM Dashboard</title>
    <!-- Import bootstrap with main.css -->
    <?php include 'partials/header.php'; ?>
</head>
<body>
    <!-- Navbar -->
    <?php include 'partials/nav.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <?php
        // Get filter parameters
        $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
        $filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';

        // Initialize stats
        $stats_open = 0;
        $stats_done = 0;
        $stats_high_priority = 0;
        $stats_overdue = 0;
        $tasks = [];

        $contacts = [];
        $deals = [];
        $users = [];

        if ($pdo && !isset($db_error)) {
            try {
                $contacts = $pdo->query('SELECT id, "name" FROM contacts ORDER BY "name"')->fetchAll();
            } catch (PDOException $e) { /* keep empty */ }
            try {
                $deals = $pdo->query('SELECT id, "name" FROM deals ORDER BY "name"')->fetchAll();
            } catch (PDOException $e) { /* keep empty */ }
            try {
                $users = $pdo->query('SELECT id, first_name FROM users ORDER BY first_name')->fetchAll();
            } catch (PDOException $e) { /* keep empty */ }
        }

        if ($pdo && !isset($db_error)) {
            try {
                // Get stats
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'open'");
                $stats_open = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'done'");
                $stats_done = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE priority = 'high' AND status != 'done'");
                $stats_high_priority = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE due_at IS NOT NULL AND due_at < NOW() AND status NOT IN ('done', 'canceled')");
                $stats_overdue = $stmt->fetch()['count'];

                // Build query for tasks
                $sql = "SELECT 
                    t.id,
                    t.title,
                    t.status,
                    t.priority,
                    t.due_at,
                    t.created_at,
                    t.notes,
                    c.name as contact_name,
                    d.name as deal_name,
                    u.first_name as assigned_to_name
                FROM tasks t
                LEFT JOIN contacts c ON t.contact_id = c.id
                LEFT JOIN deals d ON t.deal_id = d.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE 1=1";

                $params = [];

                if ($filter_status) {
                    $sql .= " AND t.status = :status";
                    $params[':status'] = $filter_status;
                }

                if ($filter_priority) {
                    $sql .= " AND t.priority = :priority";
                    $params[':priority'] = $filter_priority;
                }

                $sql .= " ORDER BY 
                    CASE t.priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END,
                    t.due_at ASC NULLS LAST,
                    t.created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $tasks = $stmt->fetchAll();
            } catch (PDOException $e) {
                $db_error = $e->getMessage();
            }
        }
        ?>

        <?php
        $flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
        $flash_created = isset($_GET['created']) && $_GET['created'] === '1';
        ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_created): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Task created successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2>Tasks</h2>
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createTaskModal">Create New Task</button>
            </div>
        </div>

        <div class="row">
            <!-- Stats Cards -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Open Tasks</h5>
                        <h2 class="card-text"><?php echo $stats_open; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Done</h5>
                        <h2 class="card-text"><?php echo $stats_done; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">High Priority</h5>
                        <h2 class="card-text"><?php echo $stats_high_priority; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Overdue</h5>
                        <h2 class="card-text"><?php echo $stats_overdue; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Table -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Tasks</h5>
                        <form method="GET" action="" class="d-flex gap-2">
                            <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="done" <?php echo $filter_status === 'done' ? 'selected' : ''; ?>>Done</option>
                                <option value="canceled" <?php echo $filter_status === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                            </select>
                            <select name="priority" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                            <?php if ($filter_status || $filter_priority): ?>
                                <a href="tasks.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php elseif (empty($tasks)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Due Date</th>
                                            <th>Assigned To</th>
                                            <th>Related To</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No tasks found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Due Date</th>
                                            <th>Assigned To</th>
                                            <th>Related To</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): 
                                            $due_date = $task['due_at'] ? new DateTime($task['due_at']) : null;
                                            $is_overdue = $due_date && $due_date < new DateTime() && $task['status'] !== 'done' && $task['status'] !== 'canceled';
                                            $created_date = new DateTime($task['created_at']);
                                            
                                            // Status badge classes
                                            $status_class = '';
                                            if ($task['status'] === 'open') $status_class = 'bg-primary';
                                            elseif ($task['status'] === 'done') $status_class = 'bg-success';
                                            elseif ($task['status'] === 'canceled') $status_class = 'bg-secondary';
                                            
                                            // Priority badge classes
                                            $priority_class = '';
                                            if ($task['priority'] === 'high') $priority_class = 'bg-danger';
                                            elseif ($task['priority'] === 'medium') $priority_class = 'bg-warning';
                                            elseif ($task['priority'] === 'low') $priority_class = 'bg-info';
                                            
                                            // Related to
                                            $related_to = '';
                                            if ($task['contact_name']) {
                                                $related_to = 'Contact: ' . htmlspecialchars($task['contact_name'], ENT_QUOTES, 'UTF-8');
                                            } elseif ($task['deal_name']) {
                                                $related_to = 'Deal: ' . htmlspecialchars($task['deal_name'], ENT_QUOTES, 'UTF-8');
                                            } else {
                                                $related_to = '<span class="text-muted">—</span>';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($task['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $priority_class; ?>">
                                                        <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($due_date): ?>
                                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo $due_date->format('M d, Y'); ?>
                                                            <?php if ($is_overdue): ?>
                                                                <span class="badge bg-danger">Overdue</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Unassigned</span>'; ?>
                                                </td>
                                                <td><?php echo $related_to; ?></td>
                                                <td><?php echo $created_date->format('M d, Y'); ?></td>
                                                <td>
                                                    <a href="task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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
    </div>

    <!-- Create Task Modal -->
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
                                <?php foreach ($contacts as $c):
                                    $cname = $c['name'] ?? $c['Name'] ?? '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create_deal" class="form-label">Deal</label>
                            <select class="form-select" id="create_deal" name="deal_id">
                                <option value="">— None —</option>
                                <?php foreach ($deals as $d):
                                    $dname = $d['name'] ?? $d['Name'] ?? '';
                                ?>
                                    <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($dname, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="create_assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="create_assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $u):
                                    $uid = (int) $u['id'];
                                    $sel = (isset($logged_in_user_id) && $logged_in_user_id === $uid) ? ' selected' : '';
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
        var form = document.getElementById('createTaskModal').querySelector('form');
        var contact = document.getElementById('create_contact');
        var deal = document.getElementById('create_deal');
        form.addEventListener('submit', function (e) {
            var c = contact.value.trim();
            var d = deal.value.trim();
            if (!c && !d) {
                e.preventDefault();
                alert('Please select at least one of Contact or Deal.');
                return false;
            }
        });
    })();
    </script>
</body>
</html>
