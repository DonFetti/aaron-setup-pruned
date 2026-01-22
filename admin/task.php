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
        <?php
        // Get task ID from URL
        $task_id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
        $task = null;
        $error = null;

        // Validate task ID
        if ($task_id === '' || !ctype_digit($task_id)) {
            $error = 'Invalid task ID.';
        } elseif (!$pdo || isset($db_error)) {
            $error = 'Database connection unavailable.';
        } else {
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
                    t.assigned_to,
                    c.name as contact_name,
                    d.name as deal_name,
                    u.first_name as assigned_to_name
                FROM tasks t
                LEFT JOIN contacts c ON t.contact_id = c.id
                LEFT JOIN deals d ON t.deal_id = d.id
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
        ?>

        <!-- Back Button -->
        <div class="row mb-3">
            <div class="col-12">
                <a href="tasks.php?status=&priority=" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Tasks
                </a>
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
                                <dt class="col-sm-5">Task ID:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($task['id'], ENT_QUOTES, 'UTF-8'); ?></dd>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
