<?php
require_once __DIR__ . '/partials/no-cache.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle POST requests for update and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = isset($_POST['task_id']) ? trim((string) $_POST['task_id']) : '';
    
    if ($task_id === '' || !ctype_digit($task_id)) {
        header('Location: /admin/tasks.php?error=' . urlencode('Invalid task ID.'));
        exit;
    }
    
    // Handle mark as done
    if (isset($_POST['mark_done'])) {
        if (!$pdo || isset($db_error)) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Database unavailable.'));
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'done' WHERE id = :id");
            $stmt->execute([':id' => (int) $task_id]);
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&marked_done=1');
            exit;
        } catch (PDOException $e) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Could not mark task as done.'));
            exit;
        }
    }
    
    // Handle delete
    if (isset($_POST['delete_task'])) {
        if (!$pdo || isset($db_error)) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Database unavailable.'));
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
            $stmt->execute([':id' => (int) $task_id]);
            header('Location: /admin/tasks.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Could not delete task.'));
            exit;
        }
    }
    
    // Handle update
    if (isset($_POST['update_task'])) {
        $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;
        $status = isset($_POST['status']) ? (string) $_POST['status'] : 'open';
        $priority = isset($_POST['priority']) ? (string) $_POST['priority'] : 'medium';
        $deal_id_raw = isset($_POST['deal_id']) ? trim((string) $_POST['deal_id']) : '';
        $company_id_raw = isset($_POST['company_id']) ? trim((string) $_POST['company_id']) : '';
        $assigned_to_raw = isset($_POST['assigned_to']) ? trim((string) $_POST['assigned_to']) : '';
        $due_at_raw = isset($_POST['due_at']) ? trim((string) $_POST['due_at']) : '';
        $company_id = (isset($company_id_raw) && $company_id_raw !== '' && ctype_digit($company_id_raw)) ? (int) $company_id_raw : null;
        
        $allowed_status = ['open', 'done', 'canceled'];
        if (!in_array($status, $allowed_status, true)) {
            $status = 'open';
        }
        
        $allowed_priority = ['low', 'medium', 'high'];
        if (!in_array($priority, $allowed_priority, true)) {
            $priority = 'medium';
        }
        
        $deal_id = null;
        if ($deal_id_raw !== '') {
            $deal_id = ctype_digit($deal_id_raw) ? (int) $deal_id_raw : null;
            if ($deal_id !== null && $deal_id < 1) {
                $deal_id = null;
            }
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
        
        if (!$pdo || isset($db_error)) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Database unavailable.'));
            exit;
        }
        
        try {
            $sql = "UPDATE tasks 
                    SET notes = :notes, 
                        status = :status, 
                        priority = :priority, 
                        due_at = :due_at, 
                        assigned_to = :assigned_to, 
                        deal_id = :deal_id,
                        company_id = :company_id
                    WHERE id = :id";
            $params = [
                ':notes'       => $notes ?: null,
                ':status'      => $status,
                ':priority'    => $priority,
                ':due_at'      => $due_at,
                ':assigned_to' => $assigned_to,
                ':deal_id'     => $deal_id,
                ':company_id'  => $company_id,
                ':id'          => (int) $task_id,
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&updated=1');
            exit;
        } catch (PDOException $e) {
            header('Location: /admin/task.php?id=' . urlencode($task_id) . '&error=' . urlencode('Could not update task.'));
            exit;
        }
    }
}

// Get task ID from URL
$task_id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$task = null;
$error = null;
$deals = [];
$companies = [];
$users = [];

// Validate task ID
if ($task_id === '' || !ctype_digit($task_id)) {
    $error = 'Invalid task ID.';
} elseif (!$pdo || isset($db_error)) {
    $error = 'Database connection unavailable.';
} else {
    // Fetch deals, companies, and users for the edit form
    try {
        $deals = $pdo->query('SELECT id, "name" FROM deals ORDER BY "name"')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
    try {
        $companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
    try {
        $users = $pdo->query('SELECT id, first_name FROM users ORDER BY first_name')->fetchAll();
    } catch (PDOException $e) { /* keep empty */ }
    
    try {
        // Fetch task with related data
        $sql = "SELECT 
            t.id,
            t.title,
            t.status,
            t.priority,
            t.due_at,
            t.created_at,
            t.notes,
            t.contact_id,
            t.deal_id,
            t.company_id,
            t.assigned_to,
            c.name as contact_name,
            d.name as deal_name,
            co.name as company_name,
            u.first_name as assigned_to_name
        FROM tasks t
        LEFT JOIN contacts c ON t.contact_id = c.id
        LEFT JOIN deals d ON t.deal_id = d.id
        LEFT JOIN companies co ON t.company_id = co.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.id = :id
        LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => (int) $task_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $error = 'Task not found.';
        }
    } catch (PDOException $e) {
        $error = 'Error loading task: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

// Get flash messages
$flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
$flash_updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$flash_marked_done = isset($_GET['marked_done']) && $_GET['marked_done'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Task Details - CRM Dashboard</title>
    <!-- Import bootstrap with main.css -->
    <?php include 'partials/header.php'; ?>
</head>
<body>
    <!-- Navbar -->
    <?php include 'partials/nav.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Flash Messages -->
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_updated): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Task updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_marked_done): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Task marked as done.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Back Button and Actions -->
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <a href="tasks.php?status=&priority=" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Tasks
                </a>
                <?php if ($task): ?>
                    <div>
                        <?php if ($task['status'] !== 'done'): ?>
                            <form method="POST" action="/admin/task.php" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="mark_done" value="1">
                                <button type="submit" class="btn btn-success">
                                    Mark as Done
                                </button>
                            </form>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editTaskModal">
                            Edit Task
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTaskModal">
                            Delete Task
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
        <?php elseif ($task): ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Task Details Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div>
                                <?php
                                // Status badge
                                $status_class = '';
                                if ($task['status'] === 'open') $status_class = 'bg-primary';
                                elseif ($task['status'] === 'done') $status_class = 'bg-success';
                                elseif ($task['status'] === 'canceled') $status_class = 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $status_class; ?> me-2">
                                    <?php echo ucfirst($task['status']); ?>
                                </span>
                                <?php
                                // Priority badge
                                $priority_class = '';
                                if ($task['priority'] === 'high') $priority_class = 'bg-danger';
                                elseif ($task['priority'] === 'medium') $priority_class = 'bg-warning';
                                elseif ($task['priority'] === 'low') $priority_class = 'bg-info';
                                ?>
                                <span class="badge <?php echo $priority_class; ?>">
                                    <?php echo ucfirst($task['priority']); ?> Priority
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($task['notes']): ?>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Notes</h6>
                                    <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($task['notes'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <p class="text-muted mb-0"><em>No notes available.</em></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Task Information Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Task Information</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Status:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($task['status']); ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-5">Priority:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge <?php echo $priority_class; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-5">Due Date:</dt>
                                <dd class="col-sm-7">
                                    <?php if ($task['due_at']): 
                                        $due_date = new DateTime($task['due_at']);
                                        $is_overdue = $due_date < new DateTime() && $task['status'] !== 'done' && $task['status'] !== 'canceled';
                                    ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo $due_date->format('M d, Y'); ?>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-5">Assigned To:</dt>
                                <dd class="col-sm-7">
                                    <?php echo $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Unassigned</span>'; ?>
                                </dd>

                                <dt class="col-sm-5">Contact:</dt>
                                <dd class="col-sm-7">
                                    <?php if ($task['contact_name']): ?>
                                        <?php echo htmlspecialchars($task['contact_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-5">Deal:</dt>
                                <dd class="col-sm-7">
                                    <?php if ($task['deal_name']): ?>
                                        <?php echo htmlspecialchars($task['deal_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-5">Company:</dt>
                                <dd class="col-sm-7">
                                    <?php if (!empty($task['company_name'])): ?>
                                        <a href="company.php?id=<?php echo (int) $task['company_id']; ?>"><?php echo htmlspecialchars($task['company_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7">
                                    <?php 
                                    $created_date = new DateTime($task['created_at']);
                                    echo $created_date->format('M d, Y g:i A');
                                    ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Task Modal -->
    <?php if ($task): ?>
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/task.php" method="POST">
                    <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_task" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="5" placeholder="Task notes"><?php echo htmlspecialchars($task['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="open" <?php echo $task['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="done" <?php echo $task['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                                    <option value="canceled" <?php echo $task['status'] === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_priority" class="form-label">Priority</label>
                                <select class="form-select" id="edit_priority" name="priority">
                                    <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_due_at" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="edit_due_at" name="due_at" 
                                   value="<?php echo $task['due_at'] ? (new DateTime($task['due_at']))->format('Y-m-d') : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_deal" class="form-label">Deal</label>
                            <select class="form-select" id="edit_deal" name="deal_id">
                                <option value="">— None —</option>
                                <?php foreach ($deals as $d):
                                    $dname = $d['name'] ?? $d['Name'] ?? '';
                                    $selected = ($task['deal_id'] && (int) $task['deal_id'] === (int) $d['id']) ? ' selected' : '';
                                ?>
                                    <option value="<?php echo (int) $d['id']; ?>"<?php echo $selected; ?>><?php echo htmlspecialchars($dname, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company" class="form-label">Company</label>
                            <select class="form-select" id="edit_company" name="company_id">
                                <option value="">— None —</option>
                                <?php foreach ($companies as $co): ?>
                                    <option value="<?php echo (int) $co['id']; ?>" <?php echo $task['company_id'] && (int) $co['id'] === (int) $task['company_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="edit_assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $u):
                                    $uid = (int) $u['id'];
                                    $selected = ($task['assigned_to'] && (int) $task['assigned_to'] === $uid) ? ' selected' : '';
                                ?>
                                    <option value="<?php echo $uid; ?>"<?php echo $selected; ?>><?php echo htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Task Modal -->
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/task.php" method="POST">
                    <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="delete_task" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteTaskModalLabel">Delete Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this task?</p>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p class="text-danger mt-2"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
