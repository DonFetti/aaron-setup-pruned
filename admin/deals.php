<?php require_once __DIR__ . '/partials/no-cache.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deals - CRM Dashboard</title>
    <?php include __DIR__ . '/partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container-fluid mt-4">
        <?php
        $filter_status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $search_query = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

        $stats_total = 0;
        $stats_open = 0;
        $stats_won = 0;
        $stats_lost = 0;
        $deals = [];

        if ($pdo && !isset($db_error)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM deals");
                $stats_total = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM deals WHERE won_at IS NULL AND lost_at IS NULL");
                $stats_open = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM deals WHERE won_at IS NOT NULL");
                $stats_won = $stmt->fetch()['count'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM deals WHERE lost_at IS NOT NULL");
                $stats_lost = $stmt->fetch()['count'];

                $sql = "SELECT
                    d.id,
                    d.name,
                    d.amount,
                    d.stage,
                    d.close_date,
                    d.created_at,
                    d.won_at,
                    d.lost_at,
                    d.company_id,
                    c.name AS contact_name,
                    c.id AS contact_id,
                    co.name AS company_name
                FROM deals d
                INNER JOIN contacts c ON d.contact_id = c.id
                LEFT JOIN companies co ON d.company_id = co.id
                WHERE 1=1";
                $params = [];

                if ($filter_status === 'open') {
                    $sql .= " AND d.won_at IS NULL AND d.lost_at IS NULL";
                } elseif ($filter_status === 'won') {
                    $sql .= " AND d.won_at IS NOT NULL";
                } elseif ($filter_status === 'lost') {
                    $sql .= " AND d.lost_at IS NOT NULL";
                }

                if ($search_query !== '') {
                    $sql .= " AND (d.name ILIKE :search OR c.name ILIKE :search)";
                    $params[':search'] = '%' . $search_query . '%';
                }

                $sql .= " ORDER BY d.created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $deals = $stmt->fetchAll();
            } catch (PDOException $e) {
                $db_error = $e->getMessage();
            }
        }
        ?>

        <?php
        $flash_error = isset($_GET['error']) ? trim((string) $_GET['error']) : null;
        $flash_created = isset($_GET['created']) && $_GET['created'] === '1';
        $flash_deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
        ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_created): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Deal created successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_deleted): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Deal deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2>Deals</h2>
                <a href="deal-create.php" class="btn btn-primary">Add Deal</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Deals</h5>
                        <h2 class="card-text"><?php echo $stats_total; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Open</h5>
                        <h2 class="card-text"><?php echo $stats_open; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Won</h5>
                        <h2 class="card-text"><?php echo $stats_won; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Lost</h5>
                        <h2 class="card-text"><?php echo $stats_lost; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Deals</h5>
                        <form method="GET" action="" class="d-flex gap-2">
                            <?php if ($search_query): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <select name="status" class="form-select form-select-sm" style="width: auto;" aria-label="Filter by status" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="won" <?php echo $filter_status === 'won' ? 'selected' : ''; ?>>Won</option>
                                <option value="lost" <?php echo $filter_status === 'lost' ? 'selected' : ''; ?>>Lost</option>
                            </select>
                            <?php if ($filter_status || $search_query): ?>
                                <a href="deals.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (isset($db_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Database Connection Error:</strong><br>
                                <?php echo htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <form method="GET" action="" class="d-flex gap-2">
                                <?php if ($filter_status): ?>
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <input type="text"
                                           class="form-control"
                                           name="search"
                                           id="dealSearch"
                                           placeholder="Search by deal name or contact name..."
                                           value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary">Search</button>
                                <?php if ($search_query): ?>
                                    <a href="deals.php<?php echo $filter_status ? '?status=' . urlencode($filter_status) : ''; ?>" class="btn btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Deal</th>
                                        <th>Contact</th>
                                        <th>Company</th>
                                        <th>Amount</th>
                                        <th>Stage</th>
                                        <th>Close Date</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($deals)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <?php if ($search_query || $filter_status): ?>
                                                    No deals found matching your criteria.
                                                <?php else: ?>
                                                    No deals found.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($deals as $deal):
                                            $created_date = $deal['created_at'] ? new DateTime($deal['created_at']) : null;
                                            $close_date = $deal['close_date'] ? new DateTime($deal['close_date']) : null;

                                            if ($deal['won_at']) {
                                                $status = 'won';
                                                $status_class = 'bg-success';
                                            } elseif ($deal['lost_at']) {
                                                $status = 'lost';
                                                $status_class = 'bg-danger';
                                            } else {
                                                $status = 'open';
                                                $status_class = 'bg-primary';
                                            }

                                            $amount = $deal['amount'] !== null ? number_format((float) $deal['amount'], 2) : null;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($deal['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <a href="contact.php?id=<?php echo htmlspecialchars($deal['contact_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($deal['contact_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                </td>
                                                <td>
                                                    <?php if (!empty($deal['company_name'])): ?>
                                                        <a href="company.php?id=<?php echo (int) $deal['company_id']; ?>"><?php echo htmlspecialchars($deal['company_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $amount !== null ? '$' . $amount : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo $deal['stage'] ? htmlspecialchars($deal['stage'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($close_date): ?>
                                                        <?php echo $close_date->format('M d, Y'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($created_date): ?>
                                                        <?php echo $created_date->format('M d, Y'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="deal.php?id=<?php echo (int) $deal['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
