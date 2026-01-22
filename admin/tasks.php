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
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2>Tasks</h2>
                <button class="btn btn-primary" type="button">Create New Task</button>
            </div>
        </div>

        <div class="row">
            <!-- Stats Cards -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Open Tasks</h5>
                        <h2 class="card-text">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Done</h5>
                        <h2 class="card-text">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">High Priority</h5>
                        <h2 class="card-text">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Overdue</h5>
                        <h2 class="card-text">0</h2>
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
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" style="width: auto;">
                                <option value="">All Statuses</option>
                                <option value="open">Open</option>
                                <option value="done">Done</option>
                                <option value="canceled">Canceled</option>
                            </select>
                            <select class="form-select form-select-sm" style="width: auto;">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
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
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No tasks found</td>
                                        </tr>
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
