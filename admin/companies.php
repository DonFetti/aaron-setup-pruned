<?php require_once __DIR__ . '/partials/no-cache.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Companies - CRM Dashboard</title>
    <?php include 'partials/header.php'; ?>
</head>
<body>
    <?php include 'partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php
        $companies = [];
        $stats_total = 0;
        if ($pdo && !isset($db_error)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
                $stats_total = (int) $stmt->fetch()['count'];

                $sql = "SELECT c.id, c.name, c.company_type, c.created_at,
                         (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) AS contact_count
                         FROM companies c
                         ORDER BY c.name ASC";
                $stmt = $pdo->query($sql);
                $companies = $stmt->fetchAll();
            } catch (PDOException $e) {
                $db_error = $e->getMessage();
            }
        }
        ?>

        <?php
        $flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
        ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12">
                <h2>Companies</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Companies</h5>
                        <h2 class="card-text"><?php echo $stats_total; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Companies</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php elseif (empty($companies)): ?>
                            <p class="text-muted mb-0">No companies yet. Create a company when adding a contact.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Contacts</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $co): ?>
                                            <?php
                                            $created_date = !empty($co['created_at']) ? new DateTime($co['created_at']) : null;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($co['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($co['company_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int) $co['contact_count']; ?></td>
                                                <td><?php echo $created_date ? $created_date->format('M d, Y') : '—'; ?></td>
                                                <td>
                                                    <a href="company.php?id=<?php echo (int) $co['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
